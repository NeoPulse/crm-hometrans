<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesCaseAccess;
use App\Models\Attention;
use App\Models\CaseChatMessage;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaseChatController extends Controller
{
    use ManagesCaseAccess;

    /**
     * Return chat messages for the case and mark current unread items as read.
     */
    public function index(Request $request, CaseFile $caseFile): JsonResponse
    {
        $viewer = $request->user();
        $this->authorizeCaseAccess($caseFile, $viewer);

        $afterId = $request->integer('after_id');

        // Load messages incrementally to keep polling light.
        $messages = $caseFile->chatMessages()
            ->with('user')
            ->when($afterId, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->get();

        $messageIds = $messages->pluck('id');

        // Capture unread markers before clearing them to surface NEW badges.
        $newForUser = Attention::where('user_id', $viewer->id)
            ->where('target_type', 'chat')
            ->where('type', 'msg')
            ->whereIn('target_id', $messageIds)
            ->pluck('target_id')
            ->toArray();

        Attention::where('user_id', $viewer->id)
            ->where('target_type', 'chat')
            ->where('type', 'msg')
            ->whereIn('target_id', $messageIds)
            ->delete();

        $payload = $messages->map(function (CaseChatMessage $message) use ($viewer, $newForUser) {
            return $this->formatMessage($message, $viewer, in_array($message->id, $newForUser, true));
        });

        return response()->json([
            'messages' => $payload,
            'unread_count' => $this->getUnreadCount($caseFile, $viewer),
        ]);
    }

    /**
     * Store a new chat message with optional attachment and notify participants.
     */
    public function store(Request $request, CaseFile $caseFile): JsonResponse
    {
        $sender = $request->user();
        $this->authorizeCaseAccess($caseFile, $sender);

        $senderLabel = $this->determineSenderLabel($sender, $caseFile, $request->input('send_as'));

        // Validate message body and attachment constraints.
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:20480'],
            'send_as' => ['nullable', 'in:manager,buy,sell'],
        ]);

        $attachmentMeta = $this->storeAttachment($request);

        $message = CaseChatMessage::create([
            'case_id' => $caseFile->id,
            'user_id' => $sender->id,
            'sender_label' => $senderLabel,
            'body' => $validated['body'] ?? null,
            'attachment_path' => $attachmentMeta['path'],
            'attachment_name' => $attachmentMeta['name'],
            'attachment_size' => $attachmentMeta['size'],
            'attachment_mime' => $attachmentMeta['mime'],
        ]);

        $this->notifyParticipants($caseFile, $sender, $message->id);

        $this->logAction(
            $sender,
            'create',
            'chat',
            $message->id,
            "case/{$caseFile->id}/chat",
            'Posted a chat message.'
        );

        return response()->json([
            'message' => $this->formatMessage($message->load('user'), $sender, false),
            'unread_count' => $this->getUnreadCount($caseFile, $sender),
        ], 201);
    }

    /**
     * Remove a chat message and any associated attachment.
     */
    public function destroy(Request $request, CaseFile $caseFile, CaseChatMessage $chatMessage): JsonResponse
    {
        $this->assertAdmin($request->user());

        if ($chatMessage->case_id !== $caseFile->id) {
            abort(404, 'Message does not belong to this case.');
        }

        if ($chatMessage->attachment_path) {
            Storage::delete($chatMessage->attachment_path);
        }

        Attention::where('target_type', 'chat')->where('target_id', $chatMessage->id)->delete();
        $chatMessage->delete();

        $this->logAction(
            $request->user(),
            'delete',
            'chat',
            $chatMessage->id,
            "case/{$caseFile->id}/chat",
            'Deleted a chat message.'
        );

        return response()->json([
            'message' => 'Message removed successfully.',
            'unread_count' => $this->getUnreadCount($caseFile, $request->user()),
        ]);
    }

    /**
     * Provide an unread counter for the floating chat button.
     */
    public function unread(Request $request, CaseFile $caseFile): JsonResponse
    {
        $viewer = $request->user();
        $this->authorizeCaseAccess($caseFile, $viewer);

        return response()->json([
            'unread_count' => $this->getUnreadCount($caseFile, $viewer),
        ]);
    }

    /**
     * Deliver an attachment after verifying case access.
     */
    public function download(Request $request, CaseFile $caseFile, CaseChatMessage $chatMessage)
    {
        $viewer = $request->user();
        $this->authorizeCaseAccess($caseFile, $viewer);

        if ($chatMessage->case_id !== $caseFile->id || ! $chatMessage->attachment_path) {
            abort(404, 'Attachment not found.');
        }

        $this->logAction(
            $viewer,
            'download',
            'chat',
            $chatMessage->id,
            "case/{$caseFile->id}/chat/file",
            'Downloaded a chat attachment.'
        );

        return Storage::download($chatMessage->attachment_path, $chatMessage->attachment_name ?? 'chat-file');
    }

    /**
     * Decide which label to display for the sender role.
     */
    protected function determineSenderLabel(User $user, CaseFile $caseFile, ?string $requestedLabel): string
    {
        if ($user->role === 'admin') {
            return in_array($requestedLabel, ['manager', 'buy', 'sell'], true) ? $requestedLabel : 'manager';
        }

        if ($user->role !== 'legal') {
            abort(403, 'Only solicitors and managers can post.');
        }

        if ($caseFile->buy_legal_id === $user->id) {
            return 'buy';
        }

        if ($caseFile->sell_legal_id === $user->id) {
            return 'sell';
        }

        abort(403, 'You are not assigned to this case.');
    }

    /**
     * Persist an uploaded file and return metadata for storage.
     */
    protected function storeAttachment(Request $request): array
    {
        $file = $request->file('file');
        if (! $file) {
            return ['path' => null, 'name' => null, 'size' => null, 'mime' => null];
        }

        $extension = $file->getClientOriginalExtension();
        $uniqueName = $extension ? Str::uuid()->toString() . '.' . $extension : Str::uuid()->toString();
        $path = $file->storeAs('case_chat', $uniqueName);

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Generate attention records for all participants except the sender.
     */
    protected function notifyParticipants(CaseFile $caseFile, User $sender, int $messageId): void
    {
        $recipientIds = array_filter([
            $caseFile->sell_client_id,
            $caseFile->buy_client_id,
            $caseFile->sell_legal_id,
            $caseFile->buy_legal_id,
        ]);

        foreach ($recipientIds as $userId) {
            if ($userId === $sender->id) {
                continue;
            }

            Attention::firstOrCreate([
                'target_type' => 'chat',
                'target_id' => $messageId,
                'type' => 'msg',
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Convert a message into a JSON-friendly structure for the UI.
     */
    protected function formatMessage(CaseChatMessage $message, User $viewer, bool $isNew): array
    {
        return [
            'id' => $message->id,
            'label' => $this->presentLabel($message->sender_label),
            'body' => $message->body,
            'is_mine' => $message->user_id === $viewer->id,
            'is_new' => $isNew,
            'created_at' => $message->created_at?->toDateTimeString(),
            'attachment' => $message->attachment_path ? [
                'name' => $message->attachment_name,
                'size' => $message->attachment_size,
                'mime' => $message->attachment_mime,
                'url' => route('cases.chat.download', [$message->case_id, $message->id]),
            ] : null,
        ];
    }

    /**
     * Present user-facing sender labels.
     */
    protected function presentLabel(string $rawLabel): string
    {
        return match ($rawLabel) {
            'buy' => 'Buy Side',
            'sell' => 'Sell Side',
            default => 'Manager',
        };
    }

    /**
     * Retrieve unread count scoped to the case for the viewer.
     */
    protected function getUnreadCount(CaseFile $caseFile, User $user): int
    {
        return Attention::where('user_id', $user->id)
            ->where('target_type', 'chat')
            ->where('type', 'msg')
            ->whereIn('target_id', CaseChatMessage::where('case_id', $caseFile->id)->select('id'))
            ->count();
    }
}

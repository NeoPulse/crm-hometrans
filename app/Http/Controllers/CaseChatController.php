<?php

namespace App\Http\Controllers;

use App\Models\Attention;
use App\Models\CaseFile;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CaseChatController extends Controller
{
    /**
     * Retrieve chat history for a case and mark unread messages as read for the viewer.
     */
    public function index(Request $request, CaseFile $caseFile): JsonResponse
    {
        // Determine the viewer and confirm they can access the case chat.
        $viewer = $request->user();
        $this->authorizeCaseAccess($caseFile, $viewer);

        // Pull chat messages along with unread markers for the current user.
        $messages = $caseFile->chatMessages()
            ->with([
                'user:id,name',
                'attentions' => fn ($query) => $query->where('user_id', $viewer->id)->where('type', 'chat'),
            ])
            ->orderBy('id')
            ->get();

        // Convert records into transport-friendly payloads.
        $payload = $messages->map(fn (ChatMessage $message) => $this->formatMessage($message, $viewer, $caseFile))->values();

        // Clear unread flags now that the viewer has loaded the chat.
        $this->clearChatAttentions($viewer, $messages);

        // Optionally log the view event when explicitly requested by the UI.
        if ($request->boolean('log_view', false)) {
            $this->logAction(
                $viewer,
                'view',
                'chat',
                $caseFile->id,
                "cases/{$caseFile->id}/chat",
                'Opened the case chat thread.'
            );
        }

        // Return the messages with the current unread count (should be zero after clearing).
        return response()->json([
            'messages' => $payload,
            'unread' => $this->countUnread($caseFile, $viewer),
        ]);
    }

    /**
     * Store a new chat message with optional attachment.
     */
    public function store(Request $request, CaseFile $caseFile): JsonResponse
    {
        // Fetch the author and validate that they can participate.
        $author = $request->user();
        $this->authorizeCaseAccess($caseFile, $author);
        $this->assertCanSend($caseFile, $author);

        // Validate payload ensuring either text or a file is provided.
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'sender_label' => ['sometimes', 'in:manager,buy,sell'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);

        // Work out the sender label according to role and request input.
        $senderLabel = $this->resolveSenderLabel($caseFile, $author, $validated['sender_label'] ?? null);

        // Ensure the message contains either body text or an attachment.
        if (empty($validated['body']) && ! $request->hasFile('attachment')) {
            return response()->json([
                'message' => 'Please provide text or attach a document.',
            ], 422);
        }

        // Persist the message and any attachment atomically.
        $message = null;
        DB::transaction(function () use ($caseFile, $author, $senderLabel, $validated, $request, &$message) {
            // Prepare attachment metadata for secure storage.
            $attachmentPath = null;
            $attachmentName = null;
            $attachmentMime = null;
            $attachmentSize = null;

            // Store uploaded file in a private directory to prevent public exposure.
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachmentPath = $file->store('chat_uploads');
                $attachmentName = $file->getClientOriginalName();
                $attachmentMime = $file->getClientMimeType();
                $attachmentSize = $file->getSize();
            }

            // Create the chat message record with captured attributes.
            $message = ChatMessage::create([
                'case_id' => $caseFile->id,
                'user_id' => $author->id,
                'sender_label' => $senderLabel,
                'body' => $validated['body'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'attachment_size' => $attachmentSize,
            ]);

            // Flag unread chat notifications for all other participants.
            $this->createChatAttentions($caseFile, $author->id, $message->id);
        });

        // Log the new message creation for auditing.
        $this->logAction(
            $author,
            'create',
            'chat_message',
            $message->id,
            "cases/{$caseFile->id}/chat",
            'Posted a message in the case chat.'
        );

        // Return the formatted message so the UI can append it instantly.
        $message->load(['attentions' => fn ($query) => $query->where('user_id', $author->id)->where('type', 'chat')]);

        return response()->json([
            'message' => 'Message sent successfully.',
            'entry' => $this->formatMessage($message, $author, $caseFile),
            'unread' => $this->countUnread($caseFile, $author),
        ], 201);
    }

    /**
     * Delete a chat message and its related attentions.
     */
    public function destroy(Request $request, CaseFile $caseFile, ChatMessage $chatMessage): JsonResponse
    {
        // Confirm the requester has permission to remove chat content.
        $admin = $request->user();
        $this->authorizeCaseAccess($caseFile, $admin);
        $this->assertAdmin($admin);

        // Ensure the message belongs to the provided case context.
        if ($chatMessage->case_id !== $caseFile->id) {
            abort(404, 'Chat message not found for this case.');
        }

        // Remove attached file and linked attentions safely.
        DB::transaction(function () use ($chatMessage) {
            if ($chatMessage->attachment_path) {
                Storage::delete($chatMessage->attachment_path);
            }
            Attention::where('target_type', 'chat_message')->where('target_id', $chatMessage->id)->delete();
            $chatMessage->delete();
        });

        // Log the deletion event for auditing.
        $this->logAction(
            $admin,
            'delete',
            'chat_message',
            $chatMessage->id,
            "cases/{$caseFile->id}/chat",
            'Deleted a chat message.'
        );

        return response()->json([
            'message' => 'Chat message deleted.',
            'unread' => $this->countUnread($caseFile, $admin),
        ]);
    }

    /**
     * Provide the unread chat counter for the current user without altering state.
     */
    public function unreadCount(Request $request, CaseFile $caseFile): JsonResponse
    {
        // Authorize visibility for the requesting user.
        $viewer = $request->user();
        $this->authorizeCaseAccess($caseFile, $viewer);

        // Return only the count to keep polling lightweight.
        return response()->json([
            'unread' => $this->countUnread($caseFile, $viewer),
        ]);
    }

    /**
     * Download an attachment securely for an authorized viewer.
     */
    public function download(Request $request, CaseFile $caseFile, ChatMessage $chatMessage): BinaryFileResponse
    {
        // Verify case access and message ownership.
        $viewer = $request->user();
        $this->authorizeCaseAccess($caseFile, $viewer);
        if ($chatMessage->case_id !== $caseFile->id || ! $chatMessage->attachment_path) {
            abort(404, 'Attachment not found.');
        }

        // Log the download action for compliance.
        $this->logAction(
            $viewer,
            'download',
            'chat_attachment',
            $chatMessage->id,
            "cases/{$caseFile->id}/chat",
            'Downloaded a chat attachment.'
        );

        // Stream the file from protected storage with its original name.
        return Storage::download(
            $chatMessage->attachment_path,
            $chatMessage->attachment_name ?? 'chat-attachment',
            ['Content-Type' => $chatMessage->attachment_mime]
        );
    }

    /**
     * Translate the chat message model into a lightweight array for JSON delivery.
     */
    protected function formatMessage(ChatMessage $message, User $viewer, CaseFile $caseFile): array
    {
        // Map stored label to the user-facing badge text.
        $labelDisplay = match ($message->sender_label) {
            'buy' => 'Buy Side',
            'sell' => 'Sell Side',
            default => 'Manager',
        };

        // Build a secure download link when an attachment exists.
        $attachment = $message->attachment_path ? [
            'name' => $message->attachment_name,
            'size' => $message->attachment_size,
            'url' => route('cases.chat.download', [$caseFile->id, $message->id]),
        ] : null;

        return [
            'id' => $message->id,
            'label' => $labelDisplay,
            'body' => $message->body,
            'attachment' => $attachment,
            'created_at' => $message->created_at?->format('d M Y H:i'),
            'is_own' => $message->user_id === $viewer->id,
            'is_new' => $message->attentions->isNotEmpty(),
        ];
    }

    /**
     * Ensure the supplied user can reach the case context.
     */
    protected function authorizeCaseAccess(CaseFile $caseFile, User $user): void
    {
        // Administrators bypass participant-specific checks.
        if ($user->role === 'admin') {
            return;
        }

        // Only in-progress cases may be opened by clients or legal participants.
        if ($caseFile->status !== 'progress') {
            abort(403, 'Only in-progress cases are available.');
        }

        // Allow access when the user is assigned on either side as client or solicitor.
        $participantIds = array_filter([
            $caseFile->sell_client_id,
            $caseFile->buy_client_id,
            $caseFile->sell_legal_id,
            $caseFile->buy_legal_id,
        ]);

        if (! in_array($user->id, $participantIds, true)) {
            abort(403, 'You do not have access to this case.');
        }
    }

    /**
     * Verify that the authenticated user is allowed to post messages.
     */
    protected function assertCanSend(CaseFile $caseFile, User $user): void
    {
        // Administrators and assigned legal representatives may post.
        if ($user->role === 'admin') {
            return;
        }

        $isAssignedLegal = in_array($user->id, [$caseFile->buy_legal_id, $caseFile->sell_legal_id], true);
        if (! $isAssignedLegal) {
            abort(403, 'Only assigned solicitors or administrators can post messages.');
        }
    }

    /**
     * Enforce admin-only actions for destructive operations.
     */
    protected function assertAdmin(User $user): void
    {
        if ($user->role !== 'admin') {
            abort(403, 'Only administrators can manage chat messages.');
        }
    }

    /**
     * Resolve the sender label based on role and provided choice.
     */
    protected function resolveSenderLabel(CaseFile $caseFile, User $user, ?string $requestedLabel): string
    {
        // Administrators may impersonate either side or speak as manager.
        if ($user->role === 'admin') {
            return $requestedLabel ?? 'manager';
        }

        // Legal users are locked to their assigned side.
        if ($user->id === $caseFile->buy_legal_id) {
            return 'buy';
        }

        if ($user->id === $caseFile->sell_legal_id) {
            return 'sell';
        }

        abort(403, 'You cannot post to this chat.');
    }

    /**
     * Create unread markers for all participants except the sender.
     */
    protected function createChatAttentions(CaseFile $caseFile, int $senderId, int $messageId): void
    {
        // Collect participant identifiers, including administrators.
        $recipientIds = array_filter([
            $caseFile->sell_client_id,
            $caseFile->buy_client_id,
            $caseFile->sell_legal_id,
            $caseFile->buy_legal_id,
        ]);

        // Append all admin user ids to keep managers informed.
        $adminIds = User::where('role', 'admin')->pluck('id')->all();
        $recipientIds = array_unique(array_merge($recipientIds, $adminIds));

        // Insert unread flags for everyone except the sender.
        foreach ($recipientIds as $recipientId) {
            if ($recipientId === $senderId) {
                continue;
            }

            Attention::firstOrCreate([
                'target_type' => 'chat_message',
                'target_id' => $messageId,
                'type' => 'chat',
                'user_id' => $recipientId,
            ]);
        }
    }

    /**
     * Remove unread markers for the provided viewer and chat messages.
     */
    protected function clearChatAttentions(User $viewer, $messages): void
    {
        // Extract message identifiers to bulk delete attentions efficiently.
        $messageIds = $messages->pluck('id');

        Attention::where('user_id', $viewer->id)
            ->where('type', 'chat')
            ->where('target_type', 'chat_message')
            ->whereIn('target_id', $messageIds)
            ->delete();
    }

    /**
     * Count unread chat attentions for the supplied case and viewer.
     */
    protected function countUnread(CaseFile $caseFile, User $viewer): int
    {
        // Join against chat_messages to ensure the count is scoped to the case.
        return Attention::where('attentions.user_id', $viewer->id)
            ->where('attentions.type', 'chat')
            ->where('attentions.target_type', 'chat_message')
            ->whereIn('attentions.target_id', function ($query) use ($caseFile) {
                $query->select('id')->from('chat_messages')->where('case_id', $caseFile->id);
            })
            ->count();
    }

    /**
     * Append a record to the activity log for compliance.
     */
    protected function logAction(User $user, string $action, ?string $targetType, ?int $targetId, ?string $location, string $details): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'location' => $location,
            'details' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

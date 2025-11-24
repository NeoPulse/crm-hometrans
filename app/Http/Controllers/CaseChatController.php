<?php

namespace App\Http\Controllers;

use App\Models\CaseChatMessage;
use App\Models\CaseFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CaseChatController extends Controller
{
    public function index(Request $request, CaseFile $case)
    {
        $this->ensureCanView($request, $case);

        $messages = $case->chatMessages()->with('sender')->orderBy('id')->get()->map(function (CaseChatMessage $message) {
            return [
                'id' => $message->id,
                'body' => $message->body,
                'alias' => $message->sender_alias ?? $message->sender?->name ?? 'Guest',
                'is_own' => Auth::id() && Auth::id() === $message->sender_id,
                'created_at' => $message->created_at->format('H:i d/m/Y'),
                'attachment_url' => $message->attachment_path ? Storage::url($message->attachment_path) : null,
                'attachment_name' => $message->attachment_path ? basename($message->attachment_path) : null,
            ];
        });

        return response()->json([
            'messages' => $messages,
            'can_post' => Auth::check() && in_array(Auth::user()->role, ['admin', 'legal']),
        ]);
    }

    public function store(Request $request, CaseFile $case)
    {
        $this->ensureCanView($request, $case);

        abort_unless(Auth::check() && in_array(Auth::user()->role, ['admin', 'legal']), 403);

        $validated = $request->validate([
            'body' => 'nullable|string',
            'alias' => ['nullable', Rule::in(['Manager', 'Buy Side', 'Sell Side', 'Admin'])],
            'attachment' => 'nullable|file|max:20480',
        ]);

        if (! ($validated['body'] ?? null) && ! $request->file('attachment')) {
            return response()->json(['message' => 'Message is empty'], 422);
        }

        $alias = $validated['alias'] ?? null;
        if (! $alias && Auth::user()->role === 'legal') {
            if ($case->buy_legal_id === Auth::id()) {
                $alias = 'Buy-side';
            } elseif ($case->sell_legal_id === Auth::id()) {
                $alias = 'Sell-side';
            } else {
                $alias = 'Legal';
            }
        }

        $attachmentPath = null;
        if ($request->file('attachment')) {
            $attachmentPath = $request->file('attachment')->store('case-chat', 'public');
        }

        $message = CaseChatMessage::create([
            'case_id' => $case->id,
            'sender_id' => Auth::id(),
            'sender_alias' => $alias,
            'body' => $validated['body'] ?? '',
            'attachment_path' => $attachmentPath,
        ]);

        return response()->json([
            'id' => $message->id,
        ], 201);
    }

    public function destroy(Request $request, CaseFile $case, CaseChatMessage $message)
    {
        $this->ensureCanView($request, $case);
        abort_unless(Auth::check() && Auth::user()->role === 'admin', 403);
        abort_if($message->case_id !== $case->id, 404);

        if ($message->attachment_path) {
            Storage::disk('public')->delete($message->attachment_path);
        }

        $message->delete();

        return response()->json(['status' => 'deleted']);
    }

    protected function ensureCanView(Request $request, CaseFile $case): void
    {
        if (Auth::check()) {
            if (Auth::user()->role === 'admin') {
                return;
            }

            $participantIds = array_filter([
                $case->sell_legal_id,
                $case->buy_legal_id,
                $case->sell_client_id,
                $case->buy_client_id,
            ]);

            abort_unless(in_array(Auth::id(), $participantIds), 403);
            return;
        }

        if ($case->status !== 'progress') {
            abort(404);
        }

        $sessionKey = "case_public_{$case->id}";
        if ($request->query('token') === $case->public_link || session()->get($sessionKey)) {
            return;
        }

        abort(403);
    }
}

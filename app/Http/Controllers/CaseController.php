<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use App\Models\Attention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = CaseFile::query();
        if ($user->role === 'legal') {
            $query->where(function ($q) use ($user) {
                $q->where('sell_legal_id', $user->id)->orWhere('buy_legal_id', $user->id);
            })->where('status', 'progress');
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('postal_code', 'like', "%$search%")
                    ->orWhere('headline', 'like', "%$search%")
                    ->orWhere('notes', 'like', "%$search%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $cases = $query->orderBy('deadline')->paginate(20)->withQueryString();

        return view('cases.index', compact('cases'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'postal_code' => 'required',
        ]);

        $case = CaseFile::create([
            'postal_code' => $request->postal_code,
            'status' => 'new',
            'public_link' => Str::random(10),
        ]);

        $this->logActivity('case_created', $case, 'Case created');

        return redirect()->route('cases.edit', $case);
    }

    public function edit(CaseFile $case)
    {
        $this->authorizeAdmin();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $stages = $case->stages()->with('tasks')->orderBy('id')->get();
        $caseHeaderData = $this->buildCaseHeaderData($case);
        return view('cases.edit', compact('case', 'users', 'stages', 'caseHeaderData'));
    }

    public function update(Request $request, CaseFile $case)
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'postal_code' => 'required',
            'status' => 'required',
            'deadline' => 'nullable|date',
        ]);

        $case->fill([
            'postal_code' => $validated['postal_code'],
            'status' => $validated['status'],
            'deadline' => $validated['deadline'] ?? null,
            'property' => $request->property,
            'headline' => $request->headline,
            'notes' => $request->notes,
            'sell_legal_id' => $request->sell_legal_id,
            'sell_client_id' => $request->sell_client_id,
            'buy_legal_id' => $request->buy_legal_id,
            'buy_client_id' => $request->buy_client_id,
        ])->save();

        $this->logActivity('case_updated', $case, 'Case data saved');

        return back()->with('status', 'Case saved');
    }

    public function addStage(Request $request, CaseFile $case)
    {
        $this->authorizeAdmin();
        $request->validate(['name' => 'required']);
        $stage = Stage::create([
            'case_id' => $case->id,
            'name' => $request->name,
        ]);

        $this->createNewAttentions($case, 'stage', $stage->id);

        $this->logActivity('stage_added', $case, 'Stage added to case');

        if ($request->wantsJson()) {
            return response()->json([
                'stage' => $stage,
                'progress' => $stage->completedTaskRatio(),
            ]);
        }

        return back()->with('status', 'Stage added');
    }

    public function addTask(Request $request, CaseFile $case)
    {
        $this->authorizeAdmin();
        $request->validate([
            'stage_id' => 'required|exists:stages,id',
            'name' => 'required',
            'side' => 'required',
            'status' => 'required',
        ]);

        $task = Task::create([
            'stage_id' => $request->stage_id,
            'name' => $request->name,
            'side' => $request->side,
            'status' => $request->status,
            'deadline' => $request->deadline,
        ]);

        $this->createNewAttentions($case, 'task', $task->id);

        $this->logActivity('task_created', $case, 'Task added to case');

        if ($request->wantsJson()) {
            $stage = $task->stage()->with('tasks')->first();
            return response()->json([
                'task' => $task,
                'stage_progress' => $stage?->completedTaskRatio() ?? 0,
            ]);
        }

        return back()->with('status', 'Task created');
    }

    public function publicShow(Request $request, CaseFile $case)
    {
        if ($response = $this->guardPublicAccess($request, $case)) {
            return $response;
        }

        $this->markNewAttentionsSeen($case, Auth::user());

        $stages = $case->stages()->with('tasks')->orderBy('id')->get();
        $caseHeaderData = $this->buildCaseHeaderData($case);
        $attentionMap = $this->gatherAttentionMap($case, Auth::user());

        return view('cases.show', compact('case', 'stages', 'caseHeaderData', 'attentionMap'));
    }

    public function updateStage(Request $request, CaseFile $case, Stage $stage): JsonResponse
    {
        $this->authorizeAdmin();
        $this->abortIfStageOutsideCase($case, $stage);

        $validated = $request->validate(['name' => 'required|string|max:255']);
        $stage->update(['name' => $validated['name']]);

        return response()->json([
            'stage' => $stage,
            'progress' => $stage->completedTaskRatio(),
        ]);
    }

    public function deleteStage(Request $request, CaseFile $case, Stage $stage): JsonResponse
    {
        $this->authorizeAdmin();
        $this->abortIfStageOutsideCase($case, $stage);

        $stage->delete();

        return response()->json(['status' => 'deleted']);
    }

    public function updateTask(Request $request, CaseFile $case, Task $task): JsonResponse
    {
        $this->authorizeAdmin();
        $this->abortIfTaskOutsideCase($case, $task);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'deadline' => 'nullable|date',
            'status' => 'sometimes|required|in:new,progress,done',
        ]);

        $task->fill($validated);
        $task->save();

        $stage = $task->stage()->with('tasks')->first();

        return response()->json([
            'task' => $task,
            'stage_progress' => $stage?->completedTaskRatio() ?? 0,
        ]);
    }

    public function deleteTask(Request $request, CaseFile $case, Task $task): JsonResponse
    {
        $this->authorizeAdmin();
        $this->abortIfTaskOutsideCase($case, $task);

        $stage = $task->stage;
        $task->delete();

        return response()->json([
            'status' => 'deleted',
            'stage_progress' => $stage?->completedTaskRatio() ?? 0,
        ]);
    }

    public function quickAddTask(Request $request, CaseFile $case): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'stage_id' => 'required|exists:stages,id',
            'side' => 'required|in:seller,buyer',
        ]);

        $stage = Stage::findOrFail($validated['stage_id']);
        $this->abortIfStageOutsideCase($case, $stage);

        $task = Task::create([
            'stage_id' => $stage->id,
            'name' => 'New task',
            'side' => $validated['side'],
            'status' => 'new',
            'deadline' => null,
        ]);

        $this->createNewAttentions($case, 'task', $task->id);

        return response()->json([
            'task' => $task,
            'stage_progress' => $stage->completedTaskRatio(),
        ]);
    }

    protected function authorizeAdmin(): void
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }
    }

    /**
     * Prepare header metadata for case pages.
     */
    protected function buildCaseHeaderData(CaseFile $case): array
    {
        $case->loadMissing('sellLegal.legalProfile', 'buyLegal.legalProfile');
        $admin = User::where('role', 'admin')->orderBy('id')->first();

        return [
            'postal_code' => $case->postal_code,
            'deadline' => $case->deadline ? $case->deadline->format('d/M') : 'No deadline',
            'people' => [
                $this->mapPerson($admin, 'Project manager'),
                $this->mapPerson($case->sellLegal, "Seller's solicitor", true),
                $this->mapPerson($case->buyLegal, "Buyer's solicitor", true),
            ],
        ];
    }

    /**
     * Map person data into tooltip-friendly structure.
     */
    protected function mapPerson(?User $user, string $label, bool $includeOffice = false): array
    {
        $name = $user?->name ?? 'Not assigned';
        $office = $includeOffice ? $user?->legalProfile?->office : null;
        $email = $user?->email;
        $phone = $user?->phone;

        $tooltip = '<div><strong>' . e($name) . '</strong></div>';
        if ($office) {
            $tooltip .= '<div>Office: ' . e($office) . '</div>';
        }
        if ($phone) {
            $tooltip .= '<div><a class="text-white" href="tel:' . e($phone) . '">' . e($phone) . '</a></div>';
        }
        if ($email) {
            $tooltip .= '<div><a class="text-white" href="mailto:' . e($email) . '">' . e($email) . '</a></div>';
        }

        return [
            'label' => $label,
            'avatar' => $user?->avatar_url ?? asset('images/avatar-placeholder.svg'),
            'tooltip' => $tooltip,
        ];
    }

    protected function guardPublicAccess(Request $request, CaseFile $case)
    {
        $token = $request->query('token');
        $isAuthenticated = Auth::check();

        if ($isAuthenticated) {
            $this->ensureCaseViewer(Auth::user(), $case);
            return;
        }

        if ($case->status !== 'progress' || $token !== $case->public_link) {
            abort(403);
        }

        $sessionKey = "case_public_{$case->id}";
        $attemptKey = $sessionKey . '_attempts';
        $lockedUntilKey = $sessionKey . '_locked_until';

        $lockedUntil = session($lockedUntilKey);
        if ($lockedUntil && now()->lt($lockedUntil)) {
            abort(429, 'Too many attempts. Please try later.');
        }

        if (! session()->get($sessionKey)) {
            if ($request->isMethod('post')) {
                $request->validate(['postal_code' => 'required']);
                if (strcasecmp($request->postal_code, $case->postal_code) === 0) {
                    session([$sessionKey => true]);
                    session()->forget([$attemptKey, $lockedUntilKey]);
                } else {
                    $attempts = session($attemptKey, 0) + 1;
                    session([$attemptKey => $attempts]);
                    if ($attempts >= 5) {
                        session([$lockedUntilKey => now()->addMinutes(5)]);
                    }
                    return back()->withErrors(['postal_code' => 'Postal code mismatch']);
                }
            } else {
                abort_if(!$request->isMethod('get'), 403);
                return view('cases.public', compact('case'));
            }
        }
    }

    protected function ensureCaseViewer(User $user, CaseFile $case): void
    {
        if ($user->role === 'admin') {
            return;
        }

        $isParticipant = in_array($user->id, array_filter([
            $case->sell_legal_id,
            $case->buy_legal_id,
            $case->sell_client_id,
            $case->buy_client_id,
        ]));

        if (! $isParticipant) {
            abort(403);
        }
    }

    protected function abortIfStageOutsideCase(CaseFile $case, Stage $stage): void
    {
        abort_if($stage->case_id !== $case->id, 404);
    }

    protected function abortIfTaskOutsideCase(CaseFile $case, Task $task): void
    {
        abort_if($task->stage->case_id !== $case->id, 404);
    }

    protected function createNewAttentions(CaseFile $case, string $type, int $targetId): void
    {
        foreach ($this->caseParticipantIds($case) as $userId) {
            Attention::updateOrCreate([
                'target_type' => $type,
                'target_id' => $targetId,
                'type' => 'new',
                'user_id' => $userId,
            ], []);
        }
    }

    protected function caseParticipantIds(CaseFile $case): array
    {
        return array_filter([
            $case->sell_client_id,
            $case->buy_client_id,
            $case->sell_legal_id,
            $case->buy_legal_id,
        ]);
    }

    protected function markNewAttentionsSeen(CaseFile $case, ?User $user): void
    {
        if (! $user) {
            return;
        }

        $stageIds = $case->stages()->pluck('id');
        $taskIds = Task::whereIn('stage_id', $stageIds)->pluck('id');

        Attention::where('user_id', $user->id)
            ->where('type', 'new')
            ->where(function ($q) use ($stageIds, $taskIds) {
                $q->where(function ($sub) use ($stageIds) {
                    $sub->where('target_type', 'stage')->whereIn('target_id', $stageIds);
                })->orWhere(function ($sub) use ($taskIds) {
                    $sub->where('target_type', 'task')->whereIn('target_id', $taskIds);
                });
            })->delete();
    }

    protected function gatherAttentionMap(CaseFile $case, ?User $user): array
    {
        if (! $user) {
            return ['stages' => [], 'tasks' => []];
        }

        $stageIds = Attention::where('user_id', $user->id)
            ->where('type', 'new')
            ->where('target_type', 'stage')
            ->pluck('target_id')
            ->toArray();

        $taskIds = Attention::where('user_id', $user->id)
            ->where('type', 'new')
            ->where('target_type', 'task')
            ->pluck('target_id')
            ->toArray();

        return [
            'stages' => $stageIds,
            'tasks' => $taskIds,
        ];
    }
}

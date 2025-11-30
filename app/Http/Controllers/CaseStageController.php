<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesCaseAccess;
use App\Models\Attention;
use App\Models\CaseFile;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CaseStageController extends Controller
{
    use ManagesCaseAccess;

    /**
     * Display the case timeline with stages and tasks.
     */
    public function show(CaseFile $caseFile, Request $request): Response
    {
        // Guard access based on the viewer's role and case association.
        $viewer = $request->user();
        $this->authorizeCaseAccess($caseFile, $viewer);

        // Load participants and related profiles for tooltip details.
        $caseFile->load(['sellLegal.legalProfile', 'buyLegal.legalProfile', 'sellClient', 'buyClient']);

        // Retrieve stage data with task ordering and unread markers for the current user.
        $stages = $this->loadStagesWithAttentions($caseFile, $viewer);

        // Prepare stage payloads for the Blade template rendering.
        $stagePayloads = $stages->map(fn (Stage $stage) => $this->formatStage($stage, $viewer))->values();

        // Remove "new" attention markers once the viewer has opened the page.
        $this->clearNewMarkers($viewer, $stages);

        // Render the case detail blade with participant and stage data.
        return response()->view('cases.show', [
            'case' => $caseFile,
            'participants' => $this->buildParticipants($caseFile),
            'stages' => $stagePayloads,
            'isAdmin' => $viewer->role === 'admin',
            'chatProfile' => $this->buildChatProfile($caseFile, $viewer),
        ]);
    }

    /**
     * Create a new stage within the specified case.
     */
    public function storeStage(Request $request, CaseFile $caseFile): JsonResponse
    {
        // Only administrators can modify the case structure.
        $this->assertAdmin($request->user());

        // Validate the incoming stage name to avoid empty records.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:300'],
        ]);

        // Persist the stage and notify relevant participants.
        $stage = null;
        DB::transaction(function () use ($caseFile, $validated, &$stage) {
            $stage = Stage::create([
                'case_id' => $caseFile->id,
                'name' => $validated['name'],
            ]);

            // Create attention records for clients and solicitors assigned to the case.
            $this->createNewAttention($caseFile, 'stage', $stage->id);
        });

        // Log the creation event with contextual details.
        $this->logAction(
            $request->user(),
            'create',
            'stage',
            $stage->id,
            "cases/{$caseFile->id}/stages",
            "Created stage: {$stage->name}"
        );

        // Return an updated stage collection for the UI.
        $stages = $this->loadStagesWithAttentions($caseFile, $request->user());

        return response()->json([
            'stages' => $stages->map(fn (Stage $stageItem) => $this->formatStage($stageItem, $request->user()))->values(),
            'message' => 'Stage created successfully.',
        ], 201);
    }

    /**
     * Update an existing stage name.
     */
    public function updateStage(Request $request, Stage $stage): JsonResponse
    {
        // Restrict the update to administrators.
        $this->assertAdmin($request->user());

        // Validate the new stage title for length and presence.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:300'],
        ]);

        // Apply the update to the stage name.
        $stage->update(['name' => $validated['name']]);

        // Record the update in the audit log.
        $this->logAction(
            $request->user(),
            'update',
            'stage',
            $stage->id,
            "cases/{$stage->case_id}/stages",
            "Renamed stage to {$stage->name}"
        );

        // Return refreshed stage data to the frontend.
        $stages = $this->loadStagesWithAttentions($stage->caseFile, $request->user());

        return response()->json([
            'stages' => $stages->map(fn (Stage $stageItem) => $this->formatStage($stageItem, $request->user()))->values(),
            'message' => 'Stage updated successfully.',
        ]);
    }

    /**
     * Remove a stage and its tasks.
     */
    public function destroyStage(Request $request, Stage $stage): JsonResponse
    {
        // Ensure only administrators can remove a stage.
        $this->assertAdmin($request->user());

        // Prevent removal when tasks are still linked to the stage.
        if ($stage->tasks()->exists()) {
            return response()->json([
                'message' => 'You cannot delete a stage while it still contains tasks.',
            ], 422);
        }

        // Clean up related attention records before deletion.
        DB::transaction(function () use ($stage) {
            $taskIds = $stage->tasks()->pluck('id');

            Attention::where('target_type', 'task')->whereIn('target_id', $taskIds)->delete();
            Attention::where('target_type', 'stage')->where('target_id', $stage->id)->delete();

            $stage->delete();
        });

        // Log the removal action for traceability.
        $this->logAction(
            $request->user(),
            'delete',
            'stage',
            $stage->id,
            "cases/{$stage->case_id}/stages",
            'Removed a stage and its tasks.'
        );

        // Return the updated set of stages after deletion.
        $stages = $this->loadStagesWithAttentions($stage->caseFile, $request->user());

        return response()->json([
            'stages' => $stages->map(fn (Stage $stageItem) => $this->formatStage($stageItem, $request->user()))->values(),
            'message' => 'Stage deleted successfully.',
        ]);
    }

    /**
     * Create a new task for the given stage.
     */
    public function storeTask(Request $request, Stage $stage): JsonResponse
    {
        // Guard the endpoint for administrator-only usage.
        $this->assertAdmin($request->user());

        // Validate the side parameter to allocate the task correctly.
        $validated = $request->validate([
            'side' => ['required', 'in:seller,buyer'],
        ]);

        // Persist the new task and notify stakeholders.
        $task = null;
        DB::transaction(function () use ($stage, $validated, &$task) {
            $task = Task::create([
                'stage_id' => $stage->id,
                'name' => 'New task',
                'side' => $validated['side'],
                'status' => 'new',
                'deadline' => null,
            ]);

            // Mark the task as new for clients and solicitors tied to the parent case.
            $this->createNewAttention($stage->caseFile, 'task', $task->id);
        });

        // Record the creation operation in the activity log.
        $this->logAction(
            $request->user(),
            'create',
            'task',
            $task->id,
            "cases/{$stage->case_id}/tasks",
            "Added a task for the {$task->side} side."
        );

        // Respond with the refreshed stage data set.
        $stages = $this->loadStagesWithAttentions($stage->caseFile, $request->user());

        return response()->json([
            'stages' => $stages->map(fn (Stage $stageItem) => $this->formatStage($stageItem, $request->user()))->values(),
            'message' => 'Task created successfully.',
        ], 201);
    }

    /**
     * Update task fields such as name, deadline, or status.
     */
    public function updateTask(Request $request, Task $task): JsonResponse
    {
        // Limit updates to administrators.
        $this->assertAdmin($request->user());

        // Validate provided attributes while allowing partial updates.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:300'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', 'in:new,progress,done'],
        ]);

        // Apply changes only when fields are supplied.
        if (! empty($validated)) {
            $task->update($validated);
        }

        // Log the update action for the audit history.
        $this->logAction(
            $request->user(),
            'update',
            'task',
            $task->id,
            "cases/{$task->stage->case_id}/tasks",
            'Updated task details or status.'
        );

        // Return the refreshed stage list reflecting progress adjustments.
        $stages = $this->loadStagesWithAttentions($task->stage->caseFile, $request->user());

        return response()->json([
            'stages' => $stages->map(fn (Stage $stageItem) => $this->formatStage($stageItem, $request->user()))->values(),
            'message' => 'Task updated successfully.',
        ]);
    }

    /**
     * Delete an existing task.
     */
    public function destroyTask(Request $request, Task $task): JsonResponse
    {
        // Restrict deletion to administrators only.
        $this->assertAdmin($request->user());

        // Remove attention entries and the task itself in a transaction.
        DB::transaction(function () use ($task) {
            Attention::where('target_type', 'task')->where('target_id', $task->id)->delete();
            $task->delete();
        });

        // Log the deletion for traceability.
        $this->logAction(
            $request->user(),
            'delete',
            'task',
            $task->id,
            "cases/{$task->stage->case_id}/tasks",
            'Deleted a task.'
        );

        // Send the current stage data back for UI refresh.
        $stages = $this->loadStagesWithAttentions($task->stage->caseFile, $request->user());

        return response()->json([
            'stages' => $stages->map(fn (Stage $stageItem) => $this->formatStage($stageItem, $request->user()))->values(),
            'message' => 'Task deleted successfully.',
        ]);
    }

    /**
     * Pull stages with ordered tasks and unread flags for the given user.
     */
    protected function loadStagesWithAttentions(CaseFile $caseFile, User $user)
    {
        return $caseFile->stages()
            ->with([
                'tasks' => fn ($query) => $query->orderBy('side')->orderBy('id'),
                'attentions' => fn ($query) => $query->where('user_id', $user->id)->where('type', 'new'),
                'tasks.attentions' => fn ($query) => $query->where('user_id', $user->id)->where('type', 'new'),
            ])
            ->get();
    }

    /**
     * Format a stage and its tasks for JSON or Blade consumption.
     */
    protected function formatStage(Stage $stage, User $user): array
    {
        // Calculate completion based on completed tasks.
        $totalTasks = $stage->tasks->count();
        $doneTasks = $stage->tasks->where('status', 'done')->count();
        $progress = $totalTasks === 0 ? 0 : (int) round(($doneTasks / $totalTasks) * 100);

        // Transform tasks to include display-friendly attributes.
        $tasks = $stage->tasks->map(function (Task $task) {
            $deadline = $task->deadline ? Carbon::parse($task->deadline) : null;
            $isOverdue = $deadline ? $deadline->isPast() : false;

            return [
                'id' => $task->id,
                'name' => $task->name,
                'side' => $task->side,
                'status' => $task->status,
                'deadline' => $deadline?->toDateString(),
                'deadline_display' => $deadline ? $deadline->format('d/m') : '00/00',
                'overdue' => $isOverdue,
                'is_new' => $task->attentions->isNotEmpty(),
            ];
        })->values();

        // Provide a consistent payload structure.
        return [
            'id' => $stage->id,
            'case_id' => $stage->case_id,
            'name' => $stage->name,
            'progress' => $progress,
            'is_new' => $stage->attentions->isNotEmpty(),
            'tasks' => $tasks,
        ];
    }

    /**
     * Remove "new" attention markers for the user's view on page load.
     */
    protected function clearNewMarkers(User $user, $stages): void
    {
        $stageIds = $stages->pluck('id');
        $taskIds = $stages->flatMap->tasks->pluck('id');

        Attention::where('user_id', $user->id)
            ->where('type', 'new')
            ->where(function ($query) use ($stageIds, $taskIds) {
                $query->where(function ($inner) use ($stageIds) {
                    $inner->where('target_type', 'stage')->whereIn('target_id', $stageIds);
                })->orWhere(function ($inner) use ($taskIds) {
                    $inner->where('target_type', 'task')->whereIn('target_id', $taskIds);
                });
            })
            ->delete();
    }

    /**
     * Build participant cards for the case header.
     */
    protected function buildParticipants(CaseFile $caseFile): array
    {
        $projectManager = User::where('role', 'admin')->orderBy('id')->first();

        return [
            [
                'label' => 'Project manager',
                'user' => $projectManager,
                'office' => null,
            ],
            [
                'label' => "Seller's solicitor",
                'user' => $caseFile->sellLegal,
                'office' => $caseFile->sellLegal?->legalProfile?->office,
            ],
            [
                'label' => "Buyer's solicitor",
                'user' => $caseFile->buyLegal,
                'office' => $caseFile->buyLegal?->legalProfile?->office,
            ],
        ];
    }

    /**
     * Determine chat permissions and labels for the viewer.
     */
    protected function buildChatProfile(CaseFile $caseFile, User $user): array
    {
        if ($user->role === 'admin') {
            return [
                'can_post' => true,
                'default_label' => 'manager',
                'labels' => [
                    ['value' => 'manager', 'label' => 'Manager'],
                    ['value' => 'buy', 'label' => 'Buy Side'],
                    ['value' => 'sell', 'label' => 'Sell Side'],
                ],
            ];
        }

        if ($user->role === 'legal') {
            $label = null;

            if ($caseFile->buy_legal_id === $user->id) {
                $label = 'buy';
            } elseif ($caseFile->sell_legal_id === $user->id) {
                $label = 'sell';
            }

            return [
                'can_post' => (bool) $label,
                'default_label' => $label,
                'labels' => $label ? [['value' => $label, 'label' => $label === 'buy' ? 'Buy Side' : 'Sell Side']] : [],
            ];
        }

        return [
            'can_post' => false,
            'default_label' => null,
            'labels' => [],
        ];
    }

    /**
     * Create a "new" attention record for each assigned client and solicitor.
     */
    protected function createNewAttention(CaseFile $caseFile, string $targetType, int $targetId): void
    {
        $recipientIds = array_filter([
            $caseFile->sell_client_id,
            $caseFile->buy_client_id,
            $caseFile->sell_legal_id,
            $caseFile->buy_legal_id,
        ]);

        foreach ($recipientIds as $userId) {
            Attention::firstOrCreate([
                'target_type' => $targetType,
                'target_id' => $targetId,
                'type' => 'new',
                'user_id' => $userId,
            ]);
        }
    }

}

<?php

namespace App\Http\Controllers;

use App\Models\Attention;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CaseManagerController extends Controller
{
    /**
     * Display the case manager table for administrators with filters and search.
     */
    public function index(Request $request): Response
    {
        // Ensure only administrators can access the full case manager.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can open the case manager.');
        }

        // Prepare filter parameters pulled from the query string with sensible defaults.
        $statusFilter = $request->query('status', 'all');
        $searchTerm = $request->query('q');
        $sortDirection = $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        // Build the case query with optional search across multiple attributes and relations.
        $query = CaseFile::query()
            ->with(['attentions', 'sellLegal', 'sellClient', 'buyLegal', 'buyClient']);

        // Apply search across textual columns and related user names when provided.
        if ($searchTerm) {
            $query->where(function ($inner) use ($searchTerm) {
                $inner->where('postal_code', 'like', "%{$searchTerm}%")
                    ->orWhere('property', 'like', "%{$searchTerm}%")
                    ->orWhere('headline', 'like', "%{$searchTerm}%")
                    ->orWhere('notes', 'like', "%{$searchTerm}%")
                    ->orWhere('status', 'like', "%{$searchTerm}%")
                    ->orWhere('id', (int) $searchTerm)
                    ->orWhereHas('sellLegal', function ($relation) use ($searchTerm) {
                        $relation->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('buyLegal', function ($relation) use ($searchTerm) {
                        $relation->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('sellClient', function ($relation) use ($searchTerm) {
                        $relation->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('buyClient', function ($relation) use ($searchTerm) {
                        $relation->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Apply status filters including a special attention view.
        if (in_array($statusFilter, ['new', 'progress', 'completed', 'cancelled'])) {
            $query->where('status', $statusFilter);
        } elseif ($statusFilter === 'attention') {
            $query->whereHas('attentions', function ($relation) {
                $relation->where('type', 'attention');
            });
        }

        // Order by status when requested while maintaining deterministic ordering.
        $query->orderBy('status', $sortDirection)->orderByDesc('created_at');

        // Paginate the results to twenty rows per page with bootstrap styling.
        $cases = $query->paginate(20)->appends($request->query());

        // Render the admin-facing table view.
        return response()->view('casemanager.index', [
            'cases' => $cases,
            'statusFilter' => $statusFilter,
            'searchTerm' => $searchTerm,
            'sortDirection' => $sortDirection,
        ]);
    }

    /**
     * Show the legal user's case list limited to their progress cases.
     */
    public function legalIndex(Request $request): Response
    {
        // Only legal users may open their slim case table.
        if ($request->user()->role !== 'legal') {
            abort(403, 'Only legal users can access this workspace.');
        }

        // Build the query restricting cases to those where the legal is assigned and in progress.
        $cases = CaseFile::query()
            ->where('status', 'progress')
            ->where(function ($inner) use ($request) {
                $inner->where('sell_legal_id', $request->user()->id)
                    ->orWhere('buy_legal_id', $request->user()->id);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        // Render the legal home table without edit controls.
        return response()->view('casemanager.legal', [
            'cases' => $cases,
        ]);
    }

    /**
     * Create a new case from the quick postal code input.
     */
    public function store(Request $request): RedirectResponse
    {
        // Restrict creation to administrators.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can create cases.');
        }

        // Validate the incoming postal code ensuring no spaces are present.
        $validated = $request->validate([
            'postal_code' => ['required', 'string', 'regex:/^\S+$/'],
        ]);

        // Generate a unique public link token between 8 and 16 characters.
        $token = Str::random(random_int(8, 16));
        while (CaseFile::where('public_link', $token)->exists()) {
            $token = Str::random(random_int(8, 16));
        }

        // Create the case with default status and placeholder fields.
        $case = CaseFile::create([
            'postal_code' => strtoupper($validated['postal_code']),
            'status' => 'new',
            'public_link' => $token,
        ]);

        // Log the creation action for audit visibility.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'target_type' => 'case',
            'target_id' => $case->id,
            'location' => 'case manager',
            'details' => 'Created a new case from quick add.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect to the edit screen for immediate configuration.
        return redirect()->route('casemanager.edit', $case);
    }

    /**
     * Display the editing interface for a specific case.
     */
    public function edit(CaseFile $caseFile, Request $request): Response
    {
        // Ensure only administrators can reach the editing interface.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can edit cases.');
        }

        // Retrieve recent activity logs tied to this case for display.
        $logs = DB::table('activity_logs')
            ->where('target_type', 'case')
            ->where('target_id', $caseFile->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Render the edit page with current case data and log history.
        return response()->view('casemanager.edit', [
            'case' => $caseFile,
            'logs' => $logs,
        ]);
    }

    /**
     * Update participant assignments for the provided case.
     */
    public function updateParticipants(Request $request, CaseFile $caseFile): RedirectResponse
    {
        // Only administrators may modify participants.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can update participants.');
        }

        // Validate participant fields ensuring they reference expected roles.
        $validated = $request->validate([
            'sell_legal_id' => ['nullable', 'exists:users,id'],
            'sell_client_id' => ['nullable', 'exists:users,id'],
            'buy_legal_id' => ['nullable', 'exists:users,id'],
            'buy_client_id' => ['nullable', 'exists:users,id'],
        ]);

        // Enforce role correctness for each participant slot.
        $this->assertRoleMatch($validated['sell_legal_id'] ?? null, 'legal');
        $this->assertRoleMatch($validated['buy_legal_id'] ?? null, 'legal');
        $this->assertRoleMatch($validated['sell_client_id'] ?? null, 'client');
        $this->assertRoleMatch($validated['buy_client_id'] ?? null, 'client');

        // Persist the participant updates on the case record.
        $caseFile->update($validated);

        // Log the participant update action for auditing.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'update participants',
            'target_type' => 'case',
            'target_id' => $caseFile->id,
            'location' => 'case edit',
            'details' => 'Updated participant assignments on the case.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect back with confirmation feedback.
        return redirect()->route('casemanager.edit', $caseFile)->with('status', 'Participants saved successfully.');
    }

    /**
     * Update the core case details including status, postal code, and notes.
     */
    public function updateDetails(Request $request, CaseFile $caseFile): RedirectResponse
    {
        // Restrict updates to administrators only.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can save case details.');
        }

        // Validate detail fields with required postal code and status options.
        $validated = $request->validate([
            'postal_code' => ['required', 'string', 'regex:/^\S+$/'],
            'property' => ['nullable', 'string'],
            'status' => ['required', 'in:new,progress,completed,cancelled'],
            'deadline' => ['nullable', 'date'],
            'headline' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        // Persist the detail updates onto the case record.
        $caseFile->update(array_merge($validated, [
            'postal_code' => strtoupper($validated['postal_code']),
        ]));

        // Record the change in the activity log for transparency.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'update details',
            'target_type' => 'case',
            'target_id' => $caseFile->id,
            'location' => 'case edit',
            'details' => 'Updated case core information and status.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return to the edit page with success feedback.
        return redirect()->route('casemanager.edit', $caseFile)->with('status', 'Case details saved successfully.');
    }

    /**
     * Toggle attention flags (attention, mail, doc, call) for the given case.
     */
    public function toggleAttention(Request $request, CaseFile $caseFile, string $type): RedirectResponse
    {
        // Only administrators can adjust notification flags.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can manage attention flags.');
        }

        // Validate the requested attention type.
        if (! in_array($type, ['attention', 'mail', 'doc', 'call'])) {
            abort(400, 'Unsupported attention type.');
        }

        // Determine whether a flag already exists to decide on deletion or creation.
        $existing = Attention::where('target_type', 'case')
            ->where('target_id', $caseFile->id)
            ->where('type', $type)
            ->first();

        // Toggle the record and capture the resulting action for logging.
        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            Attention::create([
                'target_type' => 'case',
                'target_id' => $caseFile->id,
                'type' => $type,
                'user_id' => $request->user()->id,
            ]);
            $action = 'added';
        }

        // Record the toggle event in the activity logs.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => "attention {$action}",
            'target_type' => 'case',
            'target_id' => $caseFile->id,
            'location' => 'case edit',
            'details' => "{$action} {$type} flag on case.",
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return to the edit page with context about the change.
        return redirect()->route('casemanager.edit', $caseFile)->with('status', 'Attention flags updated.');
    }

    /**
     * Provide lightweight user search for participant inputs.
     */
    public function searchUsers(Request $request): Response
    {
        // Restrict search to administrators for participant assignment.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can search users.');
        }

        // Validate incoming query parameters for safety.
        $validated = $request->validate([
            'q' => ['required', 'string'],
            'role' => ['nullable', 'in:legal,client'],
        ]);

        // Build the user search query filtering by name, email, or id.
        $query = User::query()->where(function ($inner) use ($validated) {
            $inner->where('name', 'like', "%{$validated['q']}%")
                ->orWhere('email', 'like', "%{$validated['q']}%")
                ->orWhere('id', (int) $validated['q']);
        });

        // Limit results to the requested role when provided.
        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        // Return a small list of matching users in JSON format.
        $users = $query->orderBy('name')->limit(10)->get(['id', 'name', 'role']);

        // Respond with the user collection for the client-side dropdown.
        return response()->json($users);
    }

    /**
     * Confirm that a user id matches an expected role when provided.
     */
    private function assertRoleMatch(?int $userId, string $expectedRole): void
    {
        // Skip checks when the field is intentionally empty.
        if (! $userId) {
            return;
        }

        // Retrieve the user and verify the role alignment.
        $user = User::find($userId);
        if (! $user || $user->role !== $expectedRole) {
            abort(422, "Selected user does not match required {$expectedRole} role.");
        }
    }
}

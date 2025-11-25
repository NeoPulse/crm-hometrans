<?php

namespace App\Http\Controllers;

use App\Models\Attention;
use App\Models\CaseFile;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    /**
     * Display the client directory with filters, search, and sorting controls.
     */
    public function index(Request $request): Response
    {
        // Only administrators are allowed to manage the client registry.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can access clients.');
        }

        // Capture filter and sorting inputs with safe defaults.
        $statusFilter = $request->query('status', 'active');
        $searchTerm = $request->query('q');
        $sort = $request->query('sort', 'registered');
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        // Build the base client query with profile and attention relationships.
        $query = User::query()
            ->select('users.*')
            ->selectRaw('(SELECT COUNT(*) FROM cases WHERE cases.sell_client_id = users.id OR cases.buy_client_id = users.id) as cases_count')
            ->with([
                'clientProfile',
                'attentions' => function ($relation) {
                    $relation->whereIn('type', ['call', 'doc']);
                },
                'sellCases:id,postal_code,sell_client_id,buy_client_id,status,created_at,deadline,headline',
                'buyCases:id,postal_code,sell_client_id,buy_client_id,status,created_at,deadline,headline',
            ])
            ->where('role', 'client');

        // Apply status filter toggles including attention-specific mode.
        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        } elseif ($statusFilter === 'attention') {
            $query->whereHas('attentions', function ($relation) {
                $relation->whereIn('type', ['call', 'doc']);
            });
        }

        // Enable broad search across user and client profile fields.
        if ($searchTerm) {
            $query->where(function ($inner) use ($searchTerm) {
                $inner->where('users.name', 'like', "%{$searchTerm}%")
                    ->orWhere('users.email', 'like', "%{$searchTerm}%")
                    ->orWhere('users.phone', 'like', "%{$searchTerm}%")
                    ->orWhere('users.address1', 'like', "%{$searchTerm}%")
                    ->orWhere('users.address2', 'like', "%{$searchTerm}%")
                    ->orWhere('users.headline', 'like', "%{$searchTerm}%")
                    ->orWhere('users.notes', 'like', "%{$searchTerm}%")
                    ->orWhere('users.id', (int) $searchTerm)
                    ->orWhereHas('clientProfile', function ($profile) use ($searchTerm) {
                        $profile->where('first_name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%")
                            ->orWhere('letter', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Apply requested sorting based on cases or registration date.
        if ($sort === 'cases') {
            $query->orderBy('cases_count', $direction)->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', $direction)->orderBy('id', 'desc');
        }

        // Paginate with twenty rows per page and preserve query string.
        $clients = $query->paginate(20)->appends($request->query());

        // Record the view action in the audit log.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'view',
            'target_type' => 'client',
            'target_id' => null,
            'location' => 'clients index',
            'details' => 'Viewed client directory with filters.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Render the client list view.
        return response()->view('clients.index', [
            'clients' => $clients,
            'statusFilter' => $statusFilter,
            'searchTerm' => $searchTerm,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Create a new client with default placeholders and redirect to the detail card.
     */
    public function store(Request $request): RedirectResponse
    {
        // Restrict creation to administrators.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can add clients.');
        }

        // Generate unique email and secure password for the new client.
        $randomNumber = random_int(100000, 999999);
        $email = "{$randomNumber}@test.com";
        while (User::where('email', $email)->exists()) {
            $randomNumber = random_int(100000, 999999);
            $email = "{$randomNumber}@test.com";
        }
        $password = Str::random(16);

        // Persist the user with default active status and client role.
        $user = User::create([
            'name' => 'New client',
            'email' => $email,
            'role' => 'client',
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        // Ensure an empty client profile exists for the card form.
        ClientProfile::create([
            'user_id' => $user->id,
        ]);

        // Log the creation event for auditing.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'target_type' => 'client',
            'target_id' => $user->id,
            'location' => 'clients index',
            'details' => 'Created a client via quick add.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect to the client's card for further editing.
        return redirect()->route('clients.edit', $user);
    }

    /**
     * Show the client card with profile data, related cases, and activity logs.
     */
    public function edit(User $client, Request $request): Response
    {
        // Confirm the client role and administrator access.
        if ($request->user()->role !== 'admin' || $client->role !== 'client') {
            abort(403, 'Only administrators can open client cards.');
        }

        // Load required relations and related data.
        $client->load([
            'clientProfile',
            'attentions' => function ($relation) {
                $relation->whereIn('type', ['call', 'doc']);
            },
            'sellCases.attentions',
            'buyCases.attentions',
        ]);

        // Retrieve available cases connected to the client.
        $relatedCases = CaseFile::query()
            ->with('attentions')
            ->where(function ($query) use ($client) {
                $query->where('sell_client_id', $client->id)
                    ->orWhere('buy_client_id', $client->id);
            })
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'cases_page');

        // Fetch recent activity logs tied to this user.
        $logs = DB::table('activity_logs')
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->where('target_type', 'user')
            ->where('target_id', $client->id)
            ->orderByDesc('activity_logs.created_at')
            ->limit(50)
            ->get(['activity_logs.*', 'users.name as user_name']);

        // Log the view of the client card.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'view',
            'target_type' => 'client',
            'target_id' => $client->id,
            'location' => 'client card',
            'details' => 'Opened the client card.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Render the client detail view with related datasets.
        return response()->view('clients.edit', [
            'client' => $client,
            'relatedCases' => $relatedCases,
            'logs' => $logs,
        ]);
    }

    /**
     * Persist updates to the client and profile data.
     */
    public function update(Request $request, User $client): RedirectResponse
    {
        // Enforce administrator-only modification and validate client role.
        if ($request->user()->role !== 'admin' || $client->role !== 'client') {
            abort(403, 'Only administrators can update clients.');
        }

        // Validate incoming payload with required email and optional password.
        $validated = $request->validate([
            'is_active' => ['required', 'in:0,1'],
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'email' => ['required', 'email', 'unique:users,email,' . $client->id],
            'phone' => ['nullable', 'string'],
            'address1' => ['nullable', 'string'],
            'address2' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
            'headline' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'letter' => ['nullable', 'string'],
        ]);

        // Update the user record with core fields and optional password replacement.
        $client->fill([
            'is_active' => (bool) $validated['is_active'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'address2' => $validated['address2'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'name' => trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? '')) ?: $client->name,
        ]);

        if (! empty($validated['password'])) {
            $client->password = Hash::make($validated['password']);
        }

        $client->save();

        // Upsert the client profile with supplied fields.
        ClientProfile::updateOrCreate(
            ['user_id' => $client->id],
            [
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'letter' => $validated['letter'] ?? null,
            ]
        );

        // Log the update action for traceability.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'update',
            'target_type' => 'client',
            'target_id' => $client->id,
            'location' => 'client card',
            'details' => 'Saved client profile changes.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return to the card with success feedback.
        return redirect()->route('clients.edit', $client)->with('status', 'Client saved successfully.');
    }

    /**
     * Delete a client account and cascade their profile.
     */
    public function destroy(Request $request, User $client): RedirectResponse
    {
        // Restrict deletion to administrators and confirm client role.
        if ($request->user()->role !== 'admin' || $client->role !== 'client') {
            abort(403, 'Only administrators can delete clients.');
        }

        // Remove the client and associated records.
        $clientId = $client->id;
        $client->delete();

        // Record the deletion in the audit trail.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'delete',
            'target_type' => 'client',
            'target_id' => $clientId,
            'location' => 'client card',
            'details' => 'Deleted client account.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return to the client list after removal.
        return redirect()->route('clients.index')->with('status', 'Client deleted successfully.');
    }

    /**
     * Toggle attention markers (call/doc) for the client card icons.
     */
    public function toggleAttention(Request $request, User $client, string $type): RedirectResponse
    {
        // Ensure administrative control and valid role.
        if ($request->user()->role !== 'admin' || $client->role !== 'client') {
            abort(403, 'Only administrators can manage client attentions.');
        }

        // Validate supported attention types.
        if (! in_array($type, ['call', 'doc'])) {
            abort(400, 'Unsupported attention type.');
        }

        // Find existing flag to decide on toggle behavior.
        $existing = Attention::where('target_type', 'user')
            ->where('target_id', $client->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            Attention::create([
                'target_type' => 'user',
                'target_id' => $client->id,
                'type' => $type,
                'user_id' => $request->user()->id,
            ]);
            $action = 'added';
        }

        // Log the toggle event.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => "attention {$action}",
            'target_type' => 'client',
            'target_id' => $client->id,
            'location' => 'client card',
            'details' => "{$action} {$type} flag on client.",
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect back to the card with status feedback.
        return redirect()->route('clients.edit', $client)->with('status', 'Attention updated.');
    }
}

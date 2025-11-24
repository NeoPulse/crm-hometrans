<?php

namespace App\Http\Controllers;

use App\Models\Attention;
use App\Models\CaseFile;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    /**
     * Display filtered list of clients.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->input('status', 'active');
        $sort = $request->input('sort', 'registered_desc');
        $search = trim((string) $request->input('search'));

        $clientsQuery = User::query()
            ->where('role', 'client')
            ->with(['clientProfile', 'attentions', 'sellCases', 'buyCases'])
            ->withCount(['sellCases', 'buyCases']);

        if ($status === 'active') {
            $clientsQuery->where('is_active', true);
        } elseif ($status === 'nonactive') {
            $clientsQuery->where('is_active', false);
        } elseif ($status === 'attention') {
            $clientsQuery->whereHas('attentions', function ($q) {
                $q->whereIn('type', ['call', 'doc'])->where('target_type', 'user');
            });
        }

        if ($search !== '') {
            $clientsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('headline', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('address1', 'like', "%{$search}%")
                    ->orWhere('address2', 'like', "%{$search}%")
                    ->orWhereHas('clientProfile', function ($cp) use ($search) {
                        $cp->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($sort === 'cases') {
            $clientsQuery->orderByRaw('(sell_cases_count + buy_cases_count) desc');
        } elseif ($sort === 'registered') {
            $clientsQuery->orderBy('created_at');
        } else {
            $clientsQuery->orderByDesc('created_at');
        }

        $clients = $clientsQuery->paginate(20)->withQueryString();

        $this->logActivity('client_list_viewed', null, 'Admin viewed client list');

        return view('clients.index', compact('clients', 'status', 'sort', 'search'));
    }

    /**
     * Store a minimal new client and redirect to the card.
     */
    public function store()
    {
        $this->authorizeAdmin();

        $email = $this->buildRandomEmail();
        $password = Str::random(12);

        $client = User::create([
            'name' => 'New Client',
            'email' => $email,
            'role' => 'client',
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        ClientProfile::create([
            'user_id' => $client->id,
            'first_name' => 'New',
            'last_name' => 'Client',
        ]);

        $this->logActivity('client_created', $client, 'Client created via quick add');

        return redirect()->route('clients.show', $client);
    }

    /**
     * Show the client card.
     */
    public function show(User $client)
    {
        $this->ensureClient($client);

        $client->load(['clientProfile', 'attentions', 'sellCases', 'buyCases']);

        $cases = CaseFile::where('sell_client_id', $client->id)
            ->orWhere('buy_client_id', $client->id)
            ->orderBy('deadline')
            ->paginate(20, ['*'], 'cases_page');

        $logs = $client->activityLogs()->latest()->paginate(20, ['*'], 'logs_page');

        $this->logActivity('client_viewed', $client, 'Client card opened');

        return view('clients.show', compact('client', 'cases', 'logs'));
    }

    /**
     * Update the client profile and account data.
     */
    public function update(Request $request, User $client)
    {
        $this->ensureClient($client);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $client->id,
            'phone' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'headline' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'password' => 'nullable|string|min:8',
        ]);

        $client->fill([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'is_active' => (bool) $validated['is_active'],
            'phone' => $validated['phone'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'address2' => $validated['address2'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $client->password = Hash::make($validated['password']);
        }

        $client->save();

        $client->clientProfile()->updateOrCreate(
            ['user_id' => $client->id],
            [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ]
        );

        $this->logActivity('client_updated', $client, 'Client data saved');

        return back()->with('status', 'Client saved');
    }

    /**
     * Delete a client completely.
     */
    public function destroy(User $client)
    {
        $this->ensureClient($client);

        $client->delete();

        $this->logActivity('client_deleted', $client, 'Client removed');

        return redirect()->route('clients.index')->with('status', 'Client deleted');
    }

    /**
     * Toggle an attention marker for a client.
     */
    public function toggleAttention(User $client, string $type)
    {
        $this->ensureClient($client);

        if (! in_array($type, ['call', 'doc'], true)) {
            abort(404);
        }

        $existing = $client->attentions()
            ->where('target_type', 'user')
            ->where('type', $type)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->logActivity('attention_removed', $client, ucfirst($type) . ' attention removed');
        } else {
            Attention::create([
                'target_type' => 'user',
                'target_id' => $client->id,
                'type' => $type,
                'user_id' => Auth::id(),
            ]);
            $this->logActivity('attention_added', $client, ucfirst($type) . ' attention added');
        }

        return back()->with('status', 'Attention updated');
    }

    /**
     * Ensure only admins can proceed and the user is a client.
     */
    protected function ensureClient(User $client): void
    {
        $this->authorizeAdmin();

        if ($client->role !== 'client') {
            abort(404);
        }
    }

    /**
     * Basic admin gate reused across the controller.
     */
    protected function authorizeAdmin(): void
    {
        if (Auth::user()?->role !== 'admin') {
            abort(403);
        }
    }

    /**
     * Build a random email for quick client creation.
     */
    protected function buildRandomEmail(): string
    {
        do {
            $email = 'client_' . random_int(10000, 99999) . '@test.com';
        } while (User::where('email', $email)->exists());

        return $email;
    }
}

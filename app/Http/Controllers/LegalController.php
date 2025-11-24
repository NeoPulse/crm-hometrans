<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\LegalProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LegalController extends Controller
{
    /**
     * Display filtered list of legals.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->input('status', 'active');
        $sort = $request->input('sort', 'person');
        $search = trim((string) $request->input('search'));

        $legalsQuery = User::query()
            ->where('role', 'legal')
            ->with(['legalProfile', 'sellLegalCases', 'buyLegalCases'])
            ->withCount(['sellLegalCases', 'buyLegalCases']);

        if ($status === 'active') {
            $legalsQuery->where('is_active', true);
        } elseif ($status === 'nonactive') {
            $legalsQuery->where('is_active', false);
        }

        if ($search !== '') {
            $legalsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('headline', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('address1', 'like', "%{$search}%")
                    ->orWhere('address2', 'like', "%{$search}%")
                    ->orWhereHas('legalProfile', function ($lp) use ($search) {
                        $lp->where('company', 'like', "%{$search}%")
                            ->orWhere('website', 'like', "%{$search}%")
                            ->orWhere('locality', 'like', "%{$search}%")
                            ->orWhere('person', 'like', "%{$search}%")
                            ->orWhere('office', 'like', "%{$search}%");
                    });
            });
        }

        if ($sort === 'cases') {
            $legalsQuery->orderByRaw('(sell_legal_cases_count + buy_legal_cases_count) desc');
        } elseif ($sort === 'company') {
            $legalsQuery->orderByRaw("COALESCE((select company from legal_profiles where legal_profiles.user_id = users.id limit 1), '')");
        } elseif ($sort === 'locality') {
            $legalsQuery->orderByRaw("COALESCE((select locality from legal_profiles where legal_profiles.user_id = users.id limit 1), '')");
        } else { // person default
            $legalsQuery->orderByRaw("COALESCE((select person from legal_profiles where legal_profiles.user_id = users.id limit 1), '')");
        }

        $legals = $legalsQuery->paginate(20)->withQueryString();

        $this->logActivity('legal_list_viewed', null, 'Admin viewed legal list');

        return view('legals.index', compact('legals', 'status', 'sort', 'search'));
    }

    /**
     * Create a new legal and redirect to the card.
     */
    public function store()
    {
        $this->authorizeAdmin();

        $email = $this->buildRandomEmail();
        $password = Str::random(14);

        $legal = User::create([
            'name' => 'New legal',
            'email' => $email,
            'role' => 'legal',
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        LegalProfile::create([
            'user_id' => $legal->id,
            'person' => 'New legal',
        ]);

        $this->logActivity('legal_created', $legal, 'Legal created via quick add');

        return redirect()->route('legals.show', $legal);
    }

    /**
     * Show the legal card.
     */
    public function show(User $legal)
    {
        $this->ensureLegal($legal);

        $legal->load(['legalProfile', 'sellLegalCases', 'buyLegalCases', 'activityLogs']);

        $cases = CaseFile::where('sell_legal_id', $legal->id)
            ->orWhere('buy_legal_id', $legal->id)
            ->orderBy('deadline')
            ->paginate(20, ['*'], 'cases_page');

        $logs = $legal->activityLogs()->latest()->paginate(20, ['*'], 'logs_page');

        $generated = session('generated_credentials');

        $this->logActivity('legal_viewed', $legal, 'Legal card opened');

        return view('legals.show', compact('legal', 'cases', 'logs', 'generated'));
    }

    /**
     * Update the legal profile and account data.
     */
    public function update(Request $request, User $legal)
    {
        $this->ensureLegal($legal);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'person' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'headline' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'locality' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $legal->id,
            'office' => 'nullable|string|max:255',
        ]);

        $legal->fill([
            'name' => $validated['person'],
            'email' => $validated['email'],
            'is_active' => (bool) $validated['is_active'],
            'phone' => $validated['phone'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'address2' => $validated['address2'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $legal->save();

        $legal->legalProfile()->updateOrCreate(
            ['user_id' => $legal->id],
            [
                'person' => $validated['person'],
                'company' => $validated['company'] ?? null,
                'website' => $validated['website'] ?? null,
                'locality' => $validated['locality'] ?? null,
                'office' => $validated['office'] ?? null,
            ]
        );

        $this->logActivity('legal_updated', $legal, 'Legal data saved');

        return back()->with('status', 'Legal saved');
    }

    /**
     * Generate and set a new password for the legal.
     */
    public function generatePassword(User $legal)
    {
        $this->ensureLegal($legal);

        $password = Str::password(14, symbols: false);
        $legal->password = Hash::make($password);
        $legal->save();

        $credentials = [
            'registration_url' => route('login'),
            'login' => $legal->email,
            'password' => $password,
        ];

        $this->logActivity('legal_password_reset', $legal, 'Legal password regenerated');

        return redirect()
            ->route('legals.show', $legal)
            ->with('generated_credentials', $credentials);
    }

    /**
     * Ensure only admins can proceed and the user is a legal.
     */
    protected function ensureLegal(User $legal): void
    {
        $this->authorizeAdmin();

        if ($legal->role !== 'legal') {
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
     * Build a random email for quick legal creation.
     */
    protected function buildRandomEmail(): string
    {
        do {
            $email = 'legal_' . random_int(10000, 99999) . '@test.com';
        } while (User::where('email', $email)->exists());

        return $email;
    }
}

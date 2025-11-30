<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\LegalProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Response;

class LegalController extends Controller
{
    /**
     * Display the solicitor directory with filtering, search, and sorting controls.
     */
    public function index(Request $request): Response
    {
        // Restrict access to administrators to protect solicitor data.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can access legals.');
        }

        // Read query parameters to manage status filter, search term, and sorting order.
        $statusFilter = $request->query('status', 'active');
        $searchTerm = $request->query('q');
        $sort = $request->query('sort', 'cases');
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        // Prepare the base query joining solicitor profiles and case counts for sorting.
        $query = User::query()
            ->select('users.*')
            ->selectRaw('(SELECT COUNT(*) FROM cases WHERE cases.sell_legal_id = users.id OR cases.buy_legal_id = users.id) as cases_count')
            ->leftJoin('legal_profiles', 'legal_profiles.user_id', '=', 'users.id')
            ->with([
                'legalProfile',
                'sellLegalCases:id,postal_code,sell_legal_id,buy_legal_id,status,created_at,deadline,headline',
                'buyLegalCases:id,postal_code,sell_legal_id,buy_legal_id,status,created_at,deadline,headline',
            ])
            ->where('role', 'legal');

        // Apply status-specific filters for active, inactive, or full listings.
        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        // Enable full-text style search across user and profile fields.
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
                    ->orWhere('legal_profiles.company', 'like', "%{$searchTerm}%")
                    ->orWhere('legal_profiles.website', 'like', "%{$searchTerm}%")
                    ->orWhere('legal_profiles.locality', 'like', "%{$searchTerm}%")
                    ->orWhere('legal_profiles.person', 'like', "%{$searchTerm}%")
                    ->orWhere('legal_profiles.office', 'like', "%{$searchTerm}%");
            });
        }

        // Apply the requested sort column while keeping a deterministic fallback.
        if ($sort === 'person') {
            $query->orderBy('legal_profiles.person', $direction)->orderBy('users.id', 'desc');
        } elseif ($sort === 'company') {
            $query->orderBy('legal_profiles.company', $direction)->orderBy('users.id', 'desc');
        } elseif ($sort === 'locality') {
            $query->orderBy('legal_profiles.locality', $direction)->orderBy('users.id', 'desc');
        } else {
            $query->orderBy('cases_count', $direction)->orderBy('users.created_at', 'desc');
        }

        // Paginate results to twenty per page and preserve current query state.
        $legals = $query->paginate(20)->appends($request->query());

        // Record the directory view in the audit trail.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'view',
            'target_type' => 'legal',
            'target_id' => null,
            'location' => 'legals index',
            'details' => 'Viewed legal directory with filters.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Render the solicitor list page with filters and current data.
        return response()->view('legals.index', [
            'legals' => $legals,
            'statusFilter' => $statusFilter,
            'searchTerm' => $searchTerm,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Quickly add a new solicitor record and forward to the card view.
     */
    public function store(Request $request): RedirectResponse
    {
        // Ensure only administrators can create solicitor accounts.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can add legals.');
        }

        // Generate a unique email and secure password for the new solicitor.
        $randomNumber = random_int(100000, 999999);
        $email = "{$randomNumber}@test.com";
        while (User::where('email', $email)->exists()) {
            $randomNumber = random_int(100000, 999999);
            $email = "{$randomNumber}@test.com";
        }
        $password = Str::random(20);

        // Persist the user as inactive until explicitly activated by staff.
        $user = User::create([
            'name' => 'New legal',
            'email' => $email,
            'role' => 'legal',
            'is_active' => false,
            'password' => Hash::make($password),
        ]);

        // Attach an empty legal profile with a placeholder person name.
        LegalProfile::create([
            'user_id' => $user->id,
            'person' => 'New legal',
        ]);

        // Log the creation event with traceability details.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'target_type' => 'legal',
            'target_id' => $user->id,
            'location' => 'legals index',
            'details' => 'Created a legal via quick add.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect to the solicitor card for further editing.
        return redirect()->route('legals.edit', $user);
    }

    /**
     * Show the solicitor card with profile information, related cases, and audit logs.
     */
    public function edit(User $legal, Request $request): Response
    {
        // Restrict access to administrator users and confirm the legal role.
        if ($request->user()->role !== 'admin' || $legal->role !== 'legal') {
            abort(403, 'Only administrators can open legal cards.');
        }

        // Load profile and attention data along with associated cases for display.
        $legal->load([
            'legalProfile',
        ]);

        // Retrieve cases linked to this solicitor on either side of the transaction.
        $relatedCases = CaseFile::query()
            ->with('attentions')
            ->where(function ($query) use ($legal) {
                $query->where('sell_legal_id', $legal->id)
                    ->orWhere('buy_legal_id', $legal->id);
            })
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'cases_page');

        // Gather recent activity logs for this solicitor.
        $logs = DB::table('activity_logs')
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->where('target_type', 'legal')
            ->where('target_id', $legal->id)
            ->orderByDesc('activity_logs.created_at')
            ->limit(50)
            ->get(['activity_logs.*', 'users.name as user_name']);

        // Log the view action for audit purposes.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'view',
            'target_type' => 'legal',
            'target_id' => $legal->id,
            'location' => 'legal card',
            'details' => 'Opened the legal card.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Render the solicitor detail view.
        return response()->view('legals.edit', [
            'legal' => $legal,
            'relatedCases' => $relatedCases,
            'logs' => $logs,
            'generatedPassword' => session('generated_password'),
        ]);
    }

    /**
     * Persist changes to solicitor and profile data.
     */
    public function update(Request $request, User $legal): RedirectResponse
    {
        // Enforce administrator-only modification and role verification.
        if ($request->user()->role !== 'admin' || $legal->role !== 'legal') {
            abort(403, 'Only administrators can update legals.');
        }

        // Validate inputs including required activation flag, person, and email fields.
        $validated = $request->validate([
            'is_active' => ['required', 'in:0,1'],
            'company' => ['nullable', 'string'],
            'website' => ['nullable', 'string'],
            'locality' => ['nullable', 'string'],
            'person' => ['required', 'string'],
            'office' => ['nullable', 'string'],
            'email' => ['required', 'email', 'unique:users,email,' . $legal->id],
            'phone' => ['nullable', 'string'],
            'address1' => ['nullable', 'string'],
            'address2' => ['nullable', 'string'],
            'headline' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        // Update the user core fields and mirror the person name into the name column.
        $legal->fill([
            'is_active' => (bool) $validated['is_active'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'address2' => $validated['address2'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'name' => $validated['person'],
        ]);
        $legal->save();

        // Process an uploaded avatar and convert it into a compact JPEG stored publicly.
        if ($request->hasFile('avatar')) {
            // Use Intervention Image with the GD driver to resize the avatar consistently.
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('avatar'));

            // Crop to a 400x400 square and encode as JPEG to balance quality and size.
            $image->cover(400, 400);
            $imageStream = (string) $image->toJpeg(85);

            // Persist the processed file to the public storage disk with a predictable path.
            $filename = 'legal-' . $legal->id . '.jpg';
            $storagePath = 'avatars/' . $filename;
            Storage::disk('public')->put($storagePath, $imageStream);

            // Remove any older avatar stored for this user to avoid orphaned files.
            $existingFilename = $legal->avatar_path ? basename($legal->avatar_path) : null;
            if ($existingFilename && $existingFilename !== $filename) {
                Storage::disk('public')->delete('avatars/' . $existingFilename);
            }

            // Persist the new avatar path to the user record for display.
            $legal->avatar_path = $filename;
            $legal->save();
        }

        // Upsert the legal profile with the provided solicitor details.
        LegalProfile::updateOrCreate(
            ['user_id' => $legal->id],
            [
                'company' => $validated['company'] ?? null,
                'website' => $validated['website'] ?? null,
                'locality' => $validated['locality'] ?? null,
                'person' => $validated['person'],
                'office' => $validated['office'] ?? null,
            ]
        );

        // Record the update in the activity log.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'update',
            'target_type' => 'legal',
            'target_id' => $legal->id,
            'location' => 'legal card',
            'details' => 'Saved legal profile changes.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return to the card with confirmation.
        return redirect()->route('legals.edit', $legal)->with('status', 'Legal saved successfully.');
    }

    /**
     * Generate a fresh password for the solicitor and surface credentials for admins.
     */
    public function generatePassword(Request $request, User $legal): RedirectResponse
    {
        // Only administrators can reset passwords for solicitors.
        if ($request->user()->role !== 'admin' || $legal->role !== 'legal') {
            abort(403, 'Only administrators can reset legal passwords.');
        }

        // Create and save a new secure password.
        $newPassword = Str::random(20);
        $legal->password = Hash::make($newPassword);
        $legal->save();

        // Log the credential reset for traceability.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'password_reset',
            'target_type' => 'legal',
            'target_id' => $legal->id,
            'location' => 'legal card',
            'details' => 'Generated a new password for the legal user.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect back with the raw password for admin communication.
        return redirect()
            ->route('legals.edit', $legal)
            ->with('generated_password', $newPassword)
            ->with('status', 'New password generated successfully.');
    }
}

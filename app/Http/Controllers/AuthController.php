<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Display the login form for guest users.
     */
    public function showLogin(): Response
    {
        // Render the Bootstrap-based login page for unauthenticated visitors.
        return response()->view('auth.login');
    }

    /**
     * Handle authentication by validating credentials and enforcing role rules.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        // Validate incoming credentials before attempting authentication.
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Retrieve the user record to enforce activation and role restrictions.
        $user = DB::table('users')->where('email', $validated['email'])->first();

        // Block sign-in attempts when the account is missing or inactive.
        if (! $user || ! $user->is_active) {
            return back()->withErrors([
                'email' => 'Invalid credentials or inactive account.',
            ])->withInput($request->only('email'));
        }

        // Verify the provided password against the stored hash for the user.
        if (! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Invalid credentials or inactive account.',
            ])->withInput($request->only('email'));
        }

        // Precompute the intended redirect location and enforce in-progress case membership for legal and client roles.
        $redirectTarget = route('home');

        if (in_array($user->role, ['legal', 'client'], true)) {
            // Fetch all cases where the authenticated user participates and the case is currently in progress.
            $progressCases = CaseFile::query()
                ->where('status', 'progress')
                ->where(function ($query) use ($user) {
                    if ($user->role === 'legal') {
                        $query->where('sell_legal_id', $user->id)
                            ->orWhere('buy_legal_id', $user->id);
                    } else {
                        $query->where('sell_client_id', $user->id)
                            ->orWhere('buy_client_id', $user->id);
                    }
                })
                ->get();

            // Block authentication when the legal or client user is not assigned to any in-progress case.
            if ($progressCases->isEmpty()) {
                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'action' => 'login_denied',
                    'target_type' => 'auth',
                    'target_id' => $user->id,
                    'location' => 'login form',
                    'details' => 'User attempted to sign in without any in-progress case assignments.',
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return back()->withErrors([
                    'email' => 'You do not have any in-progress cases assigned.',
                ])->withInput($request->only('email'));
            }

            // Determine the redirect target depending on whether the user participates in one or multiple in-progress cases.
            $redirectTarget = $progressCases->count() === 1
                ? route('cases.show', ['caseFile' => $progressCases->first()->id])
                : route('casemanager.list');
        }

        // Attempt to authenticate the user with the web guard and regenerate the session.
        if (Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            $request->session()->regenerate();

            // Record the login time for auditing purposes.
            DB::table('users')->where('id', $user->id)->update([
                'last_login_at' => now(),
                'remember_token' => Str::random(10),
                'updated_at' => now(),
            ]);

            // Log the successful login attempt in the activity log table.
            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'action' => 'login',
                'target_type' => 'auth',
                'target_id' => $user->id,
                'location' => 'login form',
                'details' => 'User signed in with email and password.',
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->intended($redirectTarget);
        }

        // Fallback error in case authentication fails unexpectedly.
        return back()->withErrors([
            'email' => 'Authentication failed. Please try again.',
        ])->withInput($request->only('email'));
    }

    /**
     * Destroy the session and sign the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        // Capture the current user before the logout to store audit details.
        $user = $request->user();

        // Invalidate the authenticated session and rotate the CSRF token.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

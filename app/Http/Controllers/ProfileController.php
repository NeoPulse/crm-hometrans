<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /**
     * Display the profile screen for administrators and legal users only.
     */
    public function show(Request $request): Response
    {
        // Abort early when the authenticated user does not have permission for the profile area.
        if (! in_array($request->user()->role, ['admin', 'legal'], true)) {
            abort(403, 'Only administrators and legal users can access the profile area.');
        }

        // Log the profile view to the activity log for auditing purposes.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'view',
            'target_type' => 'profile',
            'target_id' => $request->user()->id,
            'location' => 'profile page',
            'details' => 'Opened the profile page to review account options.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Render the profile form that allows password changes for the current user.
        return response()->view('profile.index');
    }

    /**
     * Update the password for the authenticated administrator or legal user.
     */
    public function update(Request $request): RedirectResponse
    {
        // Ensure only administrators and legal users can process password updates.
        if (! in_array($request->user()->role, ['admin', 'legal'], true)) {
            abort(403, 'Only administrators and legal users can update their profile.');
        }

        // Validate the new password with confirmation to avoid accidental typos.
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Persist the hashed password to the users table for the current account.
        DB::table('users')
            ->where('id', $request->user()->id)
            ->update([
                'password' => Hash::make($validated['password']),
                'updated_at' => now(),
            ]);

        // Record the password change in the activity log with contextual details.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'update',
            'target_type' => 'profile',
            'target_id' => $request->user()->id,
            'location' => 'profile page',
            'details' => 'Updated account password through the profile form.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect back to the profile page with a success confirmation message.
        return redirect()->route('profile.show')->with('status', 'Password updated successfully.');
    }
}

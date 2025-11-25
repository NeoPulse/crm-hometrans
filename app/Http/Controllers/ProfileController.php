<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
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
        return response()->view('profile.index', [
            'user' => $request->user(),
        ]);
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

        // Validate optional password and avatar updates to keep inputs safe.
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        // Track whether any changes occurred to tailor the feedback message.
        $changes = [];

        // Persist the hashed password to the users table for the current account when provided.
        if (! empty($validated['password'])) {
            DB::table('users')
                ->where('id', $request->user()->id)
                ->update([
                    'password' => Hash::make($validated['password']),
                    'updated_at' => now(),
                ]);

            $changes[] = 'password';
        }

        // Process avatar uploads with Intervention Image for consistent sizing and format.
        if ($request->hasFile('avatar')) {
            // Instantiate the image manager with the GD driver to avoid external dependencies.
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('avatar'));

            // Resize and crop to a square 400x400 avatar while converting to JPEG to reduce size.
            $image->cover(400, 400);
            $imageStream = (string) $image->toJpeg(85);

            // Build a deterministic filename per user to avoid clutter and replace previous images.
            $filename = 'avatars/user-' . $request->user()->id . '.jpg';
            Storage::disk('public')->put($filename, $imageStream);

            // Remove an old avatar file when it differs from the new one to conserve space.
            if ($request->user()->avatar_path && $request->user()->avatar_path !== 'storage/' . $filename) {
                Storage::disk('public')->delete(str_replace('storage/', '', $request->user()->avatar_path));
            }

            // Update the user record with the publicly accessible storage path.
            DB::table('users')
                ->where('id', $request->user()->id)
                ->update([
                    'avatar_path' => 'storage/' . $filename,
                    'updated_at' => now(),
                ]);

            $changes[] = 'avatar';
        }

        // Record the profile update in the activity log with contextual details.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'update',
            'target_type' => 'profile',
            'target_id' => $request->user()->id,
            'location' => 'profile page',
            'details' => $changes ? 'Updated profile fields: ' . implode(', ', $changes) : 'Opened profile with no changes.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect back to the profile page with a tailored success message.
        $statusMessage = $changes ? 'Profile updated successfully.' : 'No changes were applied to the profile.';

        return redirect()->route('profile.show')->with('status', $statusMessage);
    }
}

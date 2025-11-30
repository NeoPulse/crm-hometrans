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

        // Render the profile form that allows password changes for the current user.
        return response()->view('profile.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Process password updates for the current administrator or legal user.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        // Ensure only administrators and legal users can process password updates.
        if (! in_array($request->user()->role, ['admin', 'legal'], true)) {
            abort(403, 'Only administrators and legal users can update their profile.');
        }

        // Validate the new password separately from avatar uploads to keep intent clear.
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

        // Log the password change for audit visibility.
        $this->logProfileAction(
            $request,
            'update',
            'Updated password from the profile page.'
        );

        return redirect()->route('profile.show')->with('status_password', 'Password updated successfully.');
    }

    /**
     * Handle avatar uploads independently from password updates.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        // Ensure only administrators and legal users can process avatar updates.
        if (! in_array($request->user()->role, ['admin', 'legal'], true)) {
            abort(403, 'Only administrators and legal users can update their profile.');
        }

        // Validate avatar uploads separately to simplify form handling.
        $request->validate([
            'avatar' => ['required', 'image', 'max:10000'],
        ]);

        // Instantiate the image manager with the GD driver to avoid external dependencies.
        $manager = new ImageManager(new Driver());
        $image = $manager->read($request->file('avatar'));

        // Resize and crop to a square 400x400 avatar while converting to JPEG to reduce size.
        $image->cover(400, 400);
        $imageStream = (string) $image->toJpeg(85);

        // Build a deterministic filename per user to avoid clutter and replace previous images.
        $filename = 'user-' . $request->user()->id . '.jpg';
        $storagePath = 'avatars/' . $filename;
        Storage::disk('public')->put($storagePath, $imageStream);

        // Remove an old avatar file when it differs from the new one to conserve space.
        $existingFilename = $request->user()->avatar_path ? basename($request->user()->avatar_path) : null;
        if ($existingFilename && $existingFilename !== $filename) {
            Storage::disk('public')->delete('avatars/' . $existingFilename);
        }

        // Update the user record with the publicly accessible storage path.
        DB::table('users')
            ->where('id', $request->user()->id)
            ->update([
                'avatar_path' => $filename,
                'updated_at' => now(),
            ]);

        // Log the avatar change alongside the page action for traceability.
        $this->logProfileAction(
            $request,
            'update',
            'Updated avatar from the profile page.'
        );

        return redirect()->route('profile.show')->with('status_avatar', 'Avatar updated successfully.');
    }

    /**
     * Record profile related actions in the activity log for auditing.
     */
    protected function logProfileAction(Request $request, string $action, string $details): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'profile',
            'target_id' => $request->user()->id,
            'location' => 'profile page',
            'details' => $details,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

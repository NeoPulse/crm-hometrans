<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Seed base users for admin, legal, and client roles.
     */
    public function run(): void
    {
        // Prepare timestamps for consistent seeding records.
        $timestamp = Carbon::now();

        // Insert three users covering admin, legal, and client roles.
        DB::table('users')->insert([
            [
                'name' => 'Alice Admin',
                'email' => 'admin@admin.com',
                'role' => 'admin',
                'is_active' => true,
                'phone' => '+44 7700 900001',
                'password' => Hash::make('admin'),
                'address1' => '100 High Street',
                'address2' => 'London',
                'headline' => 'Operations lead overseeing all cases.',
                'notes' => 'Primary administrator account for demonstrations.',
                'avatar_path' => null,
                'email_verified_at' => $timestamp,
                'verification_token' => Str::random(32),
                'remember_token' => Str::random(10),
                'last_login_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Laura Legal',
                'email' => 'legal@example.com',
                'role' => 'legal',
                'is_active' => true,
                'phone' => '+44 7700 900002',
                'password' => Hash::make('LegalPass123!'),
                'address1' => '200 Fleet Street',
                'address2' => 'London',
                'headline' => 'Expert solicitor for residential conveyancing.',
                'notes' => 'Handles both seller and buyer sides for demo cases.',
                'avatar_path' => null,
                'email_verified_at' => $timestamp,
                'verification_token' => Str::random(32),
                'remember_token' => Str::random(10),
                'last_login_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Carl Client',
                'email' => 'client@example.com',
                'role' => 'client',
                'is_active' => true,
                'phone' => '+44 7700 900003',
                'password' => Hash::make('ClientPass123!'),
                'address1' => '300 Baker Street',
                'address2' => 'London',
                'headline' => 'Relocating to a new home this quarter.',
                'notes' => 'Acts as both seller and buyer representative for samples.',
                'avatar_path' => null,
                'email_verified_at' => $timestamp,
                'verification_token' => Str::random(32),
                'remember_token' => Str::random(10),
                'last_login_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

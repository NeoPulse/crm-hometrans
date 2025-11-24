<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LegalProfileSeeder extends Seeder
{
    /**
     * Seed legal profiles for solicitor users.
     */
    public function run(): void
    {
        // Use a single timestamp to align created and updated markers.
        $timestamp = Carbon::now();

        // Attach three solicitor profiles to the legal user for demo coverage.
        DB::table('legal_profiles')->insert([
            [
                'user_id' => 2,
                'company' => 'Northbank Law Group',
                'website' => 'https://northbank.example.com',
                'locality' => 'London',
                'person' => 'Laura Legal',
                'office' => '200 Fleet Street, London',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'user_id' => 2,
                'company' => 'City Conveyancing',
                'website' => 'https://cityconveyancing.example.com',
                'locality' => 'Manchester',
                'person' => 'Laura Legal',
                'office' => '12 King Street, Manchester',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'user_id' => 2,
                'company' => 'Harbour & Co',
                'website' => 'https://harbourco.example.com',
                'locality' => 'Bristol',
                'person' => 'Laura Legal',
                'office' => '8 Anchor Road, Bristol',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

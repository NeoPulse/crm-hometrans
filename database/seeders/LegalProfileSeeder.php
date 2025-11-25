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
        ]);
    }
}

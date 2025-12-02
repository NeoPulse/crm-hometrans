<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CaseSeeder extends Seeder
{
    /**
     * Seed property transaction cases with demo assignments.
     */
    public function run(): void
    {
        // Capture the current time for created and updated timestamps.
        $timestamp = Carbon::now();

        // Insert three cases linked to the demo legal and client accounts.
        DB::table('cases')->insert([
            [
                'postal_code' => 'SW1A 1AA',
                'sell_legal_id' => 2,
                'sell_client_id' => 3,
                'buy_legal_id' => 2,
                'buy_client_id' => 3,
                'deadline' => Carbon::now()->addDays(30)->toDateString(),
                'property' => '10 Downing Street, London',
                'status' => 'progress',
                'headline' => 'Prime property exchange in central London.',
                'notes' => 'Sample case demonstrating both sides handled by demo accounts.',
                'public_link' => Str::random(12),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'postal_code' => 'M1 1AE',
                'sell_legal_id' => 2,
                'sell_client_id' => 3,
                'buy_legal_id' => 2,
                'buy_client_id' => 3,
                'deadline' => Carbon::now()->addDays(45)->toDateString(),
                'property' => '50 King Street, Manchester',
                'status' => 'new',
                'headline' => 'City centre apartment sale kickoff.',
                'notes' => 'Used to illustrate initial workflow steps.',
                'public_link' => Str::random(12),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'postal_code' => 'BS1 4ST',
                'sell_legal_id' => 2,
                'sell_client_id' => 3,
                'buy_legal_id' => 2,
                'buy_client_id' => 3,
                'deadline' => Carbon::now()->addDays(60)->toDateString(),
                'property' => '8 Anchor Road, Bristol',
                'status' => 'completed',
                'headline' => 'Harbourside townhouse completion.',
                'notes' => 'Marks an already completed workflow for reference.',
                'public_link' => Str::random(12),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

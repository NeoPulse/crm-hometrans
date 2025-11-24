<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StageSeeder extends Seeder
{
    /**
     * Seed stages for the sample cases.
     */
    public function run(): void
    {
        // Reuse a timestamp to keep stage records synchronised.
        $timestamp = Carbon::now();

        // Insert three stages mapped to the seeded cases.
        DB::table('stages')->insert([
            [
                'case_id' => 1,
                'name' => 'Initial review and document collection',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'case_id' => 2,
                'name' => 'Buyer enquiries and searches',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'case_id' => 3,
                'name' => 'Completion and post-completion filings',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

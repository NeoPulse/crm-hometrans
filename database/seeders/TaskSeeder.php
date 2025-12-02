<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskSeeder extends Seeder
{
    /**
     * Seed tasks across the demo stages.
     */
    public function run(): void
    {
        // Fix timestamps to keep task history consistent.
        $timestamp = Carbon::now();

        // Insert three tasks with alternating sides and statuses.
        DB::table('tasks')->insert([
            [
                'stage_id' => 1,
                'name' => 'Collect identity documents from both parties',
                'side' => 'seller',
                'status' => 'progress',
                'deadline' => Carbon::now()->addDays(7)->toDateString(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'stage_id' => 2,
                'name' => 'Review mortgage offer and conditions',
                'side' => 'buyer',
                'status' => 'new',
                'deadline' => Carbon::now()->addDays(10)->toDateString(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'stage_id' => 3,
                'name' => 'Confirm completion funds received',
                'side' => 'seller',
                'status' => 'done',
                'deadline' => Carbon::now()->addDays(3)->toDateString(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

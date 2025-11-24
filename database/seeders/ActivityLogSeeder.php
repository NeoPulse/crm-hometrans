<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityLogSeeder extends Seeder
{
    /**
     * Seed activity logs summarising sample actions.
     */
    public function run(): void
    {
        // Capture the current timestamp for consistent log entries.
        $timestamp = Carbon::now();

        // Insert three log entries demonstrating various user events.
        DB::table('activity_logs')->insert([
            [
                'user_id' => 1,
                'action' => 'Case overview accessed',
                'target_type' => 'case',
                'target_id' => 1,
                'location' => 'dashboard',
                'details' => 'Admin reviewed the active London case.',
                'ip_address' => '127.0.0.1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'user_id' => 2,
                'action' => 'Stage updated',
                'target_type' => 'stage',
                'target_id' => 2,
                'location' => 'cases/2/stages',
                'details' => 'Solicitor adjusted buyer enquiries checklist.',
                'ip_address' => '127.0.0.1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'user_id' => 3,
                'action' => 'Task marked complete',
                'target_type' => 'task',
                'target_id' => 3,
                'location' => 'cases/3/tasks',
                'details' => 'Client confirmed receipt of completion funds.',
                'ip_address' => '127.0.0.1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

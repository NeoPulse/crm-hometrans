<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttentionSeeder extends Seeder
{
    /**
     * Seed attentions highlighting key activities for demo users.
     */
    public function run(): void
    {
        // Align timestamps for attention records.
        $timestamp = Carbon::now();

        // Insert three attention notifications linked to different targets.
        DB::table('attentions')->insert([
            [
                'target_type' => 'case',
                'target_id' => 1,
                'type' => 'attention',
                'user_id' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'target_type' => 'stage',
                'target_id' => 2,
                'type' => 'mail',
                'user_id' => 2,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'target_type' => 'task',
                'target_id' => 3,
                'type' => 'doc',
                'user_id' => 3,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

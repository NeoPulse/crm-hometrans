<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientProfileSeeder extends Seeder
{
    /**
     * Seed client profiles connected to the demo client account.
     */
    public function run(): void
    {
        // Establish a shared timestamp to keep records aligned.
        $timestamp = Carbon::now();

        // Insert three variations of client profiles for sample cases.
        DB::table('client_profiles')->insert([
            [
                'user_id' => 3,
                'first_name' => 'Carl',
                'last_name' => 'Client',
                'letter' => 'Looking to finalise the relocation to a new address this month.',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}

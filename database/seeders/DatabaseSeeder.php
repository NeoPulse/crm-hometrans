<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with demo data.
     */
    public function run(): void
    {
        // Register the seeders in dependency order to satisfy foreign keys.
        $this->call([
            UserSeeder::class,
            LegalProfileSeeder::class,
            ClientProfileSeeder::class,
            CaseSeeder::class,
            StageSeeder::class,
            TaskSeeder::class,
            AttentionSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}

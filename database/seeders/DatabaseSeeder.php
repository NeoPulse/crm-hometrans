<?php

namespace Database\Seeders;

use App\Models\CaseFile;
use App\Models\ClientProfile;
use App\Models\LegalProfile;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
            'password' => Hash::make('password'),
            'headline' => 'Project manager for all transactions',
        ]);

        $sellerLegal = User::create([
            'name' => 'Sally Legal',
            'email' => 'legal@example.com',
            'role' => 'legal',
            'is_active' => true,
            'password' => Hash::make('password'),
            'phone' => '+1-555-0100',
            'headline' => 'Seller side solicitor',
        ]);

        $buyerLegal = User::create([
            'name' => 'Ben Buyer Legal',
            'email' => 'legal2@example.com',
            'role' => 'legal',
            'is_active' => true,
            'password' => Hash::make('password'),
            'phone' => '+1-555-0200',
            'headline' => 'Buyer side solicitor',
        ]);

        $sellerClient = User::create([
            'name' => 'Carla Client',
            'email' => 'client@example.com',
            'role' => 'client',
            'is_active' => true,
            'password' => Hash::make('password'),
            'phone' => '+1-555-0300',
            'headline' => 'Seller',
        ]);

        $buyerClient = User::create([
            'name' => 'Brian Buyer',
            'email' => 'client2@example.com',
            'role' => 'client',
            'is_active' => true,
            'password' => Hash::make('password'),
            'phone' => '+1-555-0400',
            'headline' => 'Buyer',
        ]);

        LegalProfile::create([
            'user_id' => $sellerLegal->id,
            'company' => 'Sellers Law Ltd',
            'website' => 'https://sellers.test',
            'locality' => 'London',
            'person' => 'Sally Legal',
            'office' => '21 Baker Street, London',
        ]);

        LegalProfile::create([
            'user_id' => $buyerLegal->id,
            'company' => 'BuyRight',
            'website' => 'https://buyers.test',
            'locality' => 'Manchester',
            'person' => 'Ben Buyer Legal',
            'office' => '5 King Road, Manchester',
        ]);

        ClientProfile::create([
            'user_id' => $sellerClient->id,
            'first_name' => 'Carla',
            'last_name' => 'Client',
            'letter' => 'Welcome to your selling journey.',
        ]);

        ClientProfile::create([
            'user_id' => $buyerClient->id,
            'first_name' => 'Brian',
            'last_name' => 'Buyer',
            'letter' => 'We will keep you posted on progress.',
        ]);

        $case = CaseFile::create([
            'postal_code' => 'E1 6AN',
            'sell_legal_id' => $sellerLegal->id,
            'sell_client_id' => $sellerClient->id,
            'buy_legal_id' => $buyerLegal->id,
            'buy_client_id' => $buyerClient->id,
            'deadline' => now()->addMonth(),
            'property' => '12 Thames View, London',
            'status' => 'progress',
            'headline' => 'Downtown apartment sale',
            'notes' => 'Sample seeded case.',
            'public_link' => Str::random(12),
        ]);

        $stage = Stage::create([
            'case_id' => $case->id,
            'name' => 'Client Onboarding & File Opening',
        ]);

        Task::create([
            'stage_id' => $stage->id,
            'name' => 'Collect seller ID documentation',
            'side' => 'seller',
            'status' => 'progress',
            'deadline' => now()->addDays(5),
        ]);

        Task::create([
            'stage_id' => $stage->id,
            'name' => 'Verify buyer funding proof',
            'side' => 'buyer',
            'status' => 'new',
            'deadline' => now()->addDays(7),
        ]);
    }
}

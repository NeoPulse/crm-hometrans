<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Attention;
use App\Models\CaseChatMessage;
use App\Models\CaseFile;
use App\Models\ClientProfile;
use App\Models\LegalProfile;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a representative dataset.
     */
    public function run(): void
    {
        // Create core users.
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
            'password' => Hash::make('password'),
            'headline' => 'Project manager for all transactions',
        ]);

        $legalUser = User::create([
            'name' => 'Sally Legal',
            'email' => 'legal@example.com',
            'role' => 'legal',
            'is_active' => true,
            'phone' => '+1-555-2000',
            'address1' => '10 River Street',
            'headline' => 'Seller side solicitor',
            'password' => Hash::make('password'),
        ]);

        $clientUser = User::create([
            'name' => 'Brandon Client',
            'email' => 'client@example.com',
            'role' => 'client',
            'is_active' => true,
            'phone' => '+1-555-3000',
            'address1' => '22 Orange Avenue',
            'headline' => 'Seller',
            'password' => Hash::make('password'),
        ]);

        $buyLegalUser = User::create([
            'name' => 'Bianca Buyer',
            'email' => 'buy-legal@example.com',
            'role' => 'legal',
            'is_active' => true,
            'phone' => '+1-555-4000',
            'headline' => 'Buyer side solicitor',
            'password' => Hash::make('password'),
        ]);

        $buyClientUser = User::create([
            'name' => 'Hector Buyer',
            'email' => 'buyer@example.com',
            'role' => 'client',
            'is_active' => true,
            'phone' => '+1-555-5000',
            'headline' => 'Buyer',
            'password' => Hash::make('password'),
        ]);

        // Attach profile details.
        LegalProfile::create([
            'user_id' => $legalUser->id,
            'company' => 'Sellers Law Ltd',
            'website' => 'https://sellers.example.com',
            'locality' => 'London',
            'person' => 'Sally Legal',
            'office' => '21 Baker Street, London',
        ]);

        LegalProfile::create([
            'user_id' => $buyLegalUser->id,
            'company' => 'BuyRight Solicitors',
            'website' => 'https://buyright.example.com',
            'locality' => 'Manchester',
            'person' => 'Bianca Buyer',
            'office' => '5 King Road, Manchester',
        ]);

        ClientProfile::create([
            'user_id' => $clientUser->id,
            'first_name' => 'Brandon',
            'last_name' => 'Client',
            'letter' => 'Welcome to HomeTrans, your sale is now in motion.',
        ]);

        ClientProfile::create([
            'user_id' => $buyClientUser->id,
            'first_name' => 'Hector',
            'last_name' => 'Buyer',
            'letter' => 'Buyer account ready for updates.',
        ]);

        // Create a sample case with relationships.
        $case = CaseFile::create([
            'postal_code' => 'E1 6AN',
            'sell_legal_id' => $legalUser->id,
            'sell_client_id' => $clientUser->id,
            'buy_legal_id' => $buyLegalUser->id,
            'buy_client_id' => $buyClientUser->id,
            'deadline' => now()->addMonth()->toDateString(),
            'property' => '12 Thames View, London',
            'status' => 'progress',
            'headline' => 'Downtown apartment sale',
            'notes' => 'Priority transaction with tight timeline.',
            'public_link' => Str::random(12),
        ]);

        // Build out stages and tasks.
        $onboarding = Stage::create([
            'case_id' => $case->id,
            'name' => 'Client Onboarding & File Opening',
        ]);

        $review = Stage::create([
            'case_id' => $case->id,
            'name' => 'Contract Review',
        ]);

        Task::insert([
            [
                'stage_id' => $onboarding->id,
                'name' => 'Collect seller ID documentation',
                'side' => 'seller',
                'status' => 'progress',
                'deadline' => now()->addDays(5)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'stage_id' => $onboarding->id,
                'name' => 'Verify buyer funding proof',
                'side' => 'buyer',
                'status' => 'new',
                'deadline' => now()->addDays(7)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'stage_id' => $review->id,
                'name' => 'Draft sale contract',
                'side' => 'seller',
                'status' => 'done',
                'deadline' => now()->addDays(10)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Attentions to highlight activity.
        Attention::insert([
            [
                'target_type' => 'case',
                'target_id' => $case->id,
                'type' => 'attention',
                'user_id' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'target_type' => 'task',
                'target_id' => Task::where('stage_id', $onboarding->id)->first()->id,
                'type' => 'new',
                'user_id' => $legalUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seed chat history for the case.
        CaseChatMessage::insert([
            [
                'case_id' => $case->id,
                'sender_id' => $admin->id,
                'sender_alias' => 'Manager',
                'body' => 'Welcome to the transaction room. I will coordinate updates here.',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'case_id' => $case->id,
                'sender_id' => $legalUser->id,
                'sender_alias' => 'Sell Side',
                'body' => 'Seller documents are nearly ready.',
                'created_at' => now()->subHours(12),
                'updated_at' => now()->subHours(12),
            ],
        ]);

        // Log initial actions for traceability.
        ActivityLog::insert([
            [
                'user_id' => $admin->id,
                'action' => 'case_created',
                'target_type' => 'case',
                'target_id' => $case->id,
                'description' => 'Created case and assigned parties.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $legalUser->id,
                'action' => 'documents_uploaded',
                'target_type' => 'case',
                'target_id' => $case->id,
                'description' => 'Uploaded initial seller documentation.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

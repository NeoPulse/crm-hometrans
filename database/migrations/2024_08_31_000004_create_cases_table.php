<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('postal_code');
            $table->foreignId('sell_legal_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sell_client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buy_legal_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buy_client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->string('property')->nullable();
            $table->enum('status', ['new', 'progress', 'completed', 'cancelled'])->default('new');
            $table->string('headline')->nullable();
            $table->longText('notes')->nullable();
            $table->string('public_link', 32)->unique();
            $table->timestamps();

            $table->index('status');
            $table->index('deadline');
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};

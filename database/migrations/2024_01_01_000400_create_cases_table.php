<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the cases table for property transactions.
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('postal_code');
            $table->foreignId('sell_legal_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('sell_client_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('buy_legal_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('buy_client_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->string('property')->nullable();
            $table->enum('status', ['new', 'progress', 'completed', 'cancelled']);
            $table->text('headline')->nullable();
            $table->longText('notes')->nullable();
            $table->string('public_link', 16)->unique();
            $table->timestamps();

            $table->index(['status', 'deadline']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the cases table.
        Schema::dropIfExists('cases');
    }
};

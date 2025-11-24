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
        // Create the attentions table for user and case notifications.
        Schema::create('attentions', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['user', 'case', 'stage', 'task']);
            $table->unsignedBigInteger('target_id');
            $table->enum('type', ['attention', 'mail', 'doc', 'call', 'new']);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the attentions table.
        Schema::dropIfExists('attentions');
    }
};

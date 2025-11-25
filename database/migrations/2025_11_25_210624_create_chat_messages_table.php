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
        // Create the chat_messages table to store threaded case conversations.
        Schema::create('chat_messages', function (Blueprint $table) {
            // Primary key for individual chat messages.
            $table->id();
            // Foreign key linking the message to its parent case file.
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            // Author reference for auditability and permissions.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Role label snapshot (manager, buy, sell) to display consistent badges.
            $table->string('sender_label');
            // Optional textual body of the chat message.
            $table->text('body')->nullable();
            // Internal storage path for the uploaded attachment when present.
            $table->string('attachment_path')->nullable();
            // Original file name to present in the UI for downloads.
            $table->string('attachment_name')->nullable();
            // Mime type used for secure download responses.
            $table->string('attachment_mime')->nullable();
            // File size in bytes to help with future validations and display.
            $table->unsignedBigInteger('attachment_size')->nullable();
            // Standard timestamp columns for ordering and audits.
            $table->timestamps();

            // Composite index to optimise lookups per case and chronology.
            $table->index(['case_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the chat_messages table if it exists.
        Schema::dropIfExists('chat_messages');
    }
};

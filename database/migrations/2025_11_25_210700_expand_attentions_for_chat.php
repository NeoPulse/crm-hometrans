<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand attentions to support chat messages and chat unread markers.
     */
    public function up(): void
    {
        // Rebuild the attentions table so we can add chat-specific enum options safely for SQLite.
        DB::transaction(function () {
            Schema::create('attentions_temp', function (Blueprint $table) {
                // Identify the related record, now including chat messages.
                $table->id();
                $table->enum('target_type', ['user', 'case', 'stage', 'task', 'chat_message']);
                $table->unsignedBigInteger('target_id');

                // Track the notification purpose, now covering chat unread state.
                $table->enum('type', ['attention', 'mail', 'doc', 'call', 'new', 'chat']);
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                // Speed up lookups by target on the rebuilt table.
                $table->index(['target_type', 'target_id']);
            });

            // Preserve the existing data while adopting the expanded enum options.
            DB::statement('INSERT INTO attentions_temp (id, target_type, target_id, type, user_id, created_at, updated_at) SELECT id, target_type, target_id, type, user_id, created_at, updated_at FROM attentions');

            // Swap the old table with the expanded version.
            Schema::drop('attentions');
            Schema::rename('attentions_temp', 'attentions');
        });
    }

    /**
     * Revert attentions to the original enum set without chat tracking.
     */
    public function down(): void
    {
        // Recreate the legacy structure, filtering out chat-specific records when rolling back.
        DB::transaction(function () {
            Schema::create('attentions_legacy', function (Blueprint $table) {
                // Restore the original relation targets without chat messages.
                $table->id();
                $table->enum('target_type', ['user', 'case', 'stage', 'task']);
                $table->unsignedBigInteger('target_id');

                // Reapply the initial notification purposes.
                $table->enum('type', ['attention', 'mail', 'doc', 'call', 'new']);
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                // Reinstate the lookup index for the legacy table.
                $table->index(['target_type', 'target_id']);
            });

            // Copy back only compatible rows, defaulting unknown types to attention for safety.
            DB::statement("INSERT INTO attentions_legacy (id, target_type, target_id, type, user_id, created_at, updated_at) SELECT id, target_type, target_id, CASE WHEN type NOT IN ('attention','mail','doc','call','new') THEN 'attention' ELSE type END AS type, user_id, created_at, updated_at FROM attentions WHERE target_type IN ('user','case','stage','task')");

            // Replace the expanded table with the original layout.
            Schema::drop('attentions');
            Schema::rename('attentions_legacy', 'attentions');
        });
    }
};

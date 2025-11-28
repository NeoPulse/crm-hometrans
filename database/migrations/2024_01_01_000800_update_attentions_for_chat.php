<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend attention enums to support chat notifications.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attentions MODIFY target_type ENUM('user','case','stage','task','chat')");
            DB::statement("ALTER TABLE attentions MODIFY type ENUM('attention','mail','doc','call','new','msg')");

            return;
        }

        if ($driver === 'sqlite') {
            // Rebuild the table with the expanded enum constraints for SQLite installations.
            Schema::create('attentions_temp', function (Blueprint $table) {
                $table->id();
                $table->enum('target_type', ['user', 'case', 'stage', 'task', 'chat']);
                $table->unsignedBigInteger('target_id');
                $table->enum('type', ['attention', 'mail', 'doc', 'call', 'new', 'msg']);
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->index(['target_type', 'target_id']);
            });

            DB::table('attentions')->orderBy('id')->chunk(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('attentions_temp')->insert((array) $record);
                }
            });

            Schema::drop('attentions');
            Schema::rename('attentions_temp', 'attentions');
        }
    }

    /**
     * Roll back the enum changes to the previous state.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attentions MODIFY target_type ENUM('user','case','stage','task')");
            DB::statement("ALTER TABLE attentions MODIFY type ENUM('attention','mail','doc','call','new')");

            return;
        }

        if ($driver === 'sqlite') {
            // Restore the original enum set by recreating the table.
            Schema::create('attentions_restore', function (Blueprint $table) {
                $table->id();
                $table->enum('target_type', ['user', 'case', 'stage', 'task']);
                $table->unsignedBigInteger('target_id');
                $table->enum('type', ['attention', 'mail', 'doc', 'call', 'new']);
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->index(['target_type', 'target_id']);
            });

            DB::table('attentions')->orderBy('id')->chunk(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('attentions_restore')->insert([
                        'id' => $record->id,
                        'target_type' => in_array($record->target_type, ['user', 'case', 'stage', 'task'], true) ? $record->target_type : 'case',
                        'target_id' => $record->target_id,
                        'type' => in_array($record->type, ['attention', 'mail', 'doc', 'call', 'new'], true) ? $record->type : 'attention',
                        'user_id' => $record->user_id,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                    ]);
                }
            });

            Schema::drop('attentions');
            Schema::rename('attentions_restore', 'attentions');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attentions', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['user', 'case', 'stage', 'task']);
            $table->unsignedBigInteger('target_id');
            $table->enum('type', ['attention', 'mail', 'doc', 'call', 'new']);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->unique(['target_type', 'target_id', 'type', 'user_id'], 'attention_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attentions');
    }
};

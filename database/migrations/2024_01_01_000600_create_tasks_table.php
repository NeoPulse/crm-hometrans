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
        // Create the tasks table for stage-level actions.
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $table->string('name', 300);
            $table->enum('side', ['seller', 'buyer']);
            $table->enum('status', ['new', 'progress', 'done']);
            $table->date('deadline')->nullable();
            $table->timestamps();

            $table->index('stage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the tasks table.
        Schema::dropIfExists('tasks');
    }
};

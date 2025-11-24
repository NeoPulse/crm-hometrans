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
        // Create the legal_profiles table for solicitor-specific details.
        Schema::create('legal_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('company')->nullable();
            $table->string('website')->nullable();
            $table->string('locality')->nullable();
            $table->string('person')->nullable();
            $table->string('office')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the legal_profiles table.
        Schema::dropIfExists('legal_profiles');
    }
};

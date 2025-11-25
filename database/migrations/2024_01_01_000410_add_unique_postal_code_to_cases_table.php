<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique index to postal codes to guarantee uniqueness across cases.
     */
    public function up(): void
    {
        // Enforce uniqueness on the postal_code column while keeping existing data safe.
        Schema::table('cases', function (Blueprint $table) {
            $table->unique('postal_code');
        });
    }

    /**
     * Remove the unique postal code index when rolling back.
     */
    public function down(): void
    {
        // Drop the unique constraint to restore the previous schema shape.
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique(['postal_code']);
        });
    }
};

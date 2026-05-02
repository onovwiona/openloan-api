<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Note: Spatie migration creates model_has_roles table.
     * This migration is now empty as Spatie handles the pivot table.
     */
    public function up(): void
    {
        // Spatie migration (2026_04_28_091826_create_permission_tables) already creates:
        // - model_has_roles (renamed from role_user)
        // No additional tables needed here.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Spatie handles the cleanup
    }
};

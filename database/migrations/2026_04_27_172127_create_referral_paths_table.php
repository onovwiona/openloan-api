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
        Schema::create('referral_paths', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ancestor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('descendant_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('depth');

            $table->timestamps();

            $table->unique(['ancestor_user_id', 'descendant_user_id']);
            $table->index(['descendant_user_id', 'depth']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_paths');
    }
};

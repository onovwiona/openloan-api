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
        Schema::create('signup_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            // ✅ Controlled lengths (important for indexing)
            $table->string('phone', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('referral_code', 50)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // ✅ Hash should not be huge
            $table->string('device_hash', 64)->nullable();

            $table->enum('status', ['pending', 'created', 'failed', 'blocked', 'reviewed'])
                ->default('pending');

            $table->string('failure_reason', 255)->nullable();
            $table->string('blocked_reason', 255)->nullable();

            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            // ✅ SAFE indexes
            $table->index(['phone', 'email']); // now fits within limit
            $table->index(['ip_address', 'device_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signup_attempts');
    }
};

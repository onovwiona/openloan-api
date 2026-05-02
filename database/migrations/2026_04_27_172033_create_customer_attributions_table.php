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
        Schema::create('customer_attributions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->enum('source_type', [
                'marketer',
                'staff',
                'secretary',
                'walk_in',
                'customer_referral',
                'organic',
                'campaign'
            ]);

            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referral_code_id')->nullable()->constrained('referral_codes')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('campaign_code')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_hash')->nullable();

            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();

            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['source_type', 'source_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_attributions');
    }
};

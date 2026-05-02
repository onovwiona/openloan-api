<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->foreignId('beneficiary_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('event_type', [
                'signup',
                'kyc_verified',
                'loan_applied',
                'loan_approved',
                'loan_disbursed',
                'first_repayment',
                'repayment_collected',
                'referral_bonus'
            ]);

            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();

            $table->decimal('base_amount', 15, 2)->nullable();
            $table->decimal('commission_amount', 15, 2);

            $table->enum('status', ['pending', 'approved', 'paid', 'reversed', 'cancelled'])->default('pending');

            $table->timestamp('earned_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['beneficiary_user_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_events');
    }
};

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
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->enum('beneficiary_role', ['marketer', 'staff', 'secretary', 'customer']);
$table->enum('trigger_type', [
                'signup',
                'kyc_verified',
                'loan_applied',
                'loan_approved',
                'loan_disbursed',
                'first_repayment',
                'repayment_collected',
                'referral_bonus'
            ]);

            $table->enum('amount_type', ['fixed', 'percentage']);
            $table->decimal('amount_value', 15, 2);
            $table->decimal('minimum_amount', 15, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};

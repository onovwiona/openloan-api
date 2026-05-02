<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_account')->default(true);
            $table->uuid('repayment_account_type_id')->nullable();
            $table->decimal('min_amount', 18, 2);
            $table->decimal('max_amount', 18, 2);
            $table->enum('interest_type', ['flat', 'reducing'])->default('reducing');
            $table->decimal('interest_rate', 5, 2); // Annual rate
            $table->integer('tenure_min_months');
            $table->integer('tenure_max_months');
            $table->decimal('processing_fee', 5, 2)->default(0); // Percentage
            $table->decimal('penalty_rate', 5, 2)->default(0); // Monthly penalty rate
            $table->decimal('insurance_fee', 5, 2)->default(0);
            $table->decimal('legal_fee', 5, 2)->default(0);
            $table->boolean('allow_early_repayment')->default(true);
            $table->decimal('early_repayment_penalty', 5, 2)->default(0);
            $table->boolean('requires_guarantor')->default(false);
            $table->integer('min_guarantors')->default(0);
            $table->boolean('requires_collateral')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('repayment_account_type_id')->references('id')->on('account_types')->onDelete('set null');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
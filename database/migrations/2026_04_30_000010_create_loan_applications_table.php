<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('loan_product_id');
            $table->uuid('account_id')->nullable(); // Repayment account
            $table->string('application_no', 20)->unique();
            $table->decimal('requested_amount', 18, 2);
            $table->integer('requested_tenure'); // In months
            $table->decimal('monthly_income', 18, 2)->nullable();
            $table->string('employment_status')->nullable(); // employed, self_employed, business
            $table->string('purpose')->nullable();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'disbursed', 'cancelled'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('loan_product_id')->references('id')->on('loan_products')->onDelete('restrict');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('customer_id');
            $table->index('status');
            $table->index('application_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
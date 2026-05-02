<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_application_id');
            $table->uuid('customer_id');
            $table->uuid('account_id')->nullable(); // Disbursement account
            $table->string('loan_no', 20)->unique();
            $table->decimal('principal', 18, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('tenure_months');
            $table->decimal('total_interest', 18, 2);
            $table->decimal('total_repayment', 18, 2);
            $table->decimal('disbursed_amount', 18, 2);
            $table->decimal('outstanding_principal', 18, 2);
            $table->decimal('outstanding_interest', 18, 2);
            $table->decimal('outstanding_total', 18, 2);
            $table->enum('status', ['pending', 'active', 'closed', 'defaulted', 'writeoff'])->default('pending');
            $table->date('disbursed_at')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('first_payment_date')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('disbursed_by')->nullable();
            $table->timestamps();

            $table->foreign('loan_application_id')->references('id')->on('loan_applications')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('disbursed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('customer_id');
            $table->index('status');
            $table->index('loan_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
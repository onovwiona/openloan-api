<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('account_id')->nullable(); // Payment from account
            $table->decimal('amount', 18, 2);
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('fees_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->string('payment_channel')->nullable(); // bank, wallet, transfer
            $table->string('reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('narration')->nullable();
            $table->timestamp('paid_at');
            $table->boolean('allocated')->default(false);
            $table->uuid('allocated_by')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('restrict');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('allocated_by')->references('id')->on('users')->onDelete('set null');
            $table->index('loan_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
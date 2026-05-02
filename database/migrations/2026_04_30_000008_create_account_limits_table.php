<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->decimal('daily_debit_limit', 18, 2)->nullable();
            $table->decimal('daily_credit_limit', 18, 2)->nullable();
            $table->decimal('monthly_debit_limit', 18, 2)->nullable();
            $table->decimal('monthly_credit_limit', 18, 2)->nullable();
            $table->decimal('single_transaction_limit', 18, 2)->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->unique('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_limits');
    }
};
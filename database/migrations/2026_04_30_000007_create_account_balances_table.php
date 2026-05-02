<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->decimal('available_balance', 18, 2)->default(0);
            $table->decimal('ledger_balance', 18, 2)->default(0);
            $table->decimal('hold_balance', 18, 2)->default(0);
            $table->decimal('uncleared_balance', 18, 2)->default(0);
            $table->timestamp('as_at');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->unique('account_id');
            $table->index('as_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('currency', 3)->default('NGN');
            $table->decimal('min_balance', 18, 2)->default(0);
            $table->decimal('max_balance', 18, 2)->nullable();
            $table->boolean('allow_overdraft')->default(false);
            $table->decimal('overdraft_limit', 18, 2)->default(0);
            $table->boolean('accrues_interest')->default(false);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};
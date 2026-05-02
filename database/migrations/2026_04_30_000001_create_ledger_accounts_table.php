<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->uuid('parent_id')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->boolean('active')->default(true);
            $table->boolean('allow_manual_entry')->default(true);
            $table->string('description')->nullable();
            $table->integer('level')->default(1);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('ledger_accounts')->onDelete('set null');
            $table->index('type');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
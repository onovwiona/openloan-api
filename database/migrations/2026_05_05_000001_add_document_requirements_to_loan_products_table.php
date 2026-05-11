<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->boolean('requires_bank_statement')->default(false)->after('requires_collateral');
            $table->boolean('requires_proof_income')->default(false)->after('requires_bank_statement');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['requires_bank_statement', 'requires_proof_income']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->boolean('allow_topup')->nullable()->after('allow_refinance')->comment('Nullable: only set true for products that allow topup');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('allow_topup');
        });
    }
};

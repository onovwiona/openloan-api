<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->boolean('allow_refinance')->nullable()->after('active')->comment('Nullable: only set true for products that allow refinance/topup');
            $table->json('required_cooperative_account_type_ids')->nullable()->after('allow_refinance')->comment('JSON array of account_type ids that satisfy cooperative requirement');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['allow_refinance', 'required_cooperative_account_type_ids']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('loan_products', 'payroll_fields')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->dropColumn('payroll_fields');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('loan_products', 'payroll_fields')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->string('payroll_fields')->nullable()->after('threshold_amount')->comment('JSON array of required payroll fields (gross_amount,net_amount,etc)');
            });
        }
    }
};

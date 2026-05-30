<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_applications')) {
            return;
        }

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->decimal('gross_amount', 18, 2)->nullable()->after('monthly_income');
            $table->decimal('net_amount', 18, 2)->nullable()->after('gross_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_applications')) {
            return;
        }

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['gross_amount', 'net_amount']);
        });
    }
};

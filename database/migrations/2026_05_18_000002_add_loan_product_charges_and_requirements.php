<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('service_charge', 8, 2)->default(0)->after('processing_fee')->comment('Service charge percentage of loan amount');
            $table->decimal('form_fee', 10, 2)->default(0)->after('service_charge')->comment('Fixed form fee amount');
            $table->string('repayment_schedules')->nullable()->after('form_fee')->comment('JSON array of allowed repayment schedules (daily,weekly,bi_weekly,monthly,etc)');
            $table->decimal('threshold_amount', 15, 2)->nullable()->after('repayment_schedules')->comment('Threshold amount for additional requirements');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['service_charge', 'form_fee', 'repayment_schedules', 'threshold_amount']);
        });
    }
};

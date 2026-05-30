<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('employer_id_number')->nullable()->after('monthly_income');
        });

        if (! Schema::hasTable('loan_applications')) {
            return;
        }

        Schema::table('loan_applications', function (Blueprint $table) {
            // Only add employer_id_number; legacy employer_id field removed to avoid duplication.
            $table->string('employer_id_number')->nullable()->after('gross_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_applications')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->dropColumn(['employer_id_number']);
            });
        }

        if (Schema::hasTable('customer_profiles')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                $table->dropColumn('employer_id_number');
            });
        }
    }
};

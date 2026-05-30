<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_applications')) {
            return;
        }

        Schema::table('loan_applications', function (Blueprint $table) {
            // Rename gross_amount and net_amount to match customer_employment_profile naming
            if (Schema::hasColumn('loan_applications', 'gross_amount') && 
                !Schema::hasColumn('loan_applications', 'payroll_gross')) {
                $table->renameColumn('gross_amount', 'payroll_gross');
            }

            if (Schema::hasColumn('loan_applications', 'net_amount') && 
                !Schema::hasColumn('loan_applications', 'payroll_net')) {
                $table->renameColumn('net_amount', 'payroll_net');
            }

            // Add repayment_plan if it doesn't exist
            if (!Schema::hasColumn('loan_applications', 'repayment_plan')) {
                $table->enum('repayment_plan', ['monthly', 'weekly', 'quarterly', 'annually'])
                    ->nullable()
                    ->after('requested_tenure');
            }

            // Ensure employer_id_number exists and remove legacy employer_id if present
            if (!Schema::hasColumn('loan_applications', 'employer_id_number')) {
                $table->string('employer_id_number')->nullable()->after('monthly_income');
            }

            if (Schema::hasColumn('loan_applications', 'employer_id_number')) {
                $table->dropColumn('employer_id_number');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('loan_applications')) {
            return;
        }

        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'payroll_gross')) {
                $table->renameColumn('payroll_gross', 'gross_amount');
            }

            if (Schema::hasColumn('loan_applications', 'payroll_net')) {
                $table->renameColumn('payroll_net', 'net_amount');
            }

            if (Schema::hasColumn('loan_applications', 'repayment_plan')) {
                $table->dropColumn('repayment_plan');
            }

            if (Schema::hasColumn('loan_applications', 'employer_id')) {
                $table->dropColumn('employer_id');
            }
        });
    }
};

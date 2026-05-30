<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_profiles')) {
            if (!Schema::hasColumn('customer_profiles', 'employer_type')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->string('employer_type')->nullable()->after('employment_status');
                });
            }

            if (!Schema::hasColumn('customer_profiles', 'employer_computer_id')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->string('employer_computer_id')->nullable()->after('employer_type');
                });
            }

            if (!Schema::hasColumn('customer_profiles', 'payroll_gross')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->decimal('payroll_gross', 18, 2)->nullable()->after('monthly_income');
                });
            }

            if (!Schema::hasColumn('customer_profiles', 'payroll_net')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->decimal('payroll_net', 18, 2)->nullable()->after('payroll_gross');
                });
            }

            if (!Schema::hasColumn('customer_profiles', 'employment_documents')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->json('employment_documents')->nullable()->after('kyc_documents');
                });
            }

            if (!Schema::hasColumn('customer_profiles', 'employment_profile_status')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->string('employment_profile_status')->default('pending')->after('kyc_status');
                });
            }

            if (!Schema::hasColumn('customer_profiles', 'employment_profile_reviewed_by')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->uuid('employment_profile_reviewed_by')->nullable()->after('employment_profile_status');
                });
            }

            if (!Schema::hasColumn('customer_profiles', 'employment_profile_reviewed_at')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->timestamp('employment_profile_reviewed_at')->nullable()->after('employment_profile_reviewed_by');
                });
            }
        }

        if (Schema::hasTable('loan_products')) {
            if (!Schema::hasColumn('loan_products', 'requires_employment_profile')) {
                Schema::table('loan_products', function (Blueprint $table) {
                    $table->boolean('requires_employment_profile')->default(false)->after('requires_passport');
                });
            }

            if (!Schema::hasColumn('loan_products', 'required_employer_type')) {
                Schema::table('loan_products', function (Blueprint $table) {
                    $table->string('required_employer_type')->nullable()->after('requires_employment_profile');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_products')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->dropColumn(['required_employer_type', 'requires_employment_profile']);
            });
        }

        if (Schema::hasTable('customer_profiles')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                $table->dropColumn([
                    'employer_type',
                    'employer_computer_id',
                    'payroll_gross',
                    'payroll_net',
                    'employment_documents',
                    'employment_profile_status',
                    'employment_profile_reviewed_by',
                    'employment_profile_reviewed_at',
                ]);
            });
        }
    }
};

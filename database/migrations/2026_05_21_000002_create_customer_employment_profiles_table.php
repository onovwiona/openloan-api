<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_employment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_profile_id')->unique()->constrained('customer_profiles')->cascadeOnDelete();

            $table->string('employer_type')->nullable();
            $table->string('employer_computer_id')->nullable();
            $table->string('employer_id_number')->nullable();
            $table->string('employment_status')->nullable();
            $table->decimal('payroll_gross', 18, 2)->nullable();
            $table->decimal('payroll_net', 18, 2)->nullable();
            $table->json('employment_documents')->nullable();
            $table->string('employment_profile_status')->default('pending');
            $table->uuid('employment_profile_reviewed_by')->nullable();
            $table->timestamp('employment_profile_reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        if (Schema::hasTable('customer_profiles')) {
            $sourceColumns = [
                'id',
                'employer_type',
                'employer_computer_id',
                'employer_id_number',
                'employment_status',
                'payroll_gross',
                'payroll_net',
                'employment_documents',
                'employment_profile_status',
                'employment_profile_reviewed_by',
                'employment_profile_reviewed_at',
                'created_at',
                'updated_at',
            ];

            $hasEmploymentColumns = Schema::hasColumn('customer_profiles', 'employer_type')
                && Schema::hasColumn('customer_profiles', 'employment_profile_status');

            if ($hasEmploymentColumns) {
                $profiles = DB::table('customer_profiles')->select($sourceColumns)->get();

                foreach ($profiles as $profile) {
                    DB::table('customer_employment_profiles')->insert([ 
                        'customer_profile_id' => $profile->id,
                        'employer_type' => $profile->employer_type,
                        'employer_computer_id' => $profile->employer_computer_id,
                        'employer_id_number' => $profile->employer_id_number,
                        'employment_status' => $profile->employment_status,
                        'payroll_gross' => $profile->payroll_gross,
                        'payroll_net' => $profile->payroll_net,
                        'employment_documents' => $profile->employment_documents,
                        'employment_profile_status' => $profile->employment_profile_status ?? 'pending',
                        'employment_profile_reviewed_by' => $profile->employment_profile_reviewed_by,
                        'employment_profile_reviewed_at' => $profile->employment_profile_reviewed_at,
                        'created_at' => $profile->created_at,
                        'updated_at' => $profile->updated_at,
                    ]);
                }

                Schema::table('customer_profiles', function (Blueprint $table) {
                    $table->dropColumn([
                        'employer_id_number',
                        'employment_status',
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
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_profiles')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_profiles', 'employment_status')) {
                    $table->string('employment_status')->nullable()->after('nin');
                }
                if (!Schema::hasColumn('customer_profiles', 'employer_id_number')) {
                    $table->string('employer_id_number')->nullable()->after('employment_status');
                }
                if (!Schema::hasColumn('customer_profiles', 'employer_type')) {
                    $table->string('employer_type')->nullable()->after('employer_id_number');
                }
                if (!Schema::hasColumn('customer_profiles', 'employer_computer_id')) {
                    $table->string('employer_computer_id')->nullable()->after('employer_type');
                }
                if (!Schema::hasColumn('customer_profiles', 'payroll_gross')) {
                    $table->decimal('payroll_gross', 18, 2)->nullable()->after('monthly_income');
                }
                if (!Schema::hasColumn('customer_profiles', 'payroll_net')) {
                    $table->decimal('payroll_net', 18, 2)->nullable()->after('payroll_gross');
                }
                if (!Schema::hasColumn('customer_profiles', 'employment_documents')) {
                    $table->json('employment_documents')->nullable()->after('kyc_documents');
                }
                if (!Schema::hasColumn('customer_profiles', 'employment_profile_status')) {
                    $table->string('employment_profile_status')->default('pending')->after('kyc_status');
                }
                if (!Schema::hasColumn('customer_profiles', 'employment_profile_reviewed_by')) {
                    $table->uuid('employment_profile_reviewed_by')->nullable()->after('employment_profile_status');
                }
                if (!Schema::hasColumn('customer_profiles', 'employment_profile_reviewed_at')) {
                    $table->timestamp('employment_profile_reviewed_at')->nullable()->after('employment_profile_reviewed_by');
                }
            });

            if (Schema::hasTable('customer_employment_profiles')) {
                $profiles = DB::table('customer_employment_profiles')->get();

                foreach ($profiles as $employmentProfile) {
                    DB::table('customer_profiles')
                        ->where('id', $employmentProfile->customer_profile_id)
                        ->update([
                            'employer_type' => $employmentProfile->employer_type,
                            'employer_computer_id' => $employmentProfile->employer_computer_id,
                            'employer_id_number' => $employmentProfile->employer_id_number,
                            'employment_status' => $employmentProfile->employment_status,
                            'payroll_gross' => $employmentProfile->payroll_gross,
                            'payroll_net' => $employmentProfile->payroll_net,
                            'employment_documents' => $employmentProfile->employment_documents,
                            'employment_profile_status' => $employmentProfile->employment_profile_status,
                            'employment_profile_reviewed_by' => $employmentProfile->employment_profile_reviewed_by,
                            'employment_profile_reviewed_at' => $employmentProfile->employment_profile_reviewed_at,
                        ]);
                }
            }
        }

        Schema::dropIfExists('customer_employment_profiles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_employment_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_employment_profiles', 'employment_type')) {
                $table->string('employment_type')->nullable()->after('employment_status');
            }

            if (! Schema::hasColumn('customer_employment_profiles', 'retirement_status')) {
                $table->string('retirement_status')->nullable()->after('employment_type');
            }

            if (! Schema::hasColumn('customer_employment_profiles', 'employment_year')) {
                $table->integer('employment_year')->nullable()->after('retirement_status');
            }

            if (! Schema::hasColumn('customer_employment_profiles', 'retirement_year')) {
                $table->integer('retirement_year')->nullable()->after('employment_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_employment_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('customer_employment_profiles', 'retirement_year')) {
                $table->dropColumn('retirement_year');
            }

            if (Schema::hasColumn('customer_employment_profiles', 'employment_year')) {
                $table->dropColumn('employment_year');
            }

            if (Schema::hasColumn('customer_employment_profiles', 'retirement_status')) {
                $table->dropColumn('retirement_status');
            }

            if (Schema::hasColumn('customer_employment_profiles', 'employment_type')) {
                $table->dropColumn('employment_type');
            }
        });
    }
};

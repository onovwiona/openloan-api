<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customer_employment_profiles', 'employer_computer_id')) {
            Schema::table('customer_employment_profiles', function (Blueprint $table) {
                $table->dropColumn('employer_computer_id');
            });
        }

        if (Schema::hasColumn('customer_profiles', 'employer_computer_id')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                $table->dropColumn('employer_computer_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customer_employment_profiles', 'employer_computer_id')) {
            Schema::table('customer_employment_profiles', function (Blueprint $table) {
                $table->string('employer_computer_id')->nullable()->after('employer_type');
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'employer_computer_id')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                $table->string('employer_computer_id')->nullable()->after('employer_type');
            });
        }
    }
};

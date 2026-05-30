<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loans')) {
            return;
        }

        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'repayment_plan')) {
                $table->enum('repayment_plan', ['monthly', 'weekly', 'quarterly', 'annually'])
                    ->default('monthly')
                    ->after('tenure_months');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loans')) {
            return;
        }

        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'repayment_plan')) {
                $table->dropColumn('repayment_plan');
            }
        });
    }
};

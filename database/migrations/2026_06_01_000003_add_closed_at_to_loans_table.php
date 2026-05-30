<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loans') && ! Schema::hasColumn('loans', 'closed_at')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->date('closed_at')->nullable()->after('maturity_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loans') && Schema::hasColumn('loans', 'closed_at')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropColumn('closed_at');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->text('profile_update_note')->nullable()->after('kyc_reviewed_by');
            $table->foreignId('profile_updated_by')->nullable()->after('profile_update_note')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_updated_by');
            $table->dropColumn('profile_update_note');
        });
    }
};

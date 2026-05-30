<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kyc_documents')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `kyc_documents` MODIFY `document_type` ENUM('NIN','BVN','PASSPORT','PASSPORT_DOCUMENT','PASSPORT_PHOTO','SELFIE','ID_CARD_FRONT','ID_CARD_BACK','DRIVERS_LICENSE','UTILITY_BILL','PROOF_OF_ADDRESS','APPOINTMENT_LETTER','EMPLOYER_ID_CARD','EMPLOYMENT_LETTER','EMPLOYMENT_DOCUMENT','PAYSLIP_DOCUMENT') NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('kyc_documents')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `kyc_documents` MODIFY `document_type` ENUM('NIN','BVN','PASSPORT','PASSPORT_DOCUMENT','PASSPORT_PHOTO','SELFIE','ID_CARD_FRONT','ID_CARD_BACK','DRIVERS_LICENSE','UTILITY_BILL','PROOF_OF_ADDRESS','APPOINTMENT_LETTER') NOT NULL");
    }
};

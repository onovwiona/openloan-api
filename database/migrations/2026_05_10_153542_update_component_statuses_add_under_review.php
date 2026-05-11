<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update loan_application_documents status enum
        DB::statement("ALTER TABLE loan_application_documents MODIFY COLUMN status ENUM('pending', 'under_review', 'verified', 'rejected') DEFAULT 'pending'");
        
        // Update loan_collaterals status enum
        DB::statement("ALTER TABLE loan_collaterals MODIFY COLUMN status ENUM('pending', 'under_review', 'verified', 'rejected') DEFAULT 'pending'");
        
        // Update loan_guarantors status enum
        DB::statement("ALTER TABLE loan_guarantors MODIFY COLUMN status ENUM('pending', 'under_review', 'verified', 'rejected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert loan_application_documents status enum
        DB::statement("ALTER TABLE loan_application_documents MODIFY COLUMN status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'");
        
        // Revert loan_collaterals status enum
        DB::statement("ALTER TABLE loan_collaterals MODIFY COLUMN status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'");
        
        // Revert loan_guarantors status enum
        DB::statement("ALTER TABLE loan_guarantors MODIFY COLUMN status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'");
    }
};

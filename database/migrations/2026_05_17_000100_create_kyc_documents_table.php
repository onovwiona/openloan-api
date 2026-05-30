<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->enum('document_type', [
                'NIN',
                'BVN',
                'PASSPORT',
                'SELFIE',
                'ID_CARD_FRONT',
                'ID_CARD_BACK',
                'DRIVERS_LICENSE',
                'UTILITY_BILL',
                'PROOF_OF_ADDRESS',
            ]);
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('storage_path', 500);
            $table->enum('verification_status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};

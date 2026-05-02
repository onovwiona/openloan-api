<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_collaterals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_application_id');
            $table->string('type'); // vehicle, property, equipment, other
            $table->string('description');
            $table->decimal('estimated_value', 18, 2);
            $table->string('document_url')->nullable();
            $table->string('document_type')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('verification_notes')->nullable();
            $table->timestamps();

            $table->foreign('loan_application_id')->references('id')->on('loan_applications')->onDelete('cascade');
            $table->index('loan_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_collaterals');
    }
};
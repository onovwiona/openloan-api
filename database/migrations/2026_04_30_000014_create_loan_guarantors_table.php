<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_application_id');
            $table->string('name');
            $table->string('phone');
            $table->text('address')->nullable();
            $table->string('relationship')->nullable();
            $table->string('employer')->nullable();
            $table->string('employer_phone')->nullable();
            $table->decimal('monthly_income', 18, 2)->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('loan_application_id')->references('id')->on('loan_applications')->onDelete('cascade');
            $table->index('loan_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};
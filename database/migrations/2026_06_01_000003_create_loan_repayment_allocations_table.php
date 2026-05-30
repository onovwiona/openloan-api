<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('repayment_id');
            $table->uuid('schedule_id');
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('fees_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('total_allocated', 18, 2)->storedAs(
                'principal_amount + interest_amount + fees_amount + penalty_amount'
            );
            $table->enum('status', ['pending', 'applied', 'reversed'])->default('pending');
            $table->timestamps();

            $table->foreign('repayment_id')->references('id')->on('loan_repayments')->onDelete('cascade');
            $table->foreign('schedule_id')->references('id')->on('loan_schedules')->onDelete('restrict');
            $table->index('repayment_id');
            $table->index('schedule_id');
            $table->unique(['repayment_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_allocations');
    }
};

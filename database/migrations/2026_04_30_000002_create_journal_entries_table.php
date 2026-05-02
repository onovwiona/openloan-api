<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 50)->unique();
            $table->string('source_type')->nullable(); // loan, payment, manual, transfer, fee, etc.
            $table->uuid('source_id')->nullable();
            $table->text('description');
            $table->date('entry_date');
            $table->uuid('posted_by');
            $table->enum('status', ['draft', 'posted', 'reversed', 'voided'])->default('draft');
            $table->uuid('reversed_by')->nullable();
            $table->uuid('reversal_of')->nullable();
            $table->timestamps();

            $table->foreign('posted_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reversed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index('entry_date');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
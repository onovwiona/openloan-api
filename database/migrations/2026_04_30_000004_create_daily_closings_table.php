<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('closing_date')->unique();
            $table->decimal('total_debits', 18, 2);
            $table->decimal('total_credits', 18, 2);
            $table->boolean('balanced')->default(true);
            $table->uuid('closed_by');
            $table->timestamps();

            $table->foreign('closed_by')->references('id')->on('users')->onDelete('restrict');
            $table->index('closing_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
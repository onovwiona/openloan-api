<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->nullable(); // Links to user (customer)
            $table->uuid('account_type_id');
            $table->string('account_no', 25)->unique();
            $table->string('name');
            $table->string('currency', 3)->default('NGN');
            $table->enum('status', ['active', 'dormant', 'frozen', 'closed'])->default('active');
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->string('freeze_reason')->nullable();
            $table->uuid('frozen_by')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('account_type_id')->references('id')->on('account_types')->onDelete('restrict');
            $table->foreign('frozen_by')->references('id')->on('users')->onDelete('set null');
            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
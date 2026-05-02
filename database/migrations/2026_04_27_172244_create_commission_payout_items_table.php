<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_payout_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payout_batch_id')->constrained('commission_payout_batches')->cascadeOnDelete();
            $table->foreignId('commission_event_id')->constrained('commission_events')->cascadeOnDelete();
            $table->foreignId('beneficiary_user_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->enum('status', ['queued', 'paid', 'failed'])->default('queued');

            $table->timestamps();

            $table->unique('commission_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payout_items');
    }
};

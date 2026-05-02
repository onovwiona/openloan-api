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
        Schema::create('commission_payout_batches', function (Blueprint $table) {
            $table->id();

            $table->string('batch_no')->unique();
            $table->enum('status', ['draft', 'processing', 'paid', 'failed', 'cancelled'])->default('draft');

            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('payment_provider')->nullable();
            $table->string('provider_reference')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payout_batches');
    }
};

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
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('address')->nullable();
            $table->date('dob')->nullable();

            $table->text('bvn_encrypted')->nullable();
            $table->char('bvn_hash', 64)->nullable()->unique();

            $table->string('nin')->nullable();
            $table->string('employment_status')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();

            $table->enum('kyc_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('kyc_verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};

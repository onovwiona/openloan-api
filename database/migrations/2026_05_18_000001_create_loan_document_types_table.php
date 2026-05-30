<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_document_types')) {
            Schema::create('loan_document_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('required')->default(true);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('loan_product_document_types')) {
            Schema::create('loan_product_document_types', function (Blueprint $table) {
                $table->uuid('loan_product_id');
                $table->uuid('document_type_id');
                $table->timestamps();

                $table->foreign('loan_product_id')
                    ->references('id')
                    ->on('loan_products')
                    ->cascadeOnDelete();

                $table->foreign('document_type_id')
                    ->references('id')
                    ->on('loan_document_types')
                    ->cascadeOnDelete();

                $table->primary(['loan_product_id', 'document_type_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_product_document_types');
        Schema::dropIfExists('loan_document_types');
    }
};

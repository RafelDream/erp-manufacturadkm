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
        Schema::create('sales_quotation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_quotation_id')
                ->constrained('sales_quotations')
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete(); //  Jangan boleh hapus product kalau sudah dipakai

            //  Gunakan decimal agar lebih presisi untuk uang
            $table->decimal('qty', 15, 2);
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 18, 2);

            $table->timestamps();

            //  Index untuk performa
            $table->index(['sales_quotation_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_quotation_items');
    }
};

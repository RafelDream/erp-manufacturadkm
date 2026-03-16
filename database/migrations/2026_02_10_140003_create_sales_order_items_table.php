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
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->decimal('qty_pesanan', 15, 2); 
            $table->decimal('qty_shipped', 15, 2)->default(0);

            //  Tambahan agar sinkron dengan quotation
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);

            $table->timestamps();

            //  Index performa
            $table->index(['sales_order_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};

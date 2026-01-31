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
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('unit_id')->constrained('units');
            
            $table->decimal('quantity_ordered', 15, 3); // Qty Dipesan
            $table->decimal('quantity_received', 15, 3); // Qty Terima
            $table->decimal('quantity_remaining', 15, 3)->default(0); // Qty Sisa
            $table->decimal('quantity_actual', 15, 3)->default(0); // Qty Realisasi (setelah QC)
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};

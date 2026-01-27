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
        Schema::create('raw_material_stock_out_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_stock_out_id')
                ->constrained('raw_material_stock_outs')
                ->cascadeOnDelete();
            $table->foreignId('raw_material_id')
                ->constrained('raw_materials')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->foreignId('unit_id')
                ->constrained('units');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stock_out_items');
    }
};

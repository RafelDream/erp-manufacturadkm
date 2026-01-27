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
        Schema::create('raw_material_stock_in_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_stock_in_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained();
            $table->decimal('quantity', 15, 2);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stock_in_items');
    }
};

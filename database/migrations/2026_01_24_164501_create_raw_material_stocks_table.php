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
        Schema::create('raw_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')
                ->constrained('raw_materials')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->unique(['raw_material_id', 'warehouse_id'], 'rm_stock_unique');
            $table->softDeletes();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stocks');
    }
};

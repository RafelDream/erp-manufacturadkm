<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_of_material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_material_id')->constrained('bill_of_materials')->onDelete('cascade');
            $table->foreignId('raw_material_id')->constrained('raw_materials');
            $table->decimal('quantity', 15, 3);
            $table->foreignId('unit_id')->constrained('units');
            $table->timestamps();
            
            $table->index('bill_of_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_of_material_items');
    }
};
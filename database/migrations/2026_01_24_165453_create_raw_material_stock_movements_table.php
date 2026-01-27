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
        Schema::create('raw_material_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->enum('movement_type', [
                'IN',
                'OUT',
                'ADJUSTMENT',
                'TRANSFER_IN',
                'TRANSFER_OUT'
            ]);
            $table->decimal('quantity', 15, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stock_movements');
    }
};

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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('production_number')->unique();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('bom_id')->constrained('bill_of_materials');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('production_date');
            
            // Quantities
            $table->decimal('quantity_plan', 15, 3);
            $table->decimal('quantity_actual', 15, 3)->nullable();
            $table->decimal('quantity_waste', 15, 3)->default(0);
            
            // Status
            $table->enum('status', ['draft', 'released', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->string('operator')->nullable();
            
            // Costs
            $table->decimal('total_material_cost', 20, 2)->default(0);
            $table->decimal('labor_cost', 20, 2)->default(0);
            $table->decimal('overhead_cost', 20, 2)->default(0);
            $table->decimal('total_production_cost', 20, 2)->default(0);
            $table->decimal('hpp_per_unit', 20, 2)->nullable();
            
            // Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'production_date']);
            $table->index('production_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};

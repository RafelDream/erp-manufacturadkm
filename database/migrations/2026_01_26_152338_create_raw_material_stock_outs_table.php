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
        Schema::create('raw_material_stock_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->date('issued_at')->index();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'posted', 'cancelled'])
                  ->default('draft');
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->softDeletes();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stock_outs');
    }
};

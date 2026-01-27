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
        Schema::create('raw_material_stock_ins', function (Blueprint $table) {
            $table->id();
            $table->string('stock_in_number')->unique();
            $table->date('stock_in_date');
            $table->foreignId('warehouse_id')->constrained();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stock_ins');
    }
};

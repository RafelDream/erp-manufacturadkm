<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Symfony\Component\String\s;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->foreignId('dari_warehouse_id')->constrained('warehouses');
            $table->foreignId('ke_warehouse_id')->constrained('warehouses');
            $table->date('transfer_date');
            $table->enum('status', ['draft','approved','rejected','executed'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        schema::dropIfExists('stock_transfers');
    }
};

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
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('no_sj')->unique(); 
            $table->date('tanggal');
            $table->string('no_spk')->nullable(); 
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers'); // Relasi ke Master Customer baru kita
            $table->foreignId('warehouse_id')->constrained('warehouses'); // Gudang Pengirim
            $table->string('expedition')->nullable();
            $table->string('vehicle_number')->nullable(); 
            $table->enum('status', ['draft', 'shipped', 'received', 'cancelled'])->default('draft');
            $table->text('notes')->nullable(); // Catatan Tambahan
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};

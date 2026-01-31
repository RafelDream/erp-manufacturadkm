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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique(); // No Dokumen
            $table->date('receipt_date'); // Tanggal
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained(); // Gudang Penerima
            
            $table->string('delivery_note_number')->nullable(); // Nomor Surat Jalan
            $table->string('vehicle_number')->nullable(); // Nomor Kendaraan
            $table->string('po_reference')->nullable(); // No Pesanan Pembelian (PO)
            
            $table->enum('type', ['GOODS_RECEIPT', 'RETURN'])->default('GOODS_RECEIPT'); // Jenis
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            
            $table->text('notes')->nullable(); // Catatan Tambahan
            
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};

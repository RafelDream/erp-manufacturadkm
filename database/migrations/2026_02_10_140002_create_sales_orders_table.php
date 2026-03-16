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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();

            $table->string('no_spk')->unique();
            $table->date('tanggal');

            $table->foreignId('customer_id')
                ->constrained('customers');

            //  Tambahan: Jejak dari Quotation
            $table->foreignId('sales_quotation_id')
                ->nullable()
                ->constrained('sales_quotations')
                ->nullOnDelete();

            //  Tambahan: Total harga (dibutuhkan di controller kamu)
            $table->decimal('total_price', 18, 2)->default(0);

            $table->text('notes')->nullable();

            //  Sinkron dengan workflow convert
            $table->enum('status', [
                'pending',     // Baru masuk, menunggu verifikasi
                'approved',    // Sudah diverifikasi, siap dikirim (Ready to Ship)
                'partial',     // Sudah dikirim sebagian
                'completed',   // Selesai (Semua qty sudah terkirim)
                'cancelled'    // Dibatalkan
            ])->default('pending');

            $table->foreignId('created_by')
                ->constrained('users');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};

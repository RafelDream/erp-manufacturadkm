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
        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->onDelete('cascade');
            $table->integer('installment_number'); // Cicilan ke-1, 2, dst
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('receipt_no'); // Nomor struk khusus cicilan ini
            $table->text('notes')->nullable();
            // Pada file migration sales_invoices
            $table->decimal('total_fines', 15, 2)->default(0); // Akumulasi total denda
            // Pada file migration invoice_installments
            $table->decimal('fine_paid', 15, 2)->default(0); // Denda yang dibayar pada cicilan tersebut
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');
    }
};

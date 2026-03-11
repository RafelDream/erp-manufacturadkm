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
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('no_invoice')->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('delivery_order_id')->nullable()->constrained('delivery_orders')->onDelete('set null');
            $table->date('tanggal');
            $table->date('due_date'); // Jatuh tempo
            $table->decimal('total_price', 15, 2);
            $table->decimal('dp_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('pph_amount', 15, 2)->default(0);
            $table->enum('payment_type', ['full', 'dp'])->default('full');
            $table->enum('status', ['draft', 'partial', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->integer('gallon_loan_qty')->default(0);
            $table->enum('gallon_deposit_status', ['none', 'loaned', 'returned'])->default('none');
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
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
        Schema::dropIfExists('sales_invoices');
    }
};

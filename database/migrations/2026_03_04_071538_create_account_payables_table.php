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
        Schema::create('account_payables', function (Blueprint $table) {
            $table->id();
            $table->string('payable_number')->unique();

            // Referensi ke TTF & Invoice
            $table->foreignId('invoice_receipt_id')->constrained('invoice_receipts');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('supplier_id')->constrained('suppliers');

            // Nominal
            $table->decimal('amount', 20, 2);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('remaining_amount', 20, 2);

            // Tanggal
            $table->date('invoice_date');
            $table->date('due_date');

            // Status
            // unpaid   = belum dibayar
            // paid     = lunas
            // overdue  = lewat jatuh tempo (update via scheduler / query)
            $table->enum('status', ['unpaid', 'paid', 'overdue'])->default('unpaid');

            $table->text('notes')->nullable();

            // Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Index untuk aging report
            $table->index(['supplier_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
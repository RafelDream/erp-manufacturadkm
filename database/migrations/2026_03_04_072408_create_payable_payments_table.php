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
        Schema::create('payable_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();

            // Referensi hutang yang dibayar
            $table->foreignId('account_payable_id')->constrained('account_payables');
            $table->foreignId('supplier_id')->constrained('suppliers');

            // Metode pembayaran & akun COA
            // cash, bank_transfer, credit_card, giro_cek
            $table->enum('payment_method', ['cash', 'bank_transfer', 'credit_card', 'giro_cek']);
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts'); // Akun kas/bank dari COA

            // Detail pembayaran
            $table->date('payment_date');
            $table->decimal('amount', 20, 2);
            $table->string('reference_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('notes')->nullable();

            // Status
            // draft     = belum dikonfirmasi
            // confirmed = pembayaran dikonfirmasi, hutang lunas
            // cancelled = dibatalkan
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');

            // Jurnal akuntansi yang dihasilkan
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');

            // Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_date', 'payment_method']);
            $table->index(['account_payable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_payments');
    }
};
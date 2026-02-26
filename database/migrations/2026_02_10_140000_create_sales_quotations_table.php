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
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('no_quotation')->unique();
            $table->date('tanggal');

            $table->foreignId('customer_id')->constrained('customers');

            $table->string('cara_bayar')->nullable();
            $table->double('dp_amount')->default(0);
            $table->double('total_price')->default(0);

            $table->text('notes')->nullable();

            // 🔥 UPGRADE STATUS WORKFLOW
            $table->enum('status', [
                'draft',
                'sent',
                'waiting_approval',
                'approved',
                'rejected',
                'converted'
            ])->default('draft');

            // 🔥 APPROVAL SYSTEM
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

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
        Schema::dropIfExists('sales_quotations');
    }
};

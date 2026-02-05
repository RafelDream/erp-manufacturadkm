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
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->date('return_date');
            
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            
            $table->string('delivery_note_number')->nullable();
            $table->string('vehicle_number')->nullable();
            
            $table->text('reason');
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'realized', 'completed'])->default('draft');
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('realized_by')->nullable()->constrained('users');
            $table->timestamp('realized_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
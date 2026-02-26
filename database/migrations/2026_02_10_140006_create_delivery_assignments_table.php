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
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('no_spkp')->unique();
            $table->foreignId('work_order_id')->constrained('work_orders'); // Referensi ke WO
            $table->string('driver_name'); // Bisa relasi ke table employees jika ada
            $table->string('vehicle_plate_number');
            $table->date('tanggal_kirim');
            $table->enum('status', ['pending', 'on_process', 'in_transit', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('delivery_assignments');
    }
};

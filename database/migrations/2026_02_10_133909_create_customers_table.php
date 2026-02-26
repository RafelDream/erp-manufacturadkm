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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('kode_customer')->unique(); // Contoh: CUST-001
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address'); // Alamat utama untuk Surat Jalan
            $table->string('city')->nullable();
            $table->enum('type', ['distributor', 'agent', 'retail'])->default('retail');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

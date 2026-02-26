<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_material_stock_movements', function (Blueprint $table) {
            $table->decimal('unit_price', 20, 2)->default(0)->after('quantity');
            $table->decimal('total_price', 20, 2)->default(0)->after('unit_price');
            $table->text('notes')->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('raw_material_stock_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'total_price', 'notes']);
        });
    }
};
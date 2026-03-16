<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('hpp_terakhir', 15, 2)->nullable()->after('harga');
            $table->timestamp('hpp_updated_at')->nullable()->after('hpp_terakhir');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hpp_terakhir', 'hpp_updated_at']);
        });
    }
};
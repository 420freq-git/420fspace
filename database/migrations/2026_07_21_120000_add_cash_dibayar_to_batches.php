<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Batch cash sudah dibayar penuh di muka (Diferd lunas, TM bayar 420F).
            $table->boolean('cash_dibayar')->default(false)->after('type_payment');
            $table->timestamp('tgl_cash')->nullable()->after('cash_dibayar');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['cash_dibayar', 'tgl_cash']);
        });
    }
};

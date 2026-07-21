<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Sisa stok batch ini sudah di-buy-out (jadi milik TM420) — keluar dari stok jual 420F.
            $table->boolean('dibuyout')->default(false)->after('deposit_rekonsiliasi');
            $table->timestamp('tgl_buyout')->nullable()->after('dibuyout');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['dibuyout', 'tgl_buyout']);
        });
    }
};

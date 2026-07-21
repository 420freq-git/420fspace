<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            // Kenapa vendor mengirim kurang dari PO: reject / produksi_kurang.
            $table->string('alasan_kurang_kirim')->nullable()->after('catatan');
            // Kenapa brand menerima kurang dari yang dikirim: reject / tidak_ada.
            $table->string('alasan_kurang_terima')->nullable()->after('alasan_kurang_kirim');
            $table->string('catatan_selisih_terima')->nullable()->after('alasan_kurang_terima');
        });
    }

    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropColumn(['alasan_kurang_kirim', 'alasan_kurang_terima', 'catatan_selisih_terima']);
        });
    }
};

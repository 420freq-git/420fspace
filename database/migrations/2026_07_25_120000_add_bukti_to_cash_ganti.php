<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund reject cash jadi 2 langkah ber-bukti:
 *  1. Diferd kembalikan uang ke 420F (`bukti_diferd`, `tgl_diferd`).
 *  2. 420F teruskan refund ke TM (`bukti_tm`, `tgl_tm`).
 * Saat `gantiCash` metode refund dipanggil hanya MENDEKLARASIKAN kewajiban (buat CashGanti);
 * ledger uang dicatat di masing-masing langkah di atas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_ganti', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_ganti', 'bukti_diferd')) {
                $table->string('bukti_diferd')->nullable()->after('keterangan');
                $table->timestamp('tgl_diferd')->nullable()->after('bukti_diferd');
                $table->string('bukti_tm')->nullable()->after('tgl_diferd');
                $table->timestamp('tgl_tm')->nullable()->after('bukti_tm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_ganti', function (Blueprint $table) {
            $table->dropColumn(['bukti_diferd', 'tgl_diferd', 'bukti_tm', 'tgl_tm']);
        });
    }
};

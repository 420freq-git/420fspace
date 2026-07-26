<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur cash jadi berbasis TAGIHAN (bukan auto-catat):
 * - `invoices.jenis` membedakan penjualan / buyout / cash (DP & pelunasan) supaya tampilan &
 *   perhitungan tak tertukar (buyout dan cash sama-sama pakai jumlah_manual).
 * - `vendor_ledger.bukti_transfer` menyimpan bukti tiap pembayaran 420F→Diferd (mis. cash DP/sisa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'jenis')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('jenis')->default('penjualan')->after('batch_id'); // penjualan | buyout | cash
            });
            // Backfill: invoice manual lama (jumlah_manual > 0) berasal dari buy-out.
            DB::table('invoices')->where('jumlah_manual', '>', 0)->update(['jenis' => 'buyout']);
        }

        if (! Schema::hasColumn('vendor_ledger', 'bukti_transfer')) {
            Schema::table('vendor_ledger', function (Blueprint $table) {
                $table->string('bukti_transfer')->nullable()->after('keterangan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'jenis')) {
            Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('jenis'));
        }
        if (Schema::hasColumn('vendor_ledger', 'bukti_transfer')) {
            Schema::table('vendor_ledger', fn (Blueprint $t) => $t->dropColumn('bukti_transfer'));
        }
    }
};

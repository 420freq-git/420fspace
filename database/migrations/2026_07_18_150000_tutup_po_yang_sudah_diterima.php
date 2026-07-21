<?php

use App\Enums\TahapProduksi;
use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aturan baru: PO ditutup begitu pengiriman pertamanya diterima brand, tanpa syarat terkirim
 * penuh — karena dalam alur produksi tidak ada sisa menganggur, qty yang tidak ikut dikirim
 * berarti reject. Migrasi sebelumnya hanya menutup PO yang terkirim penuh, jadi PO yang
 * terkirim sebagian masih tertinggal di siap_kirim. Ini menyelaraskannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sudahDiterima = DB::table('pengiriman_items as pi')
            ->join('pengiriman as p', 'pi.pengiriman_id', '=', 'p.id')
            ->where('p.status', 'diterima')
            ->select('p.batch_id', 'pi.product_id')
            ->distinct()->get();

        foreach ($sudahDiterima as $row) {
            PurchaseOrder::where('batch_id', $row->batch_id)
                ->where('product_id', $row->product_id)
                ->where('tahap', '!=', TahapProduksi::Terkirim->value)
                ->update(['tahap' => TahapProduksi::Terkirim->value]);
        }
    }

    public function down(): void
    {
        // Tahap sebelumnya tidak direkam per baris — tidak dibalik otomatis.
    }
};

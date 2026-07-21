<?php

use App\Enums\TahapProduksi;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Illuminate\Database\Migrations\Migration;

/**
 * Sebelum ada aturan "tahap otomatis jadi terkirim saat brand menerima barang", PO tetap
 * tertinggal di siap_kirim walau surat jalannya sudah diterima. Migrasi ini menyelaraskan data
 * lama dengan aturan baru: PO yang seluruh qty produksinya sudah dibuatkan surat jalan ditandai
 * terkirim. PO yang baru terkirim sebagian sengaja dibiarkan supaya sisanya masih bisa dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stock = app(StockService::class);

        $pos = PurchaseOrder::with('sizeItems')
            ->where('tahap', TahapProduksi::SiapKirim->value)
            ->get();

        foreach ($pos as $po) {
            $adaSisa = $po->sizeItems->contains(function ($si) use ($po, $stock) {
                return $stock->shippedInBatch($po->batch_id, $po->product_id, $si->ukuran->value)
                    < $stock->producedInBatch($po->batch_id, $po->product_id, $si->ukuran->value);
            });

            if (! $adaSisa && $po->sizeItems->isNotEmpty()) {
                $po->update(['tahap' => TahapProduksi::Terkirim->value]);
            }
        }
    }

    public function down(): void
    {
        // Tidak dibalik otomatis: tahap sebelumnya tidak direkam per baris.
    }
};

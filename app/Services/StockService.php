<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\TahapProduksi;
use App\Models\Batch;
use App\Models\PengirimanItem;
use App\Models\PoSizeItem;
use App\Models\PurchaseOrder;
use App\Models\Sale;

class StockService
{
    /** Kekurangan/cacat saat penerimaan (dikirim − diterima) untuk produk+ukuran di sebuah batch. */
    public function shortfallInBatch(int $batchId, int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereNotNull('qty_diterima')
            ->whereHas('pengiriman', fn ($q) => $q->where('batch_id', $batchId)->where('status', 'diterima'))
            ->get()->sum(fn ($i) => max(0, $i->qty - $i->qty_diterima));
    }

    /** Total kekurangan/cacat lintas batch untuk produk+ukuran. */
    public function shortfallTotal(int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereNotNull('qty_diterima')
            ->whereHas('pengiriman', fn ($q) => $q->where('status', 'diterima'))
            ->get()->sum(fn ($i) => max(0, $i->qty - $i->qty_diterima));
    }

    /** Qty diproduksi untuk produk+ukuran di sebuah batch (semua jenis dijumlah). */
    public function producedInBatch(int $batchId, int $productId, string $ukuran): int
    {
        return (int) PoSizeItem::whereHas('purchaseOrder', function ($q) use ($batchId, $productId) {
            $q->where('batch_id', $batchId)->where('product_id', $productId);
        })->where('ukuran', $ukuran)->sum('qty');
    }

    /** Qty terjual dari sebuah batch untuk produk+ukuran. */
    public function soldInBatch(int $batchId, int $productId, string $ukuran): int
    {
        return (int) Sale::where('batch_id', $batchId)
            ->where('product_id', $productId)->where('ukuran', $ukuran)->consuming()->sum('qty');
    }

    /** Qty yang sudah dibuatkan surat jalan di sebuah batch (dikirim maupun sudah diterima). */
    public function shippedInBatch(int $batchId, int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereHas('pengiriman', fn ($q) => $q->where('batch_id', $batchId))
            ->sum('qty');
    }

    /** Qty yang benar-benar diterima brand (dasar stok yang boleh dijual). */
    public function receivedInBatch(int $batchId, int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereHas('pengiriman', fn ($q) => $q->where('batch_id', $batchId)->where('status', 'diterima'))
            ->sum('qty_diterima');
    }

    /** Sudah dikirim Diferd tapi belum dikonfirmasi diterima — stok di jalan. */
    public function inTransitInBatch(int $batchId, int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereHas('pengiriman', fn ($q) => $q->where('batch_id', $batchId)->where('status', 'dikirim'))
            ->sum('qty');
    }

    /** PO produk ini di batch tsb sudah ditutup (selesai kirim)? */
    private function poTertutup(int $batchId, int $productId): bool
    {
        return PurchaseOrder::where('batch_id', $batchId)->where('product_id', $productId)
            ->where('tahap', TahapProduksi::Terkirim->value)->exists();
    }

    /**
     * Sudah diproduksi tapi belum dibuatkan surat jalan — masih menunggu di vendor.
     * Hanya berlaku selama PO belum ditutup; setelah ditutup selisihnya jadi reject, bukan stok.
     */
    public function unshippedInBatch(int $batchId, int $productId, string $ukuran): int
    {
        if ($this->poTertutup($batchId, $productId)) {
            return 0;
        }

        return max(0, $this->producedInBatch($batchId, $productId, $ukuran)
            - $this->shippedInBatch($batchId, $productId, $ukuran));
    }

    /**
     * Reject produksi = qty PO yang tidak pernah dikirim, dihitung setelah PO ditutup.
     * Dalam alur produksi tidak ada "sisa menganggur" — barang yang tidak ikut dikirim berarti
     * gagal QC. Kerugiannya ditanggung vendor, sama seperti kurang/cacat saat penerimaan.
     */
    public function rejectInBatch(int $batchId, int $productId, string $ukuran): int
    {
        if (! $this->poTertutup($batchId, $productId)) {
            return 0;
        }

        return max(0, $this->producedInBatch($batchId, $productId, $ukuran)
            - $this->shippedInBatch($batchId, $productId, $ukuran));
    }

    /**
     * Sisa stok yang benar-benar bisa dijual = yang sudah DITERIMA brand − terjual.
     *
     * Basisnya penerimaan, bukan produksi: barang yang masih di vendor atau sedang di jalan
     * belum ada di tangan brand, jadi belum boleh dijual. Kekurangan/cacat otomatis tidak ikut
     * karena yang dihitung qty_diterima, bukan qty dikirim. Retur kondisi baik kembali jadi stok
     * dengan sendirinya karena penjualannya berhenti dihitung oleh scope consuming().
     */
    public function availableInBatch(int $batchId, int $productId, string $ukuran): int
    {
        return $this->receivedInBatch($batchId, $productId, $ukuran)
            - $this->soldInBatch($batchId, $productId, $ukuran);
    }

    /** Total diproduksi lintas batch untuk produk+ukuran. */
    public function producedTotal(int $productId, string $ukuran): int
    {
        return (int) PoSizeItem::whereHas('purchaseOrder', function ($q) use ($productId) {
            $q->where('product_id', $productId);
        })->where('ukuran', $ukuran)->sum('qty');
    }

    /** Total terjual untuk produk+ukuran. */
    public function soldTotal(int $productId, string $ukuran): int
    {
        return (int) Sale::where('product_id', $productId)->where('ukuran', $ukuran)->consuming()->sum('qty');
    }

    /** Total diterima brand lintas batch. */
    public function receivedTotal(int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereHas('pengiriman', fn ($q) => $q->where('status', 'diterima'))
            ->sum('qty_diterima');
    }

    /** Total sudah dibuatkan surat jalan lintas batch. */
    public function shippedTotal(int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)->sum('qty');
    }

    /** Total di jalan dari Diferd (dikirim, belum diterima). */
    public function inTransitTotal(int $productId, string $ukuran): int
    {
        return (int) PengirimanItem::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereHas('pengiriman', fn ($q) => $q->where('status', 'dikirim'))
            ->sum('qty');
    }

    /** Total masih menunggu di vendor (PO belum ditutup), hanya batch yang masih berjalan. */
    public function unshippedTotal(int $productId, string $ukuran): int
    {
        return $this->perBatch($productId, fn ($batchId) => $this->unshippedInBatch($batchId, $productId, $ukuran), true);
    }

    /**
     * Total reject produksi untuk batch yang MASIH BERJALAN.
     *
     * Batch yang sudah lunas tidak ikut: rejectnya sudah selesai diperhitungkan saat settlement,
     * jadi tidak perlu terus menumpuk di monitor stok berjalan. Catatan kerugiannya tetap utuh —
     * Laporan Kerugian memanggil rejectInBatch() langsung, tanpa filter status batch.
     */
    public function rejectTotal(int $productId, string $ukuran): int
    {
        return $this->perBatch($productId, fn ($batchId) => $this->rejectInBatch($batchId, $productId, $ukuran), true);
    }

    /** Reject dari batch yang sudah selesai — nilainya diarsipkan, bukan lagi stok berjalan. */
    public function rejectSelesaiTotal(int $productId, string $ukuran): int
    {
        return $this->perBatch($productId, fn ($batchId) => $this->rejectInBatch($batchId, $productId, $ukuran), false, true);
    }

    /**
     * Jumlahkan sebuah perhitungan per batch yang punya PO untuk produk ini (batch unik).
     * $hanyaAktif / $hanyaSelesai menyaring berdasarkan status batch.
     */
    private function perBatch(int $productId, callable $hitung, bool $hanyaAktif = false, bool $hanyaSelesai = false): int
    {
        $batchIds = PurchaseOrder::where('product_id', $productId)->distinct()->pluck('batch_id');

        if ($hanyaAktif || $hanyaSelesai) {
            $status = $hanyaAktif ? BatchStatus::Aktif->value : BatchStatus::Lunas->value;
            $batchIds = Batch::whereIn('id', $batchIds)->where('status', $status)->pluck('id');
        }

        return (int) $batchIds->sum(fn ($batchId) => $hitung((int) $batchId));
    }

    /**
     * Terjual tapi pesanannya belum cair — barang sudah keluar ke pembeli, uangnya belum masuk.
     * Bukan stok yang bisa dijual lagi, tapi perlu dipantau karena masih menggantung.
     */
    public function soldUnsettledTotal(int $productId, string $ukuran): int
    {
        return (int) Sale::where('product_id', $productId)->where('ukuran', $ukuran)
            ->consuming()
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'lunas'))
            ->sum('qty');
    }

    /** Total sisa stok lintas batch untuk produk+ukuran (brand tertentu). */
    public function availableTotal(int $brandId, int $productId, string $ukuran): int
    {
        $total = 0;
        foreach ($this->brandBatches($brandId) as $batch) {
            $total += max(0, $this->availableInBatch($batch->id, $productId, $ukuran));
        }

        return $total;
    }

    /**
     * Alokasi FIFO: bagi qty ke batch tertua yang masih punya stok.
     * @return array{alloc: array<int, array{batch: Batch, qty: int}>, remaining: int}
     */
    public function allocate(int $brandId, int $productId, string $ukuran, int $qty): array
    {
        $alloc = [];
        $remaining = $qty;

        foreach ($this->brandBatches($brandId) as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $avail = $this->availableInBatch($batch->id, $productId, $ukuran);
            if ($avail <= 0) {
                continue;
            }
            $take = min($avail, $remaining);
            $alloc[] = ['batch' => $batch, 'qty' => $take];
            $remaining -= $take;
        }

        return ['alloc' => $alloc, 'remaining' => $remaining];
    }

    /** @return \Illuminate\Support\Collection<int, Batch> */
    private function brandBatches(int $brandId)
    {
        return Batch::where('brand_id', $brandId)
            ->orderBy('tanggal_order')->orderBy('id')->get();
    }
}

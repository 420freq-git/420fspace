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
     * Qty se-batch yang masih menunggu surat jalan (produced − shipped) pada PO yang BELUM ditutup.
     * PO bertahap `terkirim` dilewati: sisa tak-terkirimnya adalah reject final, bukan antrean kirim.
     *
     * Dipakai monitoring produksi untuk menentukan batch "selesai" (semua PO siap_kirim DAN
     * tak ada lagi yang menunggu dikirim). Ditaruh di sini supaya MonitoringProduksiController dan
     * PurchaseOrderController (respons AJAX ganti tahap) memakai definisi yang sama persis.
     */
    public function menungguKirimBatch(Batch $batch): int
    {
        $batch->loadMissing('purchaseOrders.sizeItems');

        $kombinasi = [];
        foreach ($batch->purchaseOrders as $po) {
            if ($po->tahap === TahapProduksi::Terkirim) {
                continue;
            }
            foreach ($po->sizeItems as $si) {
                $kombinasi[$po->product_id.'|'.$si->ukuran->value] = [$po->product_id, $si->ukuran->value];
            }
        }

        $total = 0;
        foreach ($kombinasi as [$pid, $uk]) {
            $total += max(0, $this->producedInBatch($batch->id, (int) $pid, $uk)
                - $this->shippedInBatch($batch->id, (int) $pid, $uk));
        }

        return $total;
    }

    /**
     * Pergerakan stok se-batch (basis penerimaan): diterima, terjual, dan sisa (belum terjual).
     * diterima = terjual + sisa (sisa = availableInBatch). Dipakai Radar & bisa dipakai halaman lain
     * yang ingin menampilkan "terjual X dari Y diterima" per batch — satu definisi, konsisten.
     *
     * @return array{diterima:int, terjual:int, sisa:int}
     */
    public function pergerakanBatch(Batch $batch): array
    {
        $batch->loadMissing('purchaseOrders.sizeItems');

        $combos = [];
        foreach ($batch->purchaseOrders as $po) {
            foreach ($po->sizeItems as $si) {
                $combos[$po->product_id.'|'.$si->ukuran->value] = [$po->product_id, $si->ukuran->value];
            }
        }

        $diterima = 0;
        $terjual = 0;
        foreach ($combos as [$pid, $uk]) {
            $diterima += $this->receivedInBatch($batch->id, (int) $pid, $uk);
            $terjual += $this->soldInBatch($batch->id, (int) $pid, $uk);
        }

        return ['diterima' => $diterima, 'terjual' => $terjual, 'sisa' => max(0, $diterima - $terjual)];
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
        // Stok yang sudah "keluar" dari sistem tidak lagi punya stok jual:
        //   - buy-out (sisa stok jadi milik TM420 di tengah/akhir masa jual), atau
        //   - batch cash yang sudah dibayar lunas di muka (beli putus, langsung milik TM420).
        if ($this->batchStokKeluar($batchId)) {
            return 0;
        }

        return $this->receivedInBatch($batchId, $productId, $ukuran)
            - $this->soldInBatch($batchId, $productId, $ukuran);
    }

    /** @var array<int,bool>|null memo batch yang stoknya sudah keluar sistem (buy-out / cash lunas) */
    private ?array $stokKeluarMap = null;

    /**
     * @return array<int,bool> id batch => true untuk batch yang stoknya sudah keluar dari sistem
     *                         (dibuyout ATAU cash yang sudah dibayar lunas di muka).
     */
    private function stokKeluarBatches(): array
    {
        return $this->stokKeluarMap ??= Batch::where('dibuyout', true)
            ->orWhere('cash_dibayar', true)
            ->pluck('id')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    private function batchStokKeluar(int $batchId): bool
    {
        return isset($this->stokKeluarBatches()[$batchId]);
    }

    /**
     * Qty yang stoknya sudah keluar sistem untuk produk+ukuran lintas batch (received − sold pada
     * batch yang dibuyout atau cash-lunas). Dipakai halaman Stok agar stok jual tidak menampilkannya.
     */
    public function boughtOutTotal(int $productId, string $ukuran): int
    {
        $total = 0;
        foreach (array_keys($this->stokKeluarBatches()) as $batchId) {
            $total += max(0, $this->receivedInBatch($batchId, $productId, $ukuran) - $this->soldInBatch($batchId, $productId, $ukuran));
        }

        return $total;
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
     * Kurang/cacat saat penerimaan (dikirim − diterima) pada batch BERJALAN, per produk+ukuran.
     * Sama pola aktif/arsip seperti reject produksi — ini juga kerugian vendor dan harus tampil di
     * monitor stok, bukan hanya di Laporan Kerugian. Kalau tidak, penerimaan-kurang seolah hilang.
     */
    public function shortfallActiveTotal(int $productId, string $ukuran): int
    {
        return $this->perBatch($productId, fn ($batchId) => $this->shortfallInBatch($batchId, $productId, $ukuran), true);
    }

    /** Kurang/cacat penerimaan dari batch yang sudah selesai (diarsipkan). */
    public function shortfallSelesaiTotal(int $productId, string $ukuran): int
    {
        return $this->perBatch($productId, fn ($batchId) => $this->shortfallInBatch($batchId, $productId, $ukuran), false, true);
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
    /**
     * "Terjual belum cair" = barang sudah terjual dan sedang MENUNGGU pencairan marketplace —
     * yaitu pesanan berstatus `dipesan`/`dikirim` (sama dengan daftar "perlu dicek" di Monitoring).
     *
     * Dulu memakai `status != 'lunas'`, sehingga retur berkondisi `rusak` (order jadi `batal`)
     * ikut terhitung. Itu keliru: retur rusak adalah KERUGIAN brand yang ditagih lewat invoice,
     * bukan pesanan yang sedang menunggu dana cair — dan karena order-nya `batal` ia tak akan
     * pernah jadi `lunas`, jadi angkanya menggantung selamanya (kasus Smiley).
     */
    public function soldUnsettledTotal(int $productId, string $ukuran): int
    {
        return (int) Sale::where('product_id', $productId)->where('ukuran', $ukuran)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['dipesan', 'dikirim']))
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

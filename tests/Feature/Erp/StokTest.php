<?php

namespace Tests\Feature\Erp;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ErpTestCase;

class StokTest extends ErpTestCase
{
    public function test_stok_jual_diterima_minus_terjual(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);

        $this->assertSame(10, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));

        $this->jual($this->produkTm, 'M', 3);
        $this->assertSame(7, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
    }

    public function test_reject_saat_terima_ditanggung_vendor_kurangi_stok(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        // Terima 8 dari 10 (2 reject/kurang).
        $this->produksiTerima($batch, [$this->produkTm->id.'|M' => 8]);

        // Stok jual berbasis penerimaan: hanya 8.
        $this->assertSame(8, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
    }

    /**
     * Kurang saat penerimaan harus muncul di kolom "Reject/kurang" halaman Stok — bukan cuma di
     * Laporan Kerugian. Diuji untuk KEDUA brand: POV brand TM & VOOJAH identik kecuali harga, jadi
     * bug/fix di satu brand berlaku untuk keduanya (aturan kerja user, 24 Jul 2026).
     *
     */
    #[DataProvider('brandProvider')]
    public function test_kurang_saat_terima_muncul_di_reject_stok(string $produkProp): void
    {
        $produk = $this->{$produkProp};
        $batch = $this->batchAktif($produk, ['M' => 10]);
        $this->produksiTerima($batch, [$produk->id.'|M' => 8]);

        $st = $this->stock();
        $this->assertSame(2, $st->shortfallInBatch($batch->id, $produk->id, 'M'));

        $rejectStok = $st->rejectTotal($produk->id, 'M') + $st->shortfallActiveTotal($produk->id, 'M');
        $this->assertSame(2, $rejectStok, "Kurang saat penerimaan harus terlihat di monitor stok ({$produkProp}).");
    }

    public static function brandProvider(): array
    {
        return [
            'TM420' => ['produkTm'],
            'VOOJAH' => ['produkVoojah'],
        ];
    }

    public function test_reject_stok_cocok_dengan_laporan_kerugian(): void
    {
        // Angka kerugian vendor harus konsisten antara halaman Stok & Laporan Kerugian (admin POV).
        $batch = $this->batchAktif($this->produkTm, ['M' => 10, 'L' => 10]);
        $this->produksiTerima($batch, [
            $this->produkTm->id.'|M' => 8,   // kurang 2
            $this->produkTm->id.'|L' => 7,   // kurang 3
        ]);

        $st = $this->stock();
        $rejectStok = 0;
        foreach (['M', 'L', 'XL'] as $u) {
            $rejectStok += $st->rejectTotal($this->produkTm->id, $u)
                + $st->shortfallActiveTotal($this->produkTm->id, $u)
                + $st->rejectSelesaiTotal($this->produkTm->id, $u)
                + $st->shortfallSelesaiTotal($this->produkTm->id, $u);
        }

        // Laporan Kerugian (produksi) menjumlahkan reject + kurang terima tanpa filter status batch.
        $kerugianQty = (int) $st->shortfallTotal($this->produkTm->id, 'M')
            + (int) $st->shortfallTotal($this->produkTm->id, 'L');

        $this->assertSame(5, $rejectStok);
        $this->assertSame(5, $kerugianQty, 'Stok & Kerugian harus menunjukkan angka vendor loss yang sama.');
    }

    public function test_stok_belum_diterima_bukan_stok_jual(): void
    {
        // Batch aktif tapi belum ada penerimaan → stok jual 0 (walau qty PO 10).
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->assertSame(0, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
    }

    public function test_terjual_belum_cair_hanya_pesanan_menunggu_pencairan(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);

        // Pesanan marketplace belum cair (status dipesan) → masuk "belum cair".
        $order = $this->jual($this->produkTm, 'M', 3);
        $order->update(['status' => 'dipesan']);
        $this->assertSame(3, $this->stock()->soldUnsettledTotal($this->produkTm->id, 'M'));

        // Setelah cair (lunas) → keluar dari "belum cair".
        $order->update(['status' => 'lunas']);
        $this->assertSame(0, $this->stock()->soldUnsettledTotal($this->produkTm->id, 'M'));
    }

    public function test_retur_rusak_tidak_muncul_sebagai_belum_cair(): void
    {
        // Kasus Smiley: barang diretur & rusak (tidak layak jual). Order jadi `batal` dan tak
        // akan pernah `lunas` — jadi tak boleh menggantung sebagai "terjual belum cair". Itu
        // kerugian brand yang ditagih lewat invoice, bukan pesanan menunggu pencairan.
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 1);

        $order->update(['status' => 'retur']);
        app(\App\Http\Controllers\MonitoringController::class)
            ->terimaRetur($this->req($this->tm, ['kondisi' => 'rusak', 'alasan_rusak' => 'sobek']), $order->fresh());

        $this->assertSame('batal', $order->fresh()->status->value);
        $this->assertSame(0, $this->stock()->soldUnsettledTotal($this->produkTm->id, 'M'));

        // Barang rusak tetap keluar dari stok jual (dikonsumsi).
        $this->assertSame(9, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
    }
}

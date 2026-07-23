<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\MonitoringController;
use Tests\ErpTestCase;

class ReturTest extends ErpTestCase
{
    public function test_retur_layak_stok_kembali_tanpa_hak(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 2);   // sisa 3

        $this->assertSame(3, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));

        $mc = app(MonitoringController::class);
        $mc->tolakRetur($this->req($this->admin, ['alasan_batal' => 'ditolak pembeli']), $order->fresh());
        $mc->terimaRetur($this->req($this->admin, ['kondisi' => 'layak']), $order->fresh());

        // Kondisi layak → stok kembali (5), tak jadi kewajiban bayar.
        $this->assertSame(5, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
        $this->assertSame(0, $this->settlement()->hakJual($batch->id));
    }

    public function test_retur_rusak_stok_hilang_brand_tetap_bayar(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 2);

        $mc = app(MonitoringController::class);
        $mc->tolakRetur($this->req($this->admin, []), $order->fresh());
        $mc->terimaRetur($this->req($this->admin, ['kondisi' => 'rusak', 'alasan_rusak' => 'sobek']), $order->fresh());

        // Kondisi rusak → stok tetap hilang (3), brand tetap bayar produksi (hak 2×60.000).
        $this->assertSame(3, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
        $this->assertSame(120000, $this->settlement()->hakJual($batch->id));
    }
}

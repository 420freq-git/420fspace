<?php

namespace Tests\Feature\Erp;

use App\Models\BrandLedger;
use App\Models\VendorLedger;
use Tests\ErpTestCase;

class CashTest extends ErpTestCase
{
    public function test_batch_cash_dibayar_penuh_di_muka_dan_stok_hilang(): void
    {
        // Batch cash 10 M → disetujui = bayar penuh di muka.
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');

        $this->assertTrue((bool) $batch->fresh()->cash_dibayar);

        // Diferd dibayar 10 x 60.000; TM ke 420F 10 x 70.000.
        $vlCash = (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'cash')->sum('jumlah');
        $blCash = (int) BrandLedger::where('keterangan', 'like', 'Cash batch%')->sum('jumlah');
        $this->assertSame(600000, $vlCash);
        $this->assertSame(700000, $blCash);

        // Setelah produksi & terima, stok jual tetap 0 (beli putus, keluar sistem).
        $this->produksiTerima($batch);
        $this->assertSame(0, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));

        // Margin 420F dari cash = 10 x 10.000.
        $summary = $this->settlement()->batchSummary($batch->fresh());
        $this->assertSame(100000, $summary['fee420f']);
        $this->assertSame(0, $summary['saldo']);
    }
}

<?php

namespace Tests\Feature\Erp;

use App\Models\Invoice;
use App\Models\VendorLedger;
use Tests\ErpTestCase;

class BuyoutTest extends ErpTestCase
{
    public function test_buyout_terbit_invoice_dan_menambah_hak_diferd(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 3);   // sisa 7

        $sc = app(\App\Http\Controllers\SettlementController::class);
        $sc->buyout($this->req($this->admin, [], 'PATCH'), $batch->fresh());

        $batch->refresh();
        $this->assertTrue((bool) $batch->dibuyout);

        // Invoice buy-out ke TM di harga tm420: 7 x 70.000.
        $inv = Invoice::where('batch_id', $batch->id)->where('jumlah_manual', '>', 0)->first();
        $this->assertNotNull($inv);
        $this->assertSame(490000, (int) $inv->jumlah_manual);
        $this->assertSame(7, (int) $inv->pcs_manual);

        // Hak Diferd bertambah di harga diferd: 7 x 60.000.
        $this->assertSame(420000, (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'buyout')->sum('jumlah'));

        // Stok jual habis (jadi milik TM420).
        $this->assertSame(0, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));

        // Fee 420F = margin jual (3 x 10.000) + margin buy-out (490.000 - 420.000).
        $this->assertSame(30000 + 70000, $this->settlement()->fee420f($batch->id));
    }
}

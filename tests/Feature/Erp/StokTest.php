<?php

namespace Tests\Feature\Erp;

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

    public function test_stok_belum_diterima_bukan_stok_jual(): void
    {
        // Batch aktif tapi belum ada penerimaan → stok jual 0 (walau qty PO 10).
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->assertSame(0, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
    }
}

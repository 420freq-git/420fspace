<?php

namespace Tests\Feature\Erp;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Tests\ErpTestCase;

class UangKonsinyasiTest extends ErpTestCase
{
    public function test_penjualan_konsinyasi_hak_tagihan_fee(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 4);

        // Hak Diferd = 4 x 60.000
        $this->assertSame(240000, $this->settlement()->hakJual($batch->id));

        // Tagihan TM = 4 x 70.000 (snapshot di sale)
        $tagihan = (int) Sale::where('batch_id', $batch->id)->sold()->consignment()
            ->sum(DB::raw('qty * harga_tm420'));
        $this->assertSame(280000, $tagihan);

        // Fee 420F = 4 x (70.000 - 60.000)
        $this->assertSame(40000, $this->settlement()->fee420f($batch->id));
    }

    public function test_voojah_ditagih_harga_diferd_fee_nol(): void
    {
        $batch = $this->batchAktif($this->produkVoojah, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkVoojah, 'M', 3);

        // Snapshot sale VOOJAH: harga_tm420 == harga_diferd (ditagih modal).
        $sale = Sale::where('batch_id', $batch->id)->first();
        $this->assertSame(60000, (int) $sale->harga_diferd);
        $this->assertSame(60000, (int) $sale->harga_tm420);

        // Fee 420F dari VOOJAH = 0.
        $this->assertSame(0, $this->settlement()->fee420f($batch->id));
    }
}

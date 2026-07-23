<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\PenarikanController;
use App\Http\Controllers\SettlementController;
use App\Models\Penarikan;
use App\Models\VendorLedger;
use Illuminate\Validation\ValidationException;
use Tests\ErpTestCase;

class PenarikanTest extends ErpTestCase
{
    public function test_penarikan_dibekukan_fifo_ke_batch_tertua_dulu(): void
    {
        $batchA = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batchA);
        $this->jual($this->produkTm, 'M', 5);   // hak A = 300.000

        $batchB = $this->batchAktif($this->produkTm2, ['M' => 5]);
        $this->produksiTerima($batchB);
        $this->jual($this->produkTm2, 'M', 5);  // hak B = 300.000

        // Tarik 400.000 → FIFO: batch A (tertua) 300.000 penuh, batch B 100.000.
        $pc = app(PenarikanController::class);
        $pc->store($this->req($this->diferd, ['jumlah' => 400000]));
        $pen = Penarikan::latest('id')->first();
        $pc->approve($this->req($this->admin), $pen);

        $bekuA = (int) VendorLedger::where('batch_id', $batchA->id)->where('tipe', 'pembayaran')->where('penarikan_id', $pen->id)->sum('jumlah');
        $bekuB = (int) VendorLedger::where('batch_id', $batchB->id)->where('tipe', 'pembayaran')->where('penarikan_id', $pen->id)->sum('jumlah');
        $this->assertSame(300000, $bekuA);
        $this->assertSame(100000, $bekuB);
    }

    public function test_penarikan_tak_boleh_melebihi_saldo(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 5);   // hak 300.000

        $this->expectException(ValidationException::class);
        app(PenarikanController::class)->store($this->req($this->diferd, ['jumlah' => 500000]));
    }

    public function test_hak_buyout_ikut_bisa_ditarik(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 2);   // sisa 8
        app(SettlementController::class)->buyout($this->req($this->admin, [], 'PATCH'), $batch->fresh());

        // Hak global = jual 2×60.000 + buyout 8×60.000 = 600.000.
        $pc = app(PenarikanController::class);
        $m = new \ReflectionMethod($pc, 'saldo');
        $m->setAccessible(true);
        $this->assertSame(600000, $m->invoke($pc)['hak']);
    }
}

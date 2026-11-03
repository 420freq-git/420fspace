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

    public function test_pdf_bukti_bayar_hanya_untuk_penarikan_cair(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 3);

        $pc = app(PenarikanController::class);
        $pc->store($this->req($this->diferd, ['jumlah' => 100000]));
        $pen = Penarikan::latest('id')->first();

        try {
            $pc->pdf($pen);
            $this->fail('penarikan belum cair tak boleh punya bukti bayar');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $pc->approve($this->req($this->admin), $pen);
        $res = $pc->pdf($pen->fresh());
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_rincian_hak_per_brand_artikel_cocok_dengan_saldo(): void
    {
        $bTm = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($bTm);
        $this->jual($this->produkTm, 'M', 3);      // TM / Kaos TM: 3 × 60.000 = 180.000

        $bVj = $this->batchAktif($this->produkVoojah, ['M' => 5]);
        $this->produksiTerima($bVj);
        $this->jual($this->produkVoojah, 'M', 2);  // VOOJAH / Kaos VOOJAH: 2 × 60.000 = 120.000

        // Tarik 200.000 → FIFO: batch TM (tertua) 180.000, batch VOOJAH 20.000.
        $pc = app(PenarikanController::class);
        $pc->store($this->req($this->diferd, ['jumlah' => 200000]));
        $pc->approve($this->req($this->admin), Penarikan::latest('id')->first());

        $rincian = (new \ReflectionMethod($pc, 'rincianHak'))->invoke($pc)['rincianBrand']->keyBy('nama');
        $saldo = (new \ReflectionMethod($pc, 'saldo'))->invoke($pc);

        $this->assertSame(180000, $rincian['TM420']['hak']);
        $this->assertSame(180000, $rincian['TM420']['dibayar']);
        $this->assertSame(0, $rincian['TM420']['sisa']);
        $this->assertSame(3, (int) $rincian['TM420']['artikel']->first()->pcs);
        $this->assertSame($this->produkTm->id, $rincian['TM420']['artikel']->first()->product_id);

        $this->assertSame(120000, $rincian['VOOJAH']['hak']);
        $this->assertSame(20000, $rincian['VOOJAH']['dibayar']);
        $this->assertSame(100000, $rincian['VOOJAH']['sisa']);

        // Total rincian harus sama dengan kartu saldo (angka kunci konsisten).
        $this->assertSame($saldo['hak'], $rincian->sum('hak'));
        $this->assertSame($saldo['dibayar'], $rincian->sum('dibayar'));
    }
}

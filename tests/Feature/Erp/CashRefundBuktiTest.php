<?php

namespace Tests\Feature\Erp;

use App\Models\BrandLedger;
use App\Models\CashGanti;
use App\Models\Invoice;
use App\Models\VendorLedger;
use Tests\ErpTestCase;

/**
 * Refund reject cash = 2 langkah TERPISAH ber-bukti (konsisten dgn alur cash baru):
 *  1. Diferd kembalikan ke 420F (+bukti) → VendorLedger negatif.
 *  2. 420F teruskan ke TM (+bukti) → BrandLedger negatif.
 * `gantiCash` refund hanya mendeklarasikan; uang belum bergerak sampai kedua langkah dijalankan.
 */
class CashRefundBuktiTest extends ErpTestCase
{
    private function batchCashRejectSiapRefund(): \App\Models\Batch
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');
        $inv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail();
        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);
        $this->produksiTerima($batch->fresh(), [$this->produkTm->id.'|M' => 8]);   // 2 reject

        return $batch->fresh();
    }

    public function test_deklarasi_refund_belum_gerakkan_uang(): void
    {
        $batch = $this->batchCashRejectSiapRefund();
        $vendorSebelum = (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'cash')->sum('jumlah');

        app(\App\Http\Controllers\SettlementController::class)
            ->gantiCash($this->req($this->admin, ['metode' => 'refund', 'pcs' => 2], 'POST'), $batch);

        $rg = CashGanti::where('batch_id', $batch->id)->where('metode', 'refund')->firstOrFail();
        $this->assertSame(120000, $rg->nilai_diferd);   // 2 × 60.000
        $this->assertSame(140000, $rg->nilai_tm420);    // 2 × 70.000
        $this->assertFalse($rg->diferdSudahKembalikan());
        $this->assertFalse($rg->refundTuntas());

        // Belum ada uang bergerak (baru deklarasi).
        $this->assertSame($vendorSebelum, (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'cash')->sum('jumlah'));
    }

    public function test_dua_langkah_refund_gerakkan_uang_dan_tuntas(): void
    {
        $batch = $this->batchCashRejectSiapRefund();
        $sc = app(\App\Http\Controllers\SettlementController::class);
        $sc->gantiCash($this->req($this->admin, ['metode' => 'refund', 'pcs' => 2], 'POST'), $batch);
        $rg = CashGanti::where('batch_id', $batch->id)->where('metode', 'refund')->firstOrFail();

        $vendorSebelum = (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'cash')->sum('jumlah');
        $brandSebelum = (int) BrandLedger::sum('jumlah');

        // Tak boleh teruskan ke TM sebelum Diferd kembalikan.
        $sc->refundTeruskanTm($this->req($this->admin, [], 'POST'), $rg);
        $this->assertFalse($rg->fresh()->sudahDiteruskanTm());

        // Langkah 1: Diferd kembalikan → VendorLedger −120.000.
        $sc->refundDiferdMasuk($this->req($this->admin, [], 'POST'), $rg->fresh());
        $this->assertTrue($rg->fresh()->diferdSudahKembalikan());
        $this->assertSame($vendorSebelum - 120000, (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'cash')->sum('jumlah'));

        // Langkah 2: 420F teruskan ke TM → BrandLedger −140.000.
        $sc->refundTeruskanTm($this->req($this->admin, [], 'POST'), $rg->fresh());
        $this->assertTrue($rg->fresh()->sudahDiteruskanTm());
        $this->assertTrue($rg->fresh()->refundTuntas());
        $this->assertSame($brandSebelum - 140000, (int) BrandLedger::sum('jumlah'));
    }
}

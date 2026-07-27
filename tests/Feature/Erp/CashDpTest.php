<?php

namespace Tests\Feature\Erp;

use App\Models\Batch;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Tests\ErpTestCase;

/**
 * Batch cash DP — alur TAGIHAN bertahap:
 *  TM: invoice DP (saat disetujui) → invoice PELUNASAN (setelah TM TERIMA barang, reject dipotong).
 *  Diferd: 420F bayar DP-modal dulu, sisa-modal (net reject) setelah barang diterima.
 * Reject di batch DP TIDAK lewat refund — otomatis dipotong dari pelunasan.
 */
class CashDpTest extends ErpTestCase
{
    /** Bangun batch cash DP (DP = NOMINAL Rp) tanpa approve. */
    private function batchCashDpTanpaApprove(int $dpNominal, array $qty): Batch
    {
        $bc = app(\App\Http\Controllers\BatchController::class);
        $poc = app(\App\Http\Controllers\PurchaseOrderController::class);

        $bc->store($this->req($this->tm, [
            'brand_id' => $this->brandTm->id, 'tanggal_order' => now()->subDays(5)->toDateString(),
            'jenis_order' => 'full_order', 'type_payment' => 'cash', 'dp_nominal' => $dpNominal,
        ]));
        $batch = Batch::latest('id')->first();
        $batch->update(['deadline' => now()->addDays(30)->toDateString(), 'deadline_produksi' => now()->addDays(10)->toDateString()]);

        $poQty = [];
        foreach ($qty as $uk => $n) {
            $poQty[$uk] = ['pendek' => $n];
        }
        $poc->store($this->req($this->tm, ['product_id' => $this->produkTm->id, 'qty' => $poQty]), $batch);

        return $batch->fresh();
    }

    private function batchCashDp(int $dpNominal, array $qty): Batch
    {
        $batch = $this->batchCashDpTanpaApprove($dpNominal, $qty);
        app(\App\Http\Controllers\BatchController::class)->approve($this->req($this->admin), $batch);

        return $batch->fresh();
    }

    private function bayarInvoice(Invoice $inv): void
    {
        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);
    }

    private function terbitSisa(Batch $batch): void
    {
        app(\App\Http\Controllers\SettlementController::class)->lunasiSisaCash($this->req($this->admin, [], 'PATCH'), $batch->fresh());
    }

    public function test_setuju_dp_terbit_invoice_dp_saja(): void
    {
        // 10 M → total TM 700.000. DP nominal Rp 350.000 → invoice DP 350.000.
        $batch = $this->batchCashDp(350000, ['M' => 10]);
        $inv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->get();
        $this->assertCount(1, $inv);
        $this->assertSame(350000, (int) $inv->first()->total);
    }

    public function test_dp_nominal_melebihi_total_ditolak(): void
    {
        // Total TM 700.000. DP 800.000 (≥ total) → approve ditolak: status tetap menunggu, tak ada invoice.
        $batch = $this->batchCashDpTanpaApprove(800000, ['M' => 10]);
        app(\App\Http\Controllers\BatchController::class)->approve($this->req($this->admin), $batch);

        $this->assertSame('menunggu', $batch->fresh()->status->value, 'Batch tak boleh disetujui saat DP ≥ total.');
        $this->assertCount(0, Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->get());
    }

    public function test_pelunasan_baru_terbit_setelah_diterima(): void
    {
        $batch = $this->batchCashDp(350000, ['M' => 10]);
        $this->bayarInvoice(Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail());

        // Baru siap kirim (belum diterima) → pelunasan BELUM boleh terbit.
        $poc = app(\App\Http\Controllers\PurchaseOrderController::class);
        foreach (PurchaseOrder::where('batch_id', $batch->id)->get() as $po) {
            $poc->updateStatus($this->req($this->admin, ['tahap' => 'siap_kirim'], 'PATCH'), $batch, $po);
        }
        $this->terbitSisa($batch);
        $this->assertCount(1, Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->get(), 'Belum boleh terbit sebelum diterima.');

        // Setelah diterima penuh → pelunasan terbit senilai sisa (350.000).
        $this->produksiTerima($batch->fresh());
        $this->terbitSisa($batch);
        $inv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->orderBy('id')->get();
        $this->assertCount(2, $inv);
        $this->assertSame(350000, (int) $inv->last()->total);
    }

    public function test_reject_dp_dipotong_dari_pelunasan(): void
    {
        // 10 M, DP 50%. Terima 8 (2 reject). Pelunasan = sisa 350.000 − reject 2×70.000 = 210.000.
        $batch = $this->batchCashDp(350000, ['M' => 10]);
        $this->bayarInvoice(Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail());
        $this->produksiTerima($batch->fresh(), [$this->produkTm->id.'|M' => 8]);

        $this->terbitSisa($batch);
        $sisaInv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->orderBy('id')->get()->last();
        $this->assertSame(210000, (int) $sisaInv->total, 'Pelunasan = sisa − nilai reject.');

        // Diferd: DP-modal 300.000 + sisa-modal net reject (300.000 − 120.000 = 180.000) = 480.000.
        $sc = app(\App\Http\Controllers\SettlementController::class);
        $sc->bayarDiferdCash($this->req($this->admin, ['nominal' => 300000], 'POST'), $batch->fresh());   // DP-modal (nominal manual)
        $sc->bayarDiferdCash($this->req($this->admin, ['nominal' => 180000], 'POST'), $batch->fresh());   // sisa-modal net reject
        $this->assertSame(480000, $this->settlement()->diferdCashDibayar($batch->fresh()), 'Diferd dibayar untuk pcs yang sampai saja.');

        // TM bayar pelunasan → batch lunas.
        $this->bayarInvoice($sisaInv);
        $this->assertTrue((bool) $batch->fresh()->cash_dibayar);
    }

    public function test_reject_dp_tak_pakai_alur_refund_manual(): void
    {
        $batch = $this->batchCashDp(350000, ['M' => 10]);
        $this->bayarInvoice(Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail());
        $this->produksiTerima($batch->fresh(), [$this->produkTm->id.'|M' => 8]);

        // Ganti manual ditolak untuk batch DP.
        app(\App\Http\Controllers\SettlementController::class)
            ->gantiCash($this->req($this->admin, ['metode' => 'refund', 'pcs' => 2], 'POST'), $batch->fresh());
        $this->assertSame(0, \App\Models\CashGanti::where('batch_id', $batch->id)->count());
    }

    public function test_dp_plus_sisa_selalu_sama_dengan_total(): void
    {
        $batch = $this->batchCashDp(400000, ['M' => 10, 'L' => 5]);
        $split = $this->settlement()->cashDpSplit($batch);
        $total = $this->settlement()->cashTotals($batch);
        $this->assertSame($total['diferd'], $split['dp']['diferd'] + $split['sisa']['diferd']);
        $this->assertSame($total['tm420'], $split['dp']['tm420'] + $split['sisa']['tm420']);
    }
}

<?php

namespace Tests\Feature\Erp;

use App\Models\BrandLedger;
use App\Models\Invoice;
use App\Models\VendorLedger;
use Tests\ErpTestCase;

/**
 * Batch cash (beli putus) — ALUR TAGIHAN (bukan auto-catat).
 * Saat disetujui: terbit INVOICE tagihan ke TM. Uang masuk saat invoice ditandai lunas.
 * 420F bayar Diferd (modal) lewat aksi terpisah + bukti. Stok keluar sistem saat TM lunas penuh.
 */
class CashTest extends ErpTestCase
{
    public function test_setuju_cash_menerbitkan_invoice_bukan_catat_uang(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');

        // Belum ada uang tercatat.
        $this->assertSame(0, (int) BrandLedger::sum('jumlah'), 'TM belum bayar — belum ada uang masuk.');
        $this->assertSame(0, (int) VendorLedger::where('tipe', 'cash')->sum('jumlah'), 'Diferd belum dibayar.');
        $this->assertFalse((bool) $batch->fresh()->cash_dibayar);

        // Invoice cash terbit ke TM: 10 × 70.000 = 700.000.
        $inv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->first();
        $this->assertNotNull($inv);
        $this->assertSame(700000, (int) $inv->total);
    }

    public function test_tm_bayar_invoice_cash_uang_masuk_dan_stok_keluar(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');
        $inv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail();

        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);

        $this->assertSame(700000, (int) BrandLedger::sum('jumlah'), 'Uang TM masuk saat invoice lunas.');
        $this->assertTrue((bool) $batch->fresh()->cash_dibayar, 'Cash lunas → stok keluar sistem.');

        $this->produksiTerima($batch->fresh());
        $this->assertSame(0, $this->stock()->availableInBatch($batch->id, $this->produkTm->id, 'M'));
    }

    public function test_420f_bayar_diferd_penuh_dengan_bukti(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');

        // 420F bayar Diferd modal penuh (10 × 60.000 = 600.000) — input nominal manual, dgn bukti.
        app(\App\Http\Controllers\SettlementController::class)
            ->bayarDiferdCash($this->req($this->admin, ['nominal' => 600000], 'POST'), $batch);

        $this->assertSame(600000, $this->settlement()->diferdCashDibayar($batch));
        $this->assertSame(0, $this->settlement()->cashStatus($batch->fresh())['diferd_sisa']);
    }

    public function test_penjualan_batch_cash_tak_menambah_hak_diferd(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');
        $inv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail();
        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);
        $this->produksiTerima($batch->fresh());

        $hakGlobal = (int) \App\Models\Sale::sold()->consignment()->sum(\Illuminate\Support\Facades\DB::raw('qty * harga_diferd'));
        $this->assertSame(0, $hakGlobal);
    }

    public function test_reject_di_batch_cash_bikin_kewajiban_ganti_diferd(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');
        $inv = Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail();
        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);
        $this->produksiTerima($batch->fresh(), [$this->produkTm->id.'|M' => 8]);

        $ob = $this->settlement()->gantiCashObligasi($batch->fresh());
        $this->assertSame(2, $ob['pcs']);
        $this->assertSame(120000, $ob['diferd']);
    }
}

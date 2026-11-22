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

    /**
     * Jeda "sudah diterima, invoice cash belum lunas" (pasti terjadi di batch cash ber-DP): stok
     * batch cash masih di pool jual, jadi penjualan TM bisa teralokasi ke sana. Penjualan itu sudah
     * dibayar lewat invoice cash — dulu ikut terbit lagi di invoice penjualan (TM tertagih dua kali)
     * dan penarikan Diferd teralokasi ke batch cash.
     */
    public function test_penjualan_batch_cash_belum_lunas_tak_ditagih_lagi_dan_tak_menyerap_penarikan(): void
    {
        $cash = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');
        $this->produksiTerima($cash->fresh());                 // diterima, invoice cash BELUM lunas
        $orderCash = $this->jual($this->produkTm, 'M', 3);
        $this->assertSame($cash->id, $orderCash->items()->first()->batch_id, 'prasyarat: penjualan teralokasi ke batch cash');

        $termin = $this->batchAktif($this->produkTm2, ['M' => 5]);
        $this->produksiTerima($termin);
        $orderTermin = $this->jual($this->produkTm2, 'M', 2);  // hak 2 × 60.000 = 120.000

        app(\App\Http\Controllers\InvoiceController::class)->generate();

        $this->assertNull($orderCash->fresh()->invoice_id, 'penjualan batch cash tak boleh masuk invoice penjualan');
        $inv = Invoice::find($orderTermin->fresh()->invoice_id);
        $this->assertNotNull($inv);
        $this->assertSame(2 * 70000, $inv->total);

        $rencana = $this->settlement()->rencanaAlokasi(120000);
        $this->assertArrayNotHasKey($cash->id, $rencana['alokasi'], 'penarikan tak boleh teralokasi ke batch cash');
        $this->assertSame(120000, $rencana['alokasi'][$termin->id] ?? 0);
        $this->assertSame(0, $this->settlement()->hakJual($cash->id));
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

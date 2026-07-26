<?php

namespace Tests\Feature\Erp;

use App\Enums\BatchStatus;
use Tests\ErpTestCase;

class StatusBatchTest extends ErpTestCase
{
    public function test_batch_tuntas_otomatis_jadi_lunas(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 10);   // habis terjual → sisa 0, hak 600.000

        // Bayar hak Diferd penuh (600.000) agar saldo 0.
        $sc = app(\App\Http\Controllers\SettlementController::class);
        $sc->storeLedger($this->req($this->admin, [
            'tipe' => 'pembayaran', 'jumlah' => 600000, 'tanggal' => now()->toDateString(),
        ]), $batch->fresh());

        $this->assertTrue($this->settlement()->batchTuntas($batch->fresh()));
        $this->settlement()->reconcileLunas();
        $this->assertSame(BatchStatus::Lunas, $batch->fresh()->status);
    }

    public function test_batch_masih_produksi_tetap_aktif(): void
    {
        // Disetujui tapi belum produksi/terima (semua PO belum terkirim).
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);

        $this->assertFalse($this->settlement()->batchTuntas($batch));
        $this->settlement()->reconcileLunas();
        $this->assertSame(BatchStatus::Aktif, $batch->fresh()->status);
    }

    public function test_batch_terjual_tapi_diferd_belum_dibayar_tetap_aktif(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 10);   // sisa 0 tapi hak Diferd belum dibayar

        $this->assertFalse($this->settlement()->batchTuntas($batch->fresh()));
        $this->settlement()->reconcileLunas();
        $this->assertSame(BatchStatus::Aktif, $batch->fresh()->status);
    }

    public function test_batch_cash_jadi_lunas_setelah_diterima(): void
    {
        // Alur cash berbasis tagihan: TM bayar invoice + 420F bayar Diferd, baru lunas.
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');

        $inv = \App\Models\Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail();
        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);
        app(\App\Http\Controllers\SettlementController::class)
            ->bayarDiferdCash($this->req($this->admin, [], 'POST'), $batch->fresh());
        $this->produksiTerima($batch->fresh());

        $this->settlement()->reconcileLunas();
        $this->assertSame(BatchStatus::Lunas, $batch->fresh()->status);
    }
}

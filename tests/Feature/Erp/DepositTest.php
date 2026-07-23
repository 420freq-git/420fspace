<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\SettlementController;
use App\Models\BrandLedger;
use App\Models\VendorLedger;
use Tests\ErpTestCase;

class DepositTest extends ErpTestCase
{
    private function deposit(int $jumlah): void
    {
        VendorLedger::create([
            'brand_id' => $this->brandTm->id, 'batch_id' => null, 'tanggal' => now(),
            'tipe' => 'deposit', 'jumlah' => $jumlah, 'keterangan' => 'deposit awal',
        ]);
    }

    public function test_deposit_mengendap_lalu_dikembalikan_tak_sentuh_kas(): void
    {
        $this->deposit(1000000);
        $this->assertSame(1000000, $this->settlement()->depositMengendap());

        app(SettlementController::class)->selesaikanDeposit($this->req($this->admin, ['cara' => 'kembali']));

        $this->assertSame(0, $this->settlement()->depositMengendap());
        // 'kembali' tak menyentuh hak/tagihan → tak ada BrandLedger & tak ada pembayaran.
        $this->assertSame(0, BrandLedger::count());
        $this->assertSame(0, (int) VendorLedger::where('tipe', 'pembayaran')->sum('jumlah'));
    }

    public function test_deposit_offset_menutup_hak_dan_jadi_uang_muka_tm(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 5);   // hak 300.000
        $this->deposit(300000);

        app(SettlementController::class)->selesaikanDeposit($this->req($this->admin, ['cara' => 'offset']));

        $this->assertSame(0, $this->settlement()->depositMengendap());
        // Sisi Diferd: deposit di-offset jadi pembayaran hak batch.
        $this->assertSame(300000, (int) VendorLedger::where('tipe', 'pembayaran')->sum('jumlah'));
        $this->assertSame(0, $this->settlement()->saldo($batch->fresh()));
        // Sisi TM: uang muka (kredit tagihan).
        $this->assertSame(300000, (int) BrandLedger::sum('jumlah'));
    }
}

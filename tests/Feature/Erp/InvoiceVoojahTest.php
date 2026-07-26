<?php

namespace Tests\Feature\Erp;

use App\Models\Invoice;
use Tests\ErpTestCase;

/**
 * VOOJAH (brand milik sendiri) juga ditagih lewat invoice — dulu generate() memfilter hanya brand
 * eksternal sehingga tagihan VOOJAH tak pernah bisa diterbitkan. VOOJAH ditagih di harga MODAL
 * (diferd), bukan retail: snapshot sales.harga_tm420 = hargaTagihan = effectiveDiferd untuk VOOJAH.
 */
class InvoiceVoojahTest extends ErpTestCase
{
    public function test_generate_menerbitkan_invoice_voojah(): void
    {
        $batch = $this->batchAktif($this->produkVoojah, ['M' => 10]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkVoojah, 'M', 3);   // 3 pcs

        app(\App\Http\Controllers\InvoiceController::class)->generate();

        $inv = Invoice::where('brand_id', $this->brandVoojah->id)->latest('id')->first();
        $this->assertNotNull($inv, 'Invoice VOOJAH harus terbit.');
        $this->assertStringStartsWith('INV.VJ.', $inv->nomor, 'Nomor invoice pakai kode brand VOOJAH.');
        $this->assertSame($order->id, $inv->orders()->first()?->id);

        // Nilai = harga MODAL (diferd s_xl = 60.000) × 3 = 180.000 — bukan retail 70.000.
        $this->assertSame(180000, (int) $inv->total);
    }

    public function test_generate_sekaligus_untuk_tm_dan_voojah(): void
    {
        // Pesanan cair untuk KEDUA brand → generate membuat satu invoice per brand.
        $bt = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($bt);
        $this->jual($this->produkTm, 'M', 2);

        $bv = $this->batchAktif($this->produkVoojah, ['M' => 10]);
        $this->produksiTerima($bv);
        $this->jual($this->produkVoojah, 'M', 2);

        app(\App\Http\Controllers\InvoiceController::class)->generate();

        $this->assertSame(1, Invoice::where('brand_id', $this->brandTm->id)->count());
        $this->assertSame(1, Invoice::where('brand_id', $this->brandVoojah->id)->count());

        // TM ditagih retail (70.000×2=140.000), VOOJAH modal (60.000×2=120.000).
        $this->assertSame(140000, (int) Invoice::where('brand_id', $this->brandTm->id)->first()->total);
        $this->assertSame(120000, (int) Invoice::where('brand_id', $this->brandVoojah->id)->first()->total);
    }

    public function test_invoice_voojah_lunas_masuk_cashflow_dan_menutup_tagihan(): void
    {
        $batch = $this->batchAktif($this->produkVoojah, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkVoojah, 'M', 3);

        $ic = app(\App\Http\Controllers\InvoiceController::class);
        $ic->generate();
        $inv = Invoice::where('brand_id', $this->brandVoojah->id)->latest('id')->firstOrFail();

        $this->assertSame(180000, $this->settlement()->tagihanBrand($this->brandVoojah->id)['sisa']);

        $ic->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);

        $this->assertSame('lunas', $inv->fresh()->status);
        $this->assertSame(0, $this->settlement()->tagihanBrand($this->brandVoojah->id)['sisa']);
    }
}

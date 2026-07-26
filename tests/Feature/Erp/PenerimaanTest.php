<?php

namespace Tests\Feature\Erp;

use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use Tests\ErpTestCase;

/**
 * Penjagaan sisi server saat brand mengonfirmasi penerimaan barang.
 *
 * Stok jual = diterima − terjual, jadi angka penerimaan adalah PINTU MASUK stok. Tanpa batas,
 * brand bisa mengarang stok yang tak pernah diproduksi — dan tiap penjualannya menciptakan hak
 * Diferd yang harus dibayar 420F. Form punya atribut `max`, tapi itu hanya UI; penentunya server.
 */
class PenerimaanTest extends ErpTestCase
{
    /** Batch siap kirim + satu surat jalan berisi $qty pcs ukuran M. */
    private function suratJalan(int $qty = 10): Pengiriman
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => $qty]);
        $poc = app(\App\Http\Controllers\PurchaseOrderController::class);
        $peng = app(\App\Http\Controllers\PengirimanController::class);

        foreach (PurchaseOrder::where('batch_id', $batch->id)->get() as $po) {
            $poc->updateStatus($this->req($this->admin, ['tahap' => 'siap_kirim'], 'PATCH'), $batch, $po);
        }
        $peng->store($this->req($this->admin, [
            'batch_id' => $batch->id,
            'tanggal_kirim' => now()->toDateString(),
            'items' => [['product_id' => $this->produkTm->id, 'ukuran' => 'M', 'qty' => $qty]],
        ]));

        return Pengiriman::where('batch_id', $batch->id)->latest('id')->firstOrFail()->load('items');
    }

    public function test_diterima_tidak_boleh_melebihi_yang_dikirim(): void
    {
        $sj = $this->suratJalan(10);
        $item = $sj->items->first();

        app(\App\Http\Controllers\PengirimanController::class)
            ->terima($this->req($this->tm, ['diterima' => [$item->id => 999]], 'PATCH'), $sj);

        $this->assertNotSame(999, (int) $item->fresh()->qty_diterima, 'Penerimaan berlebih tidak boleh tersimpan.');
        $this->assertSame(
            0,
            $this->stock()->availableInBatch($sj->batch_id, $this->produkTm->id, 'M'),
            'Penerimaan yang ditolak tidak boleh memunculkan stok.'
        );
        $this->assertFalse($sj->fresh()->isDiterima(), 'Surat jalan tidak boleh berstatus diterima bila angkanya ditolak.');
    }

    public function test_diterima_penuh_diterima_normal(): void
    {
        $sj = $this->suratJalan(10);
        $item = $sj->items->first();

        app(\App\Http\Controllers\PengirimanController::class)
            ->terima($this->req($this->tm, ['diterima' => [$item->id => 10]], 'PATCH'), $sj);

        $this->assertSame(10, (int) $item->fresh()->qty_diterima);
        $this->assertSame(10, $this->stock()->availableInBatch($sj->batch_id, $this->produkTm->id, 'M'));
    }

    public function test_diterima_kurang_wajib_beralasan_dan_jadi_kerugian_vendor(): void
    {
        $sj = $this->suratJalan(10);
        $item = $sj->items->first();
        $peng = app(\App\Http\Controllers\PengirimanController::class);

        // Tanpa alasan → ditolak.
        $peng->terima($this->req($this->tm, ['diterima' => [$item->id => 7]], 'PATCH'), $sj);
        $this->assertFalse($sj->fresh()->isDiterima());

        // Dengan alasan → diterima, 3 pcs jadi selisih yang ditanggung vendor.
        $peng->terima($this->req($this->tm, [
            'diterima' => [$item->id => 7], 'alasan_kurang_terima' => 'reject',
        ], 'PATCH'), $sj);

        $this->assertSame(7, (int) $item->fresh()->qty_diterima);
        $this->assertSame(7, $this->stock()->availableInBatch($sj->batch_id, $this->produkTm->id, 'M'));
        $this->assertSame(3, $this->stock()->shortfallInBatch($sj->batch_id, $this->produkTm->id, 'M'));
    }

    public function test_brand_lain_tidak_bisa_mengonfirmasi_penerimaan(): void
    {
        $sj = $this->suratJalan(10);
        $item = $sj->items->first();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(\App\Http\Controllers\PengirimanController::class)
            ->terima($this->req($this->voojah, ['diterima' => [$item->id => 10]], 'PATCH'), $sj);
    }
}

<?php

namespace Tests\Feature\Erp;

use App\Models\Order;
use App\Models\Sale;
use Tests\ErpTestCase;

/**
 * Tagihan brand ke 420F — satu definisi untuk semua POV.
 *
 * Dulu dashboard TM memakai `Sale::sold()` sedangkan dashboard 420F memakai
 * "konsinyasi + order lunas". Bedanya membuat TM melihat sisa tagihan yang tak pernah bisa
 * dilunasi (retur rusak dari pesanan BATAL tidak pernah masuk invoice mana pun), sementara
 * 420F melihat nol untuk data yang sama.
 */
class TagihanBrandTest extends ErpTestCase
{
    private function tagihan(?int $brandId = null): array
    {
        return $this->settlement()->tagihanBrand($brandId);
    }

    public function test_pesanan_belum_cair_belum_menagih(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);

        // Pesanan dibuat tapi TIDAK ditandai lunas.
        app(\App\Http\Controllers\OrderController::class)->store($this->req($this->tm, [
            'nomor_pesanan' => 'ORD-BELUM-CAIR', 'marketplace' => 'tiktokshop',
            'tanggal_pesanan' => now()->toDateString(), 'status' => 'dipesan',
            'items' => [['product_id' => $this->produkTm->id, 'ukuran' => 'M', 'qty' => 3]],
        ]));

        $this->assertSame('dipesan', Order::where('nomor_pesanan', 'ORD-BELUM-CAIR')->first()->status->value);
        $this->assertSame(0, $this->tagihan($this->brandTm->id)['total'], 'Pesanan yang belum cair tidak boleh menagih.');
    }

    public function test_pesanan_cair_menagih_harga_tm420(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 3);

        // 3 pcs × 70.000 (harga tm420 tier s_xl)
        $this->assertSame(210000, $this->tagihan($this->brandTm->id)['penjualan']);
    }

    public function test_retur_rusak_tetap_ditagih_sesuai_aturan_terkunci(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 2);

        $this->assertSame(140000, $this->tagihan($this->brandTm->id)['total']);

        // Barang kembali dalam kondisi rusak: stok hilang, tapi brand TETAP bayar produksi
        // (CLAUDE.md §4 — aturan terkunci).
        $order->update(['status' => 'retur']);
        app(\App\Http\Controllers\MonitoringController::class)
            ->terimaRetur($this->req($this->tm, ['kondisi' => 'rusak', 'alasan_rusak' => 'sobek']), $order->fresh());

        $this->assertSame(
            140000,
            $this->tagihan($this->brandTm->id)['total'],
            'Retur rusak harus tetap ditagih — brand menanggung biaya produksinya.'
        );
    }

    public function test_retur_layak_tidak_lagi_ditagih(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 2);

        // Layak jual → barang kembali ke stok, brand tidak menanggung apa pun.
        $order->update(['status' => 'retur']);
        app(\App\Http\Controllers\MonitoringController::class)
            ->terimaRetur($this->req($this->tm, ['kondisi' => 'layak']), $order->fresh());

        $this->assertSame(0, $this->tagihan($this->brandTm->id)['total']);
    }

    public function test_retur_rusak_bisa_diterbitkan_invoicenya(): void
    {
        // Rugi retur pelanggan ditanggung TM: tagihannya harus BISA diterbitkan, bukan menggantung.
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 2);

        $order->update(['status' => 'retur']);
        app(\App\Http\Controllers\MonitoringController::class)
            ->terimaRetur($this->req($this->tm, ['kondisi' => 'rusak', 'alasan_rusak' => 'sobek']), $order->fresh());

        app(\App\Http\Controllers\InvoiceController::class)->generate();

        $inv = \App\Models\Invoice::latest('id')->first();
        $this->assertNotNull($inv, 'Pesanan retur rusak harus bisa masuk invoice.');
        $this->assertSame($order->id, $inv->orders()->first()?->id);
        $this->assertSame(140000, (int) $inv->total, 'Nilai tagihan = harga tm420 penuh.');

        // Setelah dibayar, saldo brand bersih.
        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);
        $this->assertSame(0, $this->tagihan($this->brandTm->id)['sisa']);
    }

    public function test_reject_produksi_ditanggung_diferd_bukan_brand(): void
    {
        // Kurang saat penerimaan = kerugian vendor. Brand tidak boleh ikut ditagih untuk ini.
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch, [$this->produkTm->id.'|M' => 7]);   // 3 pcs reject produksi

        $this->assertSame(3, $this->stock()->shortfallInBatch($batch->id, $this->produkTm->id, 'M'));
        $this->assertSame(0, $this->tagihan($this->brandTm->id)['total'], 'Reject produksi tak boleh menagih brand.');
    }

    public function test_retur_sesudah_invoice_lunas_tidak_bikin_sisa_minus(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 2);

        $ic = app(\App\Http\Controllers\InvoiceController::class);
        $ic->generate();
        $inv = \App\Models\Invoice::latest('id')->firstOrFail();
        $ic->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);

        $this->assertSame(0, $this->tagihan($this->brandTm->id)['sisa']);

        $order->update(['status' => 'retur']);
        app(\App\Http\Controllers\MonitoringController::class)
            ->terimaRetur($this->req($this->tm, ['kondisi' => 'rusak', 'alasan_rusak' => 'sobek']), $order->fresh());

        $this->assertSame(
            0,
            $this->tagihan($this->brandTm->id)['sisa'],
            'Uang sudah masuk; tagihannya tak boleh hilang sehingga sisa jadi minus.'
        );
    }

    public function test_pov_tm_dan_420f_melihat_angka_sama(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $order = $this->jual($this->produkTm, 'M', 4);

        // Satu penjualan jadi retur rusak pada pesanan batal (pemicu bug lama).
        $order2 = $this->jual($this->produkTm, 'M', 1);
        $order2->update(['status' => 'batal']);
        Sale::where('order_id', $order2->id)->update(['kondisi_retur' => 'rusak']);

        $tm = $this->tagihan($this->brandTm->id);
        $admin = $this->tagihan();

        $this->assertSame($admin['total'], $tm['total'], 'Total tagihan TM & 420F harus identik.');
        $this->assertSame($admin['sisa'], $tm['sisa'], 'Sisa tagihan TM & 420F harus identik.');
    }

    public function test_tagihan_cash_lewat_invoice_bukan_penjualan_konsinyasi(): void
    {
        // Cash = beli putus, ditagih lewat INVOICE (jumlah_manual), BUKAN penjualan konsinyasi.
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'cash');
        $inv = \App\Models\Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->firstOrFail();

        $t = $this->tagihan($this->brandTm->id);
        // Bukan tagihan penjualan konsinyasi; masuk sebagai tagihan manual (buyout+cash) 700.000.
        $this->assertSame(0, $t['penjualan']);
        $this->assertSame(700000, $t['total']);
        $this->assertSame(700000, $t['sisa'], 'Belum dibayar → jadi sisa tagihan.');

        // TM bayar invoice cash → sisa 0 (tak jadi minus).
        app(\App\Http\Controllers\InvoiceController::class)
            ->markPaid($this->req($this->admin, ['tanggal_bayar' => now()->toDateString()], 'PATCH'), $inv);
        $t2 = $this->tagihan($this->brandTm->id);
        $this->assertSame(0, $t2['sisa']);
    }
}

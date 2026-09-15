<?php

namespace Tests\Feature\Erp;

use Tests\ErpTestCase;

/**
 * Endpoint BACA read-only ERP TM420: GET /api/tm420/pengiriman.
 *
 * Dipakai ERP TM untuk membuat DRAFT penerimaan tanpa mengetik ulang 18 baris berikut harganya.
 * Yang tidak ikut menyeberang: keputusan lolos/reject — itu milik gudang penerima, dan reject
 * ditanggung vendor.
 */
class PengirimanTmTest extends ErpTestCase
{
    private const URL = '/api/tm420/pengiriman';

    protected function setUp(): void
    {
        parent::setUp();
        config(['integrasi.tm_token' => 'TM_TOKEN_UJI', 'integrasi.erp_token' => 'ERP_420F_TOKEN']);
    }

    private function tarik(string $query = '')
    {
        return $this->getJson(self::URL.$query, ['Authorization' => 'Bearer TM_TOKEN_UJI']);
    }

    public function test_tanpa_token_atau_token_salah_ditolak(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->getJson(self::URL, ['Authorization' => 'Bearer salah'])->assertStatus(401);
    }

    public function test_token_erp_420f_tidak_berlaku(): void
    {
        $this->getJson(self::URL, ['Authorization' => 'Bearer ERP_420F_TOKEN'])->assertStatus(401);
    }

    public function test_surat_jalan_tampil_berikut_sku_qty_dan_ongkos(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10, 'L' => 5], 'termin');
        $this->produksiTerima($batch);

        $data = collect($this->tarik()->assertOk()->assertJsonPath('ok', true)->json('data'));

        $this->assertCount(1, $data, 'Satu surat jalan.');
        $sj = $data->first();

        $this->assertNotEmpty($sj['nomor_sj'], 'nomor_sj adalah kunci anti-dobel di sisi ERP TM.');
        $this->assertSame($batch->nomor_batch, $sj['nomor_batch']);

        $m = collect($sj['baris'])->firstWhere('kode_sku', 'TM-A-M');
        $this->assertNotNull($m, 'kode_sku = sku_turunan per ukuran.');
        $this->assertSame(10, $m['qty_kirim']);
        $this->assertSame(10, $m['qty_diterima']);
        $this->assertSame(70000, $m['biaya_produksi_satuan'], 'Ongkos = harga tm420.');
    }

    public function test_selisih_terima_dilaporkan_apa_adanya(): void
    {
        /*
         * Angka ini USULAN bagi ERP TM, bukan keputusan. Yang penting ia jujur: kalau pabrik
         * mencatat kurang, ERP TM harus melihat kekurangannya — bukan angka kirim yang mulus.
         */
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'termin');
        $po = $batch->purchaseOrders->first();
        $this->produksiTerima($batch, [$po->product_id.'|M' => 7]);

        $m = collect($this->tarik()->json('data.0.baris'))->firstWhere('kode_sku', 'TM-A-M');

        $this->assertSame(10, $m['qty_kirim']);
        $this->assertSame(7, $m['qty_diterima']);
    }

    public function test_voojah_tidak_pernah_ikut(): void
    {
        // Barang titipan tidak punya harga beli sama sekali. Mengirimkannya lewat pintu yang
        // membawa `biaya_produksi_satuan` mengundang ERP mencatatnya sebagai pembelian.
        $batch = $this->batchAktif($this->produkVoojah, ['M' => 7], 'termin');
        $this->produksiTerima($batch);

        $kode = collect($this->tarik()->json('data'))
            ->flatMap(fn ($sj) => collect($sj['baris'])->pluck('kode_sku'));

        $this->assertTrue($kode->every(fn ($k) => ! str_starts_with((string) $k, 'VJ-')),
            'Produk VOOJAH tak boleh ikut ke ERP TM lewat pintu ini.');
    }

    public function test_sejak_menyaring_menurut_tanggal_kirim(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 3], 'termin');
        $this->produksiTerima($batch);

        \App\Models\Pengiriman::query()->update(['tanggal_kirim' => now()->subDays(200)->toDateString()]);

        $this->assertCount(0, $this->tarik()->json('data'),
            'Bawaannya 90 hari — satu permintaan tidak boleh diam-diam menarik seluruh riwayat.');

        $this->assertCount(1, $this->tarik('?sejak='.now()->subDays(365)->toDateString())->json('data'));
    }
}

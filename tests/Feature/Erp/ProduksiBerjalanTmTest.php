<?php

namespace Tests\Feature\Erp;

use Tests\ErpTestCase;

/**
 * Endpoint BACA read-only ERP TM420: GET /api/tm420/produksi-berjalan.
 * Token TERPISAH; hanya produksi TM420 (eksternal) yang aktif & belum terkirim;
 * kode_sku = sku_turunan; biaya_produksi_satuan = ongkos disepakati (tm420). VOOJAH dikecualikan.
 */
class ProduksiBerjalanTmTest extends ErpTestCase
{
    private const URL = '/api/tm420/produksi-berjalan';

    protected function setUp(): void
    {
        parent::setUp();
        config(['integrasi.tm_token' => 'TM_TOKEN_UJI', 'integrasi.erp_token' => 'ERP_420F_TOKEN']);
    }

    public function test_tanpa_token_atau_token_salah_ditolak(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->getJson(self::URL, ['Authorization' => 'Bearer salah'])->assertStatus(401);
    }

    public function test_token_erp_420f_tidak_berlaku_untuk_endpoint_tm(): void
    {
        // Token ERP 420F BUKAN token TM → harus ditolak (token terpisah).
        $this->getJson(self::URL, ['Authorization' => 'Bearer ERP_420F_TOKEN'])->assertStatus(401);
    }

    public function test_produksi_tm420_berjalan_tampil_dengan_sku_dan_ongkos(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10, 'L' => 5], 'termin');

        $resp = $this->getJson(self::URL, ['Authorization' => 'Bearer TM_TOKEN_UJI'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $rows = collect($resp->json('data'));
        $this->assertCount(2, $rows, 'Dua baris: ukuran M & L dari 1 PO.');

        $m = $rows->firstWhere('kode_sku', 'TM-A-M');
        $this->assertNotNull($m, 'kode_sku = sku_turunan per ukuran.');
        $this->assertSame(10, $m['qty_dipesan']);
        $this->assertSame(0, $m['qty_selesai'], 'Belum siap kirim → belum selesai.');
        $this->assertSame(0, $m['qty_dikirim']);
        $this->assertSame(70000, $m['biaya_produksi_satuan'], 'Ongkos = harga tm420 (dgn markup 420F).');
        $this->assertSame($batch->purchaseOrders->first()->nomor_po, $m['nomor_request']);
    }

    public function test_voojah_tidak_pernah_muncul_di_endpoint_tm(): void
    {
        $this->batchAktif($this->produkVoojah, ['M' => 7], 'termin');

        $rows = collect($this->getJson(self::URL, ['Authorization' => 'Bearer TM_TOKEN_UJI'])->json('data'));
        $this->assertTrue(
            $rows->every(fn ($r) => ! str_starts_with((string) $r['kode_sku'], 'VJ-')),
            'Produk VOOJAH (milik sendiri) tak boleh ikut ke ERP TM.'
        );
    }

    public function test_po_yang_sudah_terkirim_dikecualikan(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10], 'termin');
        $this->produksiTerima($batch->fresh());   // semua PO → terkirim

        $rows = collect($this->getJson(self::URL, ['Authorization' => 'Bearer TM_TOKEN_UJI'])->json('data'));
        $this->assertTrue($rows->doesntContain('kode_sku', 'TM-A-M'), 'PO terkirim = sudah tiba, bukan "berjalan".');
    }
}

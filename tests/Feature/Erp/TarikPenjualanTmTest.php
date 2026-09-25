<?php

namespace Tests\Feature\Erp;

use App\Models\Order;
use App\Models\Sale;
use App\Services\TarikPenjualanTmService;
use Illuminate\Support\Facades\Http;
use Tests\ErpTestCase;

/**
 * Menarik penjualan TM dari ERP TM420 sebagai dasar tagihan ongkos produksi.
 *
 * Sebelum jalur ini ada, brand TM420 punya katalog lengkap di sistem ini tapi
 * NOL pesanan — tagihan produksinya tidak pernah bisa diterbitkan dari sini.
 */
class TarikPenjualanTmTest extends ErpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['integrasi.tm_api_base_url' => 'https://erp.tm420.uji/api/420f',
            'integrasi.tm_api_token' => 'TOKEN-UJI']);

        Http::fake(['*' => fn () => Http::response($this->balasan)]);
    }

    /** @var array<string, mixed> balasan ERP TM yang sedang berlaku */
    private array $balasan = ['ok' => true, 'baris' => [], 'ringkas' => []];

    /**
     * Balasan ERP TM sesuai kontrak docs/API-420F.md §4.2.
     *
     * Fake-nya dipasang SEKALI di setUp dan membaca kotak ini, bukan
     * mendaftarkan stub baru tiap kali: stub kedua untuk pola URL yang sama
     * tidak menimpa yang pertama, jadi tarikan kedua akan membaca balasan lama
     * — dan tes perpindahan status lulus/gagal karena alasan yang keliru.
     */
    private function balas(array $baris, ?string $cutoff = null): void
    {
        $this->balasan = [
            'ok' => true,
            'baris' => $baris,
            'ringkas' => ['jumlah_baris' => count($baris), 'cutoff_penagihan' => $cutoff],
        ];
    }

    /** @return array<string, mixed> */
    private function baris(string $sku, int $qty, string $nomor = '260913ABC', ?string $tglCair = '2026-09-20',
        ?int $biaya = null, string $statusErp = 'selesai'): array
    {
        return [
            'pesanan' => ['nomor' => 'SH-'.$nomor, 'nomor_marketplace' => $nomor, 'toko' => 'TM420 Official Store',
                'platform' => 'shopee', 'tanggal' => '2026-09-13', 'status' => $statusErp],
            'sku' => $sku, 'jenis_pasokan' => 'produksi_420f', 'qty' => $qty, 'qty_tagih' => $qty,
            'cair' => $tglCair !== null, 'tgl_cair' => $tglCair,
            'biaya_produksi_satuan' => $biaya, 'nilai_tagihan' => $biaya ? $biaya * $qty : null,
        ];
    }

    private function tarik(string $dari = '2026-09-01', string $sampai = '2026-09-30'): array
    {
        return app(TarikPenjualanTmService::class)->jalankan($dari, $sampai);
    }

    /** SKU turunan milik produk TM yang dipakai seluruh berkas uji ini. */
    private function skuTm(string $ukuran = 'M'): string
    {
        return $this->produkTm->sizes()->where('ukuran', $ukuran)->value('sku_turunan');
    }

    public function test_penjualan_tm_jadi_pesanan_yang_bisa_ditagih(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 2)]);

        $r = $this->tarik();

        $this->assertSame(1, $r['dipakai'], 'satu baris ERP dipakai');
        $this->assertSame(1, $r['import']['imported_orders']);
        $this->assertSame(2, (int) Sale::where('brand_id', $this->brandTm->id)->sum('qty'), 'qty-nya ikut utuh');

        $order = Order::where('nomor_pesanan', '260913ABC')->first();
        $this->assertNotNull($order);
        $this->assertSame($this->brandTm->id, $order->brand_id);
        $this->assertSame('lunas', $order->status->value, 'yang ditarik sudah cair — harus bisa ditagih');
        $this->assertSame('2026-09-20', $order->tgl_cair->toDateString());
        $this->assertSame(1, Order::bisaDitagih()->count());
    }

    /** Nilai tagihannya harga TM420 — di situlah markup 420F hidup. */
    public function test_harga_tagihan_memakai_daftar_harga_sendiri(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 1)]);

        $this->tarik();

        $sale = Sale::where('brand_id', $this->brandTm->id)->first();
        $this->assertNotNull($sale->harga_tm420);
        $this->assertGreaterThan($sale->harga_diferd, $sale->harga_tm420, 'markup 420F harus ada di antaranya');
    }

    /**
     * Barang titipan VOOJAH ikut terbawa lingkup token, tapi BUKAN urusan
     * tagihan ini — ia sudah masuk lewat ERP 420F. Menariknya dua kali berarti
     * satu penjualan tercatat dua kali.
     */
    public function test_sku_di_luar_brand_tm_dilewati(): void
    {
        $batch = $this->batchAktif($this->produkVoojah, ['M' => 5]);
        $this->produksiTerima($batch);
        $skuVoojah = $this->produkVoojah->sizes()->where('ukuran', 'M')->value('sku_turunan');
        $this->balas([$this->baris($skuVoojah, 1)]);

        $r = $this->tarik();

        $this->assertSame(0, $r['dipakai']);
        $this->assertSame(1, $r['bukan_produksi_kita']);
        $this->assertSame(0, Order::count());
    }

    /** Periode yang tagihannya sudah diselesaikan di luar sistem tidak ditagih lagi. */
    public function test_baris_sebelum_cutoff_dilewati(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([
            $this->baris($this->skuTm(), 1, '260901LAMA', '2026-09-05'),
            $this->baris($this->skuTm(), 1, '260913BARU', '2026-09-20'),
        ], cutoff: '2026-09-07');

        $r = $this->tarik();

        $this->assertSame(1, $r['sebelum_cutoff']);
        $this->assertSame(1, $r['dipakai']);
        $this->assertNull(Order::where('nomor_pesanan', '260901LAMA')->first());
        $this->assertNotNull(Order::where('nomor_pesanan', '260913BARU')->first());
    }

    /** Menarik rentang yang sama dua kali tidak boleh menggandakan pesanan. */
    public function test_tarik_ulang_tidak_menggandakan(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 1)]);

        $this->tarik();
        $r = $this->tarik();

        $this->assertSame(1, $r['import']['skip_sudah_ada']);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Sale::count());
    }

    /**
     * Harga kita vs catatan ERP TM: keduanya seharusnya sama, karena angka di
     * sana berasal dari surat jalan yang dikirim sistem ini.
     */
    public function test_selisih_harga_dilaporkan_bukan_ditelan(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 1, biaya: 999_000)]);

        $r = $this->tarik();

        $this->assertCount(1, $r['selisih_harga']);
        $this->assertSame(999_000, $r['selisih_harga'][0]['erp_tm']);
    }

    /** SKU TM yang belum dipetakan harus kelihatan, bukan hilang dari tagihan. */
    public function test_sku_tm_yang_belum_ada_di_katalog_dilaporkan(): void
    {
        $this->balas([$this->baris('TS-BELUM-ADA-L', 1)]);

        $r = $this->tarik();

        $this->assertSame(['TS-BELUM-ADA-L'], $r['sku_tak_dikenal']);
    }

    /**
     * Pesanan yang belum cair tetap ditarik — supaya bisa dipantau sejak masuk,
     * sama seperti pesanan VOOJAH. Tapi ia BELUM boleh ditagih.
     */
    public function test_pesanan_belum_cair_ikut_tertarik_tapi_belum_bisa_ditagih(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 1, '260920BARU', tglCair: null, statusErp: 'dikirim')]);

        $this->tarik();

        $order = Order::where('nomor_pesanan', '260920BARU')->first();
        $this->assertNotNull($order, 'pesanan belum cair tetap masuk untuk dipantau');
        $this->assertSame('dikirim', $order->status->value);
        $this->assertNull($order->tgl_cair);
        $this->assertSame(0, Order::bisaDitagih()->count(), 'belum cair belum boleh ditagih');
    }

    /** Begitu uangnya turun, tarikan berikutnya memindahkannya ke lunas. */
    public function test_status_ikut_naik_saat_pesanan_cair(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);

        $this->balas([$this->baris($this->skuTm(), 1, '260920BARU', tglCair: null, statusErp: 'dikirim')]);
        $this->tarik();

        $this->balas([$this->baris($this->skuTm(), 1, '260920BARU', tglCair: '2026-09-24')]);
        $r = $this->tarik();

        $order = Order::where('nomor_pesanan', '260920BARU')->first();
        $this->assertSame('lunas', $order->status->value);
        $this->assertSame('2026-09-24', $order->tgl_cair->toDateString());
        $this->assertSame(1, $r['status_diperbarui']);
        $this->assertSame(1, Order::bisaDitagih()->count());
    }

    /** Status tidak boleh mundur: yang sudah lunas tetap lunas walau ERP masih bilang dikirim. */
    public function test_status_tidak_mundur(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 1, '260920BARU')]);
        $this->tarik();

        $this->balas([$this->baris($this->skuTm(), 1, '260920BARU', tglCair: null, statusErp: 'dikirim')]);
        $this->tarik();

        $this->assertSame('lunas', Order::where('nomor_pesanan', '260920BARU')->first()->status->value);
    }

    /** Pembatalan di ERP TM menang atas tahap mana pun — barangnya tidak jadi terjual. */
    public function test_pesanan_batal_ikut_dibatalkan(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 1, '260920BATAL', tglCair: null, statusErp: 'dikirim')]);
        $this->tarik();

        $this->balas([$this->baris($this->skuTm(), 1, '260920BATAL', tglCair: null, statusErp: 'batal')]);
        $this->tarik();

        $this->assertSame('batal', Order::where('nomor_pesanan', '260920BATAL')->first()->status->value);
    }

    /** Cut-off hanya mengecualikan yang sudah cair; yang belum cair belum pernah ditagih siapa pun. */
    public function test_cutoff_tidak_menyentuh_pesanan_yang_belum_cair(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);
        $this->balas([$this->baris($this->skuTm(), 1, '260901BELUM', tglCair: null, statusErp: 'dikirim')],
            cutoff: '2026-09-07');

        $r = $this->tarik();

        $this->assertSame(0, $r['sebelum_cutoff']);
        $this->assertNotNull(Order::where('nomor_pesanan', '260901BELUM')->first());
    }

    public function test_tanpa_token_menolak_jalan(): void
    {
        config(['integrasi.tm_api_token' => '']);
        Http::fake();

        $this->expectException(\RuntimeException::class);
        $this->tarik();
    }
}

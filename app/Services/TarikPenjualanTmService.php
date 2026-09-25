<?php

namespace App\Services;

use App\Enums\SizeTier;
use App\Models\Brand;
use App\Models\Order;
use App\Models\ProductSize;
use Illuminate\Support\Carbon;

/**
 * Menarik penjualan TM dari ERP TM420, lalu menjadikannya pesanan di sistem ini
 * supaya bisa ditagihkan seperti brand lain.
 *
 * ## Kenapa perlu
 *
 * TM420 adalah brand EKSTERNAL: 420F membeli produksinya dari Diferd, menambah
 * markup, dan menagihkannya ke TM. Tagihannya lahir saat barang TERJUAL dan
 * uangnya CAIR — dan dua peristiwa itu hanya diketahui ERP TM. Sebelum jalur
 * ini ada, penjualan TM tidak pernah masuk sistem produksi sama sekali: brand
 * TM420 punya 33 artikel dan 165 ukuran di katalog, tapi NOL pesanan. Artinya
 * tagihan ongkos produksinya tidak pernah bisa diterbitkan dari sini.
 *
 * ## Yang disaring, dan kenapa ketat
 *
 * Token produksi di ERP TM berlingkup `produksi_420f` + `konsinyasi_420f` —
 * lebih luas dari yang boleh ditagihkan di sini, karena ERP TM tidak mencatat
 * siapa yang memproduksi barang titipan. Penyaringan akhirnya memang di sisi
 * kita, memakai katalog kita sendiri: hanya SKU milik brand TM420 yang punya
 * `sku_turunan`. Barang VOOJAH sengaja tidak ikut — ia sudah masuk lewat ERP
 * 420F, dan menariknya dua kali berarti satu penjualan tercatat dua kali.
 *
 * SKU yang tidak dikenali TIDAK dibuang diam-diam; ia dilaporkan supaya artikel
 * yang belum dipetakan kelihatan, bukan menghilang dari tagihan.
 *
 * ## Cut-off
 *
 * ERP TM menandai periode yang tagihannya sudah diselesaikan di luar sistem
 * (`cutoff_penagihan`). Barisnya tetap dikirim — riwayat penjualannya nyata —
 * tapi menagihnya lagi tidak akan memunculkan galat apa pun. Karena itu baris
 * yang cair pada atau sebelum tanggal itu dilewati di sini.
 *
 * ## Harga
 *
 * Nilai tagihan memakai DAFTAR HARGA sistem ini (`harga TM420`), karena di
 * situlah markup 420F hidup. Angka ERP TM (`biaya_produksi_satuan`) ikut ditarik
 * sebagai pembanding: kalau berbeda, berarti daftar harga dan surat jalan tidak
 * sepakat, dan itu harus dilihat orang sebelum ditagih — bukan ditelan diam-diam.
 */
class TarikPenjualanTmService
{
    public function __construct(
        private ErpTmPenjualanClient $erp,
        private MarketplaceImportService $importer,
    ) {}

    /**
     * @return array<string, mixed>  ringkasan untuk ditampilkan apa adanya
     */
    public function jalankan(string $dari, string $sampai): array
    {
        /*
         * SEMUA status, bukan hanya yang cair.
         *
         * Tagihan memang lahir dari yang cair, tapi pesanan yang belum cair
         * tetap perlu terlihat — sama seperti pesanan VOOJAH. Tanpa itu, barang
         * yang sudah keluar dari gudang TM tidak muncul di mana pun sampai
         * uangnya turun, dan tidak ada yang bisa memantaunya.
         */
        $balasan = $this->erp->penjualan($dari, $sampai, status: 'semua');
        $baris = $balasan['baris'] ?? [];
        $cutoff = $balasan['ringkas']['cutoff_penagihan'] ?? null;

        $milikKita = $this->skuMilikTm();

        $ringkas = [
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'baris_erp' => count($baris),
            'dipakai' => 0,
            'bukan_produksi_kita' => 0,
            'sebelum_cutoff' => 0,
            'cutoff' => $cutoff,
            'sku_tak_dikenal' => [],
            'selisih_harga' => [],
        ];

        $perPesanan = [];

        foreach ($baris as $b) {
            $sku = strtoupper(trim((string) ($b['sku'] ?? '')));
            $qty = (int) ($b['qty_tagih'] ?? $b['qty'] ?? 0);

            if ($sku === '' || $qty < 1) {
                continue;
            }

            $tglCair = (string) ($b['tgl_cair'] ?? '');
            $cair = (bool) ($b['cair'] ?? ($tglCair !== ''));

            /*
             * Cut-off hanya mengecualikan yang SUDAH cair: periode itu tagihannya
             * sudah diselesaikan di luar sistem. Pesanan yang belum cair tidak
             * punya urusan dengan cut-off — ia belum pernah ditagih siapa pun.
             */
            if ($cair && $cutoff && $tglCair !== '' && $tglCair <= $cutoff) {
                $ringkas['sebelum_cutoff']++;

                continue;
            }

            if (! $milikKita->has($sku)) {
                $ringkas['bukan_produksi_kita']++;

                // Barang titipan memang bukan urusan tagihan ini; yang perlu
                // dilihat orang adalah SKU TM yang belum dipetakan di katalog.
                if (str_starts_with($sku, 'TS-') || str_starts_with($sku, 'TSOV-') || str_starts_with($sku, 'HOOD-')) {
                    $ringkas['sku_tak_dikenal'][$sku] = true;
                }

                continue;
            }

            /** @var ProductSize $size */
            $size = $milikKita->get($sku);
            $this->catatSelisihHarga($size, $b, $ringkas);

            $nomor = (string) ($b['pesanan']['nomor_marketplace'] ?? $b['pesanan']['nomor'] ?? '');

            if ($nomor === '') {
                continue;
            }

            $perPesanan[$nomor] ??= [
                'no_pesanan' => $nomor,
                'marketplace' => (string) ($b['pesanan']['platform'] ?? 'web'),
                'tanggal' => (string) ($b['pesanan']['tanggal'] ?? $tglCair),
                'tgl_cair' => $tglCair,
                'cair' => $cair,
                'status_erp' => (string) ($b['pesanan']['status'] ?? ''),
                'items' => [],
            ];

            $perPesanan[$nomor]['items'][] = ['sku' => $sku, 'qty' => $qty];
            $ringkas['dipakai']++;
        }

        $hasil = $this->importer->importDariErp(array_values($perPesanan));

        /*
         * Status disamakan dengan ERP TM di SETIAP tarikan, bukan sekali saat
         * dibuat. Pesanan yang tadinya belum cair akan cair belakangan, dan
         * kalau statusnya tidak ikut bergerak ia tidak akan pernah masuk
         * `bisaDitagih()` — tagihan yang jadi alasan tarikan ini ada tidak
         * pernah terbit, tanpa satu pun galat.
         */
        $ringkas['status_diperbarui'] = $this->sinkronkanStatus($perPesanan);

        $ringkas['import'] = $hasil;
        $ringkas['sku_tak_dikenal'] = array_keys($ringkas['sku_tak_dikenal']);

        return $ringkas;
    }

    /**
     * SKU brand TM420 yang punya kode — inilah "yang diproduksi di sistem ini".
     *
     * @return \Illuminate\Support\Collection<string, ProductSize>
     */
    private function skuMilikTm()
    {
        $tm = Brand::where('tipe', \App\Enums\BrandType::Eksternal)->pluck('id');

        return ProductSize::with('product')
            ->whereHas('product', fn ($q) => $q->whereIn('brand_id', $tm))
            ->whereNotNull('sku_turunan')->where('sku_turunan', '!=', '')
            ->get()
            ->keyBy(fn (ProductSize $s) => strtoupper(trim((string) $s->sku_turunan)));
    }

    /**
     * Daftar harga kita vs ongkos produksi yang tercatat di ERP TM.
     *
     * Keduanya seharusnya sama: angka di ERP TM berasal dari surat jalan yang
     * dikirim sistem ini. Kalau berbeda, salah satunya basi — dan menagih
     * memakai angka yang lebih besar tanpa memberitahu siapa pun adalah cara
     * tercepat membuat tagihan berhenti dipercaya.
     */
    private function catatSelisihHarga(ProductSize $size, array $baris, array &$ringkas): void
    {
        $erp = $baris['biaya_produksi_satuan'] ?? null;

        if ($erp === null) {
            return;
        }

        $tier = SizeTier::forUkuran($size->ukuran->value ?? (string) $size->ukuran);
        $kita = (int) ($size->product->hargaTagihan($tier) ?? 0);

        if ((int) $erp === $kita) {
            return;
        }

        $ringkas['selisih_harga'][] = [
            'sku' => strtoupper(trim((string) $size->sku_turunan)),
            'kita' => $kita,
            'erp_tm' => (int) $erp,
        ];
    }

    /**
     * Samakan status pesanan di sini dengan status di ERP TM.
     *
     * Arahnya searah: status hanya boleh MAJU (dipesan → dikirim → lunas), dan
     * batal/retur datang dari ERP TM apa adanya. Membiarkannya mundur berarti
     * pesanan yang sudah ditagih bisa kembali jadi "belum cair" hanya karena
     * satu tarikan ulang — dan tagihannya ikut kacau.
     *
     * @param  array<string, array<string, mixed>>  $perPesanan
     */
    private function sinkronkanStatus(array $perPesanan): int
    {
        $n = 0;

        foreach ($perPesanan as $nomor => $p) {
            $tujuan = $this->statusTujuan($p);

            if ($tujuan === null) {
                continue;
            }

            $pesanan = Order::where(function ($q) use ($nomor) {
                $q->where('nomor_pesanan', $nomor)->orWhere('nomor_pesanan', 'like', $nomor.'-%');
            })->get();

            foreach ($pesanan as $o) {
                $sekarang = $o->status->value;

                if ($sekarang === $tujuan) {
                    continue;
                }

                // Batal & retur menang atas apa pun: keduanya menceritakan barang
                // yang nasibnya berubah, bukan sekadar tahap yang belum sampai.
                $paksa = in_array($tujuan, ['batal', 'retur'], true);

                if (! $paksa && self::URUTAN[$tujuan] <= (self::URUTAN[$sekarang] ?? 0)) {
                    continue;
                }

                $patch = ['status' => $tujuan];

                if ($tujuan === 'lunas') {
                    $patch['tgl_cair'] = $p['tgl_cair'] !== '' ? Carbon::parse($p['tgl_cair']) : now();
                }

                $o->update($patch);
                $n++;
            }
        }

        return $n;
    }

    /** Urutan maju status; batal & retur di luar urutan ini (lihat `sinkronkanStatus`). */
    private const URUTAN = ['dipesan' => 1, 'dikirim' => 2, 'lunas' => 3, 'retur' => 3, 'batal' => 3];

    /**
     * Status ERP TM → status di sini.
     *
     * `cair` menang atas status internal: uang yang sudah turun adalah fakta
     * yang lebih menentukan daripada tahap pengirimannya.
     *
     * @param  array<string, mixed>  $pesanan
     */
    private function statusTujuan(array $pesanan): ?string
    {
        if ($pesanan['cair'] ?? false) {
            return 'lunas';
        }

        return match ($pesanan['status_erp'] ?? '') {
            'batal' => 'batal',
            'menunggu_retur' => 'retur',
            'dikirim', 'selesai' => 'dikirim',
            'baru' => 'dipesan',
            default => null,
        };
    }
}

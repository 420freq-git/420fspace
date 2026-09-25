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
        $balasan = $this->erp->penjualan($dari, $sampai);
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

            if ($cutoff && $tglCair !== '' && $tglCair <= $cutoff) {
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
                'items' => [],
            ];

            $perPesanan[$nomor]['items'][] = ['sku' => $sku, 'qty' => $qty];
            $ringkas['dipakai']++;
        }

        $hasil = $this->importer->importDariErp(array_values($perPesanan));

        /*
         * Pesanan yang ditarik di sini SUDAH cair — itu syarat masuknya. Mesin
         * import membuat pesanan berstatus `dipesan` karena ia melayani jalur
         * lain yang belum tentu cair, jadi statusnya dibereskan di sini.
         *
         * Tanpa ini pesanannya tidak akan pernah masuk `bisaDitagih()`, dan
         * tagihan yang justru jadi alasan tarikan ini ada tidak pernah terbit.
         */
        $ringkas['ditandai_cair'] = $this->tandaiCair($perPesanan);

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
     * @param  array<string, array<string, mixed>>  $perPesanan
     */
    private function tandaiCair(array $perPesanan): int
    {
        $n = 0;

        foreach ($perPesanan as $nomor => $p) {
            $tanggal = $p['tgl_cair'] !== '' ? Carbon::parse($p['tgl_cair']) : now();

            $pesanan = Order::where(function ($q) use ($nomor) {
                $q->where('nomor_pesanan', $nomor)->orWhere('nomor_pesanan', 'like', $nomor.'-%');
            })->get();

            foreach ($pesanan as $o) {
                // Retur & batal punya ceritanya sendiri; menimpanya jadi lunas
                // berarti menagih barang yang justru kembali.
                if (in_array($o->status->value, ['lunas', 'retur', 'batal'], true)) {
                    continue;
                }

                $o->update(['status' => 'lunas', 'tgl_cair' => $tanggal]);
                $n++;
            }
        }

        return $n;
    }
}

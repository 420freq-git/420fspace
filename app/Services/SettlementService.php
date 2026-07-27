<?php

namespace App\Services;

use App\Enums\SizeTier;
use App\Models\Batch;
use App\Models\BrandLedger;
use App\Models\Invoice;
use App\Models\Penarikan;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\VendorLedger;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function __construct(private StockService $stock) {}

    /**
     * Tagihan brand ke 420F — SATU definisi untuk semua halaman.
     *
     * Brand ditagih untuk barang yang sudah jadi kewajiban bayar — yaitu `Sale::sold()`:
     * pesanan **cair** (`lunas`) ATAU retur berkondisi **rusak**. Pesanan yang belum cair
     * (dipesan/dikirim) TIDAK menagih. Plus invoice buy-out. Batch cash dikecualikan karena
     * sudah dibayar penuh di muka saat batch disetujui.
     *
     * Retur rusak tetap ditagih — aturan terkunci (CLAUDE.md §4): barang hilang dari stok tapi
     * brand tetap membayar produksinya. Kalau retur rusak dikeluarkan dari tagihan, pesanan yang
     * invoice-nya SUDAH dibayar lalu diretur akan membuat sisa tagihan jadi MINUS (uang masuk
     * tercatat, tagihannya hilang).
     *
     * Dipusatkan di sini karena dashboard TM & 420F dulu memakai definisi berbeda untuk angka
     * yang sama, sehingga menampilkan sisa tagihan yang berbeda untuk data yang sama.
     *
     * @param  int|null  $brandId  null = seluruh brand (POV 420F)
     * @return array{penjualan:int, buyout:int, total:int, ditransfer:int, sisa:int}
     */
    public function tagihanBrand(?int $brandId = null): array
    {
        $penjualan = (int) Sale::consignment()->sold()
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->whereNotNull('harga_tm420')
            ->sum(DB::raw('qty * harga_tm420'));

        $buyout = (int) Invoice::when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->where('jumlah_manual', '>', 0)
            ->sum('jumlah_manual');

        // Transfer penjualan saja: buy-out lama & cash batch punya jalur sendiri.
        $ditransfer = (int) BrandLedger::when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->where(function ($w) {
                $w->whereNull('keterangan')
                    ->orWhere(fn ($q) => $q->where('keterangan', 'not like', 'Buy-out sisa stok%')
                        ->where('keterangan', 'not like', 'Cash batch%'));
            })
            ->sum('jumlah');

        $total = $penjualan + $buyout;

        return [
            'penjualan' => $penjualan,
            'buyout' => $buyout,
            'total' => $total,
            'ditransfer' => $ditransfer,
            'sisa' => $total - $ditransfer,
        ];
    }

    /**
     * Rincian artikel×ukuran yang dibeli saat buy-out sebuah batch — untuk ditampilkan di invoice.
     *
     * Direkonstruksi dari (diterima − terjual), BUKAN dari `availableInBatch()`: begitu batch
     * ditandai `dibuyout`, availableInBatch sengaja mengembalikan 0 karena stok sudah keluar pool
     * jual, sehingga rinciannya tak bisa dibaca lagi lewat jalur biasa.
     *
     * Nilai yang mengikat tetap `invoices.jumlah_manual` (tersimpan saat buy-out). Rincian ini
     * hasil hitung ulang, jadi pemanggil sebaiknya membandingkan `pcs`/`nilai` di sini dengan
     * yang tersimpan di invoice dan memberi tahu bila berbeda.
     *
     * @return array{baris: array<int, array{product: Product|null, sizes: array<int, array{ukuran:string, qty:int, harga:int, subtotal:int}>, pcs:int, nilai:int}>, pcs:int, nilai:int}
     */
    public function rincianBuyout(Batch $batch): array
    {
        $pos = PurchaseOrder::where('batch_id', $batch->id)->with('sizeItems')->get();

        $combos = [];
        foreach ($pos as $po) {
            foreach ($po->sizeItems as $si) {
                $combos[$po->product_id.'|'.$si->ukuran->value] = [$po->product_id, $si->ukuran->value];
            }
        }
        if (empty($combos)) {
            return ['baris' => [], 'pcs' => 0, 'nilai' => 0];
        }

        $products = Product::with('category.prices')
            ->whereIn('id', array_unique(array_map(fn ($c) => $c[0], $combos)))
            ->get()->keyBy('id');

        $urutUkuran = array_flip(array_map(fn ($u) => $u->value, \App\Enums\Ukuran::cases()));

        $perProduk = [];
        $pcsTotal = 0;
        $nilaiTotal = 0;

        foreach ($combos as [$pid, $uk]) {
            $qty = $this->stock->receivedInBatch($batch->id, (int) $pid, $uk)
                - $this->stock->soldInBatch($batch->id, (int) $pid, $uk);
            if ($qty <= 0) {
                continue;
            }

            $product = $products->get($pid);
            $harga = $product?->hargaTagihan(SizeTier::forUkuran($uk)) ?? 0;
            $subtotal = $qty * $harga;

            $perProduk[$pid] ??= ['product' => $product, 'sizes' => [], 'pcs' => 0, 'nilai' => 0];
            $perProduk[$pid]['sizes'][] = ['ukuran' => $uk, 'qty' => $qty, 'harga' => $harga, 'subtotal' => $subtotal];
            $perProduk[$pid]['pcs'] += $qty;
            $perProduk[$pid]['nilai'] += $subtotal;

            $pcsTotal += $qty;
            $nilaiTotal += $subtotal;
        }

        foreach ($perProduk as &$p) {
            usort($p['sizes'], fn ($a, $b) => ($urutUkuran[$a['ukuran']] ?? 99) <=> ($urutUkuran[$b['ukuran']] ?? 99));
        }
        unset($p);

        $baris = array_values($perProduk);
        usort($baris, fn ($a, $b) => strcmp($a['product']->nama_artikel ?? '', $b['product']->nama_artikel ?? ''));

        return ['baris' => $baris, 'pcs' => $pcsTotal, 'nilai' => $nilaiTotal];
    }

    /**
     * Hak Diferd atas batch ini = dari barang terjual + dari buy-out sisa stok.
     * Buy-out kini menambah hak (bukan langsung dibayar): 420F beli sisa stok dari Diferd, hak-nya
     * ditutup belakangan lewat penarikan/pembayaran seperti hak penjualan biasa.
     */
    public function kewajiban(int $batchId): int
    {
        return $this->hakJual($batchId) + $this->buyout($batchId);
    }

    /** Hak Diferd dari barang batch ini yang sudah terjual (tanpa buy-out). */
    public function hakJual(int $batchId): int
    {
        return (int) Sale::where('batch_id', $batchId)->sold()->sum(DB::raw('qty * harga_diferd'));
    }

    /** Fee 420F dari batch ini = margin penjualan lunas + margin buy-out (invoice TM − hak Diferd). */
    public function fee420f(int $batchId): int
    {
        $jual = (int) Sale::where('batch_id', $batchId)
            ->whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->whereNotNull('harga_tm420')->sum(DB::raw('qty * (harga_tm420 - harga_diferd)'));

        // Buy-out: 420F tagih TM di harga_tm420 (invoice) tapi bayar Diferd di harga_diferd (hak).
        $buyoutMargin = $this->buyoutInvoiceTm($batchId) - $this->buyout($batchId);

        return $jual + $buyoutMargin;
    }

    /** Total tagihan invoice buy-out (harga tm420) untuk batch ini. */
    public function buyoutInvoiceTm(int $batchId): int
    {
        return (int) Invoice::where('batch_id', $batchId)->where('jumlah_manual', '>', 0)->sum('jumlah_manual');
    }

    /** Pembayaran yang ditandai langsung ke batch ini (tidak termasuk deposit & buy-out). */
    public function pembayaranLedger(int $batchId): int
    {
        return (int) VendorLedger::where('batch_id', $batchId)->where('tipe', 'pembayaran')->sum('jumlah');
    }

    /**
     * Penarikan Diferd bersifat GLOBAL (tidak terikat batch), tapi uangnya tetap menutup hak per
     * batch. Method ini merencanakan pembagiannya secara FIFO: batch tertua yang haknya belum
     * tertutup dibayar lebih dulu. Rencana ini dipakai untuk pratinjau di layar persetujuan, lalu
     * DIBEKUKAN jadi baris ledger saat penarikan disetujui — supaya sisa per batch tidak bergeser
     * lagi kalau ada retur/penjualan susulan setelah batch dinyatakan lunas.
     *
     * @return array{alokasi: array<int,int>, sisa: int} batch_id => jumlah, + surplus tak terserap
     */
    public function rencanaAlokasi(int $jumlah): array
    {
        $sisa = $jumlah;
        $alokasi = [];

        foreach (Batch::orderBy('tanggal_order')->orderBy('id')->get(['id']) as $b) {
            if ($sisa <= 0) {
                break;
            }

            $belum = max(0, $this->kewajiban($b->id) - $this->pembayaranLedger($b->id));
            if ($belum <= 0) {
                continue;
            }

            $pakai = min($sisa, $belum);
            $alokasi[$b->id] = $pakai;
            $sisa -= $pakai;
        }

        return ['alokasi' => $alokasi, 'sisa' => $sisa];
    }

    /** Pembayaran ke batch ini yang berasal dari penarikan (sudah dibekukan di ledger). */
    public function penarikanBatch(int $batchId): int
    {
        return (int) VendorLedger::where('batch_id', $batchId)
            ->where('tipe', 'pembayaran')->whereNotNull('penarikan_id')->sum('jumlah');
    }

    /**
     * Penarikan cair yang tidak terserap batch mana pun — dari penjualan yang tidak terikat batch.
     * Tetap dihitung sebagai hak terbayar secara global, hanya tidak punya batch tujuan.
     */
    public function penarikanBelumTeralokasi(): int
    {
        $cair = (int) Penarikan::where('status', 'disetujui')->sum('jumlah');
        $teralokasi = (int) VendorLedger::whereNotNull('penarikan_id')->sum('jumlah');

        return max(0, $cair - $teralokasi);
    }

    /** Total yang sudah diterima Diferd atas hak batch ini (manual + hasil penarikan). */
    public function terbayar(int $batchId): int
    {
        return $this->pembayaranLedger($batchId);
    }

    /** Buy-out sisa stok oleh 420F di deadline. */
    public function buyout(int $batchId): int
    {
        return (int) VendorLedger::where('batch_id', $batchId)->where('tipe', 'buyout')->sum('jumlah');
    }

    /**
     * Modal produksi (deposit) yang BELUM direkonsiliasi. Setelah rekonsiliasi, deposit di-offset
     * menjadi pembayaran (masuk 'terbayar') sehingga modal jadi 0.
     */
    public function modalProduksi(Batch $batch): int
    {
        if ($batch->deposit_rekonsiliasi) {
            return 0;
        }

        return (int) $batch->deposit_awal
            + (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'deposit')->sum('jumlah');
    }

    /**
     * Modal (deposit) yang masih MENGENDAP di vendor — tingkat kerja sama, bukan per batch.
     *
     * Deposit hanya terjadi sekali di awal kerja sama; ia bukan atribut batch. Nilainya bertahan
     * sampai diselesaikan sekali di akhir (di-offset ke sisa hak, atau dikembalikan vendor) lewat
     * baris ledger tipe deposit_selesai. Suku Batch::deposit_awal dipertahankan untuk data lama
     * yang belum dimigrasikan — setelah migrasi nilainya 0.
     */
    public function depositMengendap(): int
    {
        return (int) Batch::where('deposit_rekonsiliasi', false)->sum('deposit_awal')
            + (int) VendorLedger::where('tipe', 'deposit')->sum('jumlah')
            - (int) VendorLedger::where('tipe', 'deposit_selesai')->sum('jumlah');
    }

    /** Sisa hak vendor dari barang terjual (positif = 420F masih harus bayar). Deposit TIDAK memotong. */
    public function saldo(Batch $batch): int
    {
        return $this->kewajiban($batch->id) - $this->terbayar($batch->id);
    }

    /**
     * Batch benar-benar TUNTAS → layak berstatus Lunas. Syarat semuanya:
     *  - status masih Aktif,
     *  - produksi & pengiriman selesai (semua PO terkirim),
     *  - hak Diferd lunas (saldo ≤ 0),
     *  - tak ada sisa stok jual (semua terjual / di-buy-out / cash keluar sistem).
     */
    public function batchTuntas(Batch $batch): bool
    {
        if ($batch->status !== \App\Enums\BatchStatus::Aktif) {
            return false;
        }
        $pos = $batch->relationLoaded('purchaseOrders') ? $batch->purchaseOrders : $batch->purchaseOrders()->get();
        if ($pos->isEmpty() || ! $pos->every(fn ($p) => $p->tahap === \App\Enums\TahapProduksi::Terkirim)) {
            return false;
        }

        return $this->saldo($batch) <= 0 && $this->sisaStok($batch)['pcs'] === 0;
    }

    /**
     * Tandai semua batch Aktif yang sudah tuntas menjadi Lunas (rekonsiliasi status).
     * Idempoten & satu arah (hanya Aktif → Lunas). Dipanggil saat daftar batch/settlement dibuka.
     */
    public function reconcileLunas(?int $brandId = null): int
    {
        $q = Batch::with('purchaseOrders')->where('status', \App\Enums\BatchStatus::Aktif->value);
        if ($brandId) {
            $q->where('brand_id', $brandId);
        }

        $n = 0;
        foreach ($q->get() as $batch) {
            if ($this->batchTuntas($batch)) {
                $batch->update(['status' => \App\Enums\BatchStatus::Lunas->value]);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Nilai batch untuk pembayaran CASH (beli putus di muka), dari seluruh qty PO:
     *  - diferd: yang 420F bayar ke Diferd
     *  - tm420 : yang TM bayar ke 420F (sesuai harga pihak; VOOJAH = diferd)
     *  - fee   : margin 420F (tm420 − diferd)
     * @return array{diferd:int, tm420:int, fee:int, pcs:int}
     */
    public function cashTotals(Batch $batch): array
    {
        $pos = PurchaseOrder::where('batch_id', $batch->id)->with(['product.category.prices', 'sizeItems'])->get();

        $diferd = $tm420 = $pcs = 0;
        foreach ($pos as $po) {
            foreach ($po->sizeItems as $si) {
                $tier = SizeTier::forUkuran($si->ukuran->value);
                $qty = (int) $si->qty;
                $pcs += $qty;
                $diferd += $qty * (int) ($po->product->effectiveDiferd($tier) ?? 0);
                $tm420 += $qty * (int) ($po->product->hargaTagihan($tier) ?? 0);
            }
        }

        return ['diferd' => $diferd, 'tm420' => $tm420, 'fee' => $tm420 - $diferd, 'pcs' => $pcs];
    }

    /**
     * Kewajiban GANTI Diferd untuk batch cash. Karena cash dibayar penuh di muka untuk seluruh
     * qty PO, tiap pcs yang akhirnya tidak sampai diterima brand (reject QC + kurang/cacat saat
     * terima) berarti Diferd dibayar untuk barang yang tak ada → wajib diganti (barang / refund).
     * Dihitung HANYA untuk PO yang sudah ditutup (tahap terkirim), supaya PO yang masih jalan tidak
     * ikut. Basis = qty PO − qty diterima = produced − received per produk+ukuran.
     *
     * @return array{pcs:int, diferd:int, tm420:int}
     */
    public function gantiCashObligasi(Batch $batch): array
    {
        if (! $batch->isCash()) {
            return ['pcs' => 0, 'diferd' => 0, 'tm420' => 0];
        }

        $pos = PurchaseOrder::where('batch_id', $batch->id)
            ->where('tahap', \App\Enums\TahapProduksi::Terkirim->value)
            ->with(['product.category.prices', 'sizeItems'])->get();

        $pcs = $diferd = $tm420 = 0;
        foreach ($pos as $po) {
            foreach ($po->sizeItems as $si) {
                $ukuran = $si->ukuran->value;
                $received = $this->stock->receivedInBatch($batch->id, $po->product_id, $ukuran);
                $kurang = max(0, (int) $si->qty - $received);
                if ($kurang <= 0) {
                    continue;
                }
                $tier = SizeTier::forUkuran($ukuran);
                $pcs += $kurang;
                $diferd += $kurang * (int) ($po->product->effectiveDiferd($tier) ?? 0);
                $tm420 += $kurang * (int) ($po->product->hargaTagihan($tier) ?? 0);
            }
        }

        return ['pcs' => $pcs, 'diferd' => $diferd, 'tm420' => $tm420];
    }

    /**
     * Yang sudah diselesaikan atas kewajiban ganti cash batch ini.
     * @return array{pcs:int, barang_pcs:int, refund_pcs:int, refund_diferd:int, refund_tm420:int}
     */
    public function gantiCashTerselesaikan(Batch $batch): array
    {
        $rows = \App\Models\CashGanti::where('batch_id', $batch->id)->get();

        return [
            'pcs' => (int) $rows->sum('pcs'),
            'barang_pcs' => (int) $rows->where('metode', 'barang')->sum('pcs'),
            'refund_pcs' => (int) $rows->where('metode', 'refund')->sum('pcs'),
            'refund_diferd' => (int) $rows->where('metode', 'refund')->sum('nilai_diferd'),
            'refund_tm420' => (int) $rows->where('metode', 'refund')->sum('nilai_tm420'),
        ];
    }

    /**
     * Sisa kewajiban ganti yang belum diselesaikan (obligasi − yang sudah ditangani).
     * @return array{pcs:int, diferd:int, tm420:int}
     */
    public function gantiCashSisa(Batch $batch): array
    {
        $ob = $this->gantiCashObligasi($batch);
        $done = $this->gantiCashTerselesaikan($batch);
        $sisaPcs = max(0, $ob['pcs'] - $done['pcs']);

        if ($sisaPcs <= 0 || $ob['pcs'] <= 0) {
            return ['pcs' => 0, 'diferd' => 0, 'tm420' => 0];
        }

        // Nilai sisa diprorata dari harga rata-rata obligasi (ukuran bisa beda harga).
        return [
            'pcs' => $sisaPcs,
            'diferd' => (int) round($ob['diferd'] * $sisaPcs / $ob['pcs']),
            'tm420' => (int) round($ob['tm420'] * $sisaPcs / $ob['pcs']),
        ];
    }

    /**
     * Jalankan pembayaran cash batch (beli putus di muka): buat ledger Diferd (cash) + BrandLedger
     * (TM bayar 420F), tandai batch cash_dibayar. Idempoten — tidak melakukan apa-apa bila bukan
     * cash, sudah dibayar, atau belum ada PO. Return totals bila berhasil, null bila dilewati.
     *
     * @return array{diferd:int, tm420:int, fee:int, pcs:int}|null
     */
    /**
     * Pecahan pembayaran cash bila pakai DP: bagian DP (di muka) & sisa (saat siap kirim).
     *
     * DP diisi sebagai NOMINAL Rp pada sisi TAGIHAN (tm420) = yang ditagih ke brand di muka.
     * Sisi Diferd (modal) diturunkan PROPORSIONAL (rasio DP/total tagihan) supaya fee 420F tetap
     * konsisten. Sisa = total − DP (bukan hitung ulang) → DP + sisa = total persis, tanpa drift.
     * DP di-cap ke total (jaga-jaga); batas "DP < total" ditegakkan saat approval.
     *
     * @return array{dp_nominal:int, dp:array{diferd:int,tm420:int,fee:int}, sisa:array{diferd:int,tm420:int,fee:int}}
     */
    public function cashDpSplit(Batch $batch): array
    {
        $t = $this->cashTotals($batch);

        $dpTm = min((int) $batch->dp_nominal, $t['tm420']);
        $ratio = $t['tm420'] > 0 ? $dpTm / $t['tm420'] : 0;
        $dpDiferd = (int) round($t['diferd'] * $ratio);

        return [
            'dp_nominal' => (int) $batch->dp_nominal,
            'dp' => ['diferd' => $dpDiferd, 'tm420' => $dpTm, 'fee' => $dpTm - $dpDiferd],
            'sisa' => ['diferd' => $t['diferd'] - $dpDiferd, 'tm420' => $t['tm420'] - $dpTm, 'fee' => ($t['tm420'] - $dpTm) - ($t['diferd'] - $dpDiferd)],
        ];
    }

    /**
     * Proses pembayaran cash saat batch disetujui.
     * - Cash biasa (tanpa DP): bayar PENUH di muka (Diferd lunas, TM bayar 420F, stok keluar).
     * - Cash dengan DP: bayar DP saja (Brand→420F→Diferd proporsional). Sisa dilunasi saat siap
     *   kirim lewat lunasiSisaCash(). `cash_dibayar` (stok keluar) baru true setelah lunas penuh.
     */
    /** Nomor invoice batch cash — format sama dgn invoice lain (INV.<kode>.<mm.yy>.NN). */
    public function invoiceNomor(\App\Models\Brand $brand): string
    {
        $base = 'INV.'.\App\Http\Controllers\BatchController::brandKode($brand).'.'.now()->format('m.y').'.';
        $n = Invoice::where('brand_id', $brand->id)->whereYear('tanggal_terbit', now()->year)->count() + 1;
        do {
            $nomor = $base.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $n++;
        } while (Invoice::where('nomor', $nomor)->exists());

        return $nomor;
    }

    /** Invoice cash batch yang sudah terbit (DP lalu pelunasan), urut terbit. */
    public function invoiceCash(Batch $batch)
    {
        return Invoice::where('batch_id', $batch->id)->where('jenis', 'cash')->orderBy('id')->get();
    }

    private function buatInvoiceCash(Batch $batch, int $nilaiTm, int $pcs, string $label): Invoice
    {
        $batch->loadMissing('brand');

        return Invoice::create([
            'brand_id' => $batch->brand_id, 'batch_id' => $batch->id, 'jenis' => 'cash',
            'nomor' => $this->invoiceNomor($batch->brand), 'tanggal_terbit' => now(), 'status' => 'belum_bayar',
            'jumlah_manual' => $nilaiTm, 'pcs_manual' => $pcs,
            'catatan' => $label.' — batch cash '.$batch->nomor_batch,
        ]);
    }

    /**
     * Batch cash DISETUJUI → terbitkan invoice TAGIHAN pertama ke TM (bukan auto-catat uang).
     * Cash biasa: tagih penuh. Cash DP: tagih DP% dulu. Uang masuk hanya saat invoice lunas (+bukti).
     * 420F→Diferd tak disentuh di sini — aksi terpisah `bayarDiferdCash` dengan buktinya sendiri.
     */
    public function prosesCashBatch(Batch $batch): ?Invoice
    {
        if (! $batch->isCash() || $batch->cash_dibayar) {
            return null;
        }
        if ($this->invoiceCash($batch)->isNotEmpty()) {
            return null;   // sudah pernah diterbitkan
        }
        $t = $this->cashTotals($batch);
        if ($t['pcs'] <= 0) {
            return null;
        }

        if ($batch->isCashDP()) {
            $dp = $this->cashDpSplit($batch)['dp'];

            return $this->buatInvoiceCash($batch, $dp['tm420'], $t['pcs'], 'DP Rp '.number_format($dp['tm420'], 0, ',', '.'));
        }

        return $this->buatInvoiceCash($batch, $t['tm420'], $t['pcs'], 'Tagihan cash penuh');
    }

    /** Semua PO batch sudah DITERIMA (tahap terkirim) → reject/kurang sudah final. */
    private function semuaDiterima(Batch $batch): bool
    {
        $pos = $batch->relationLoaded('purchaseOrders') ? $batch->purchaseOrders : $batch->purchaseOrders()->get();

        return $pos->isNotEmpty() && $pos->every(fn ($p) => $p->tahap === \App\Enums\TahapProduksi::Terkirim);
    }

    /**
     * Nilai pelunasan cash DP SETELAH dipotong reject (kurang saat penerimaan).
     * Reject di batch DP tidak lewat alur refund — langsung dipotong dari pelunasan: TM bayar lebih
     * sedikit, dan 420F bayar Diferd lebih sedikit (Diferd tak dibayar untuk pcs yang tak sampai).
     *
     * @return array{tm420:int, diferd:int, reject_pcs:int, reject_tm420:int, reject_diferd:int}
     */
    public function sisaCashNetReject(Batch $batch): array
    {
        $sisa = $this->cashDpSplit($batch)['sisa'];
        $reject = $this->gantiCashObligasi($batch);   // pcs + nilai diferd/tm420 dari kurang-terima

        return [
            'tm420' => max(0, $sisa['tm420'] - $reject['tm420']),
            'diferd' => max(0, $sisa['diferd'] - $reject['diferd']),
            'reject_pcs' => $reject['pcs'],
            'reject_tm420' => $reject['tm420'],
            'reject_diferd' => $reject['diferd'],
        ];
    }

    /**
     * Terbitkan invoice PELUNASAN cash DP — dibuka SETELAH TM terima barang (reject sudah final),
     * nilainya = sisa − reject. Butuh invoice DP sudah ada.
     */
    public function terbitSisaCash(Batch $batch): ?Invoice
    {
        if (! $batch->isCashDP() || $this->invoiceCash($batch)->count() !== 1 || ! $this->semuaDiterima($batch)) {
            return null;
        }
        $net = $this->sisaCashNetReject($batch);
        $label = $net['reject_pcs'] > 0
            ? 'Pelunasan sisa (−'.$net['reject_pcs'].' pcs reject)'
            : 'Pelunasan sisa';

        return $this->buatInvoiceCash($batch, $net['tm420'], 0, $label);
    }

    /** Total modal (diferd) yang sudah dibayar 420F ke Diferd untuk batch cash ini. */
    public function diferdCashDibayar(Batch $batch): int
    {
        return (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'cash')->sum('jumlah');
    }

    /**
     * Bayar Diferd tahap berikutnya (DP-modal dulu, sisa-modal saat pelunasan) — dengan bukti.
     * Return nilai yang dibayar, atau null bila tak ada tahap tersisa.
     */
    public function bayarDiferdCash(Batch $batch, ?string $bukti = null): ?int
    {
        if (! $batch->isCash()) {
            return null;
        }
        $total = $this->cashTotals($batch)['diferd'];
        $dibayar = $this->diferdCashDibayar($batch);
        if ($dibayar >= $total) {
            return null;
        }

        if ($batch->isCashDP()) {
            $dpModal = $this->cashDpSplit($batch)['dp']['diferd'];
            if ($dibayar < $dpModal) {
                [$nilai, $label] = [$dpModal - $dibayar, 'DP-modal'];
            } else {
                // Sisa-modal (DP): baru setelah barang DITERIMA (reject final) & DIPOTONG reject —
                // Diferd tak dibayar untuk pcs yang tak sampai.
                if (! $this->semuaDiterima($batch)) {
                    return null;
                }
                $net = $this->sisaCashNetReject($batch);
                $nilai = $net['diferd'] - ($dibayar - $dpModal);   // sisa-modal net reject, dikurangi yg sudah dicicil
                if ($nilai <= 0) {
                    return null;
                }
                $label = $net['reject_pcs'] > 0 ? 'pelunasan modal (−reject)' : 'pelunasan modal';
            }
        } else {
            [$nilai, $label] = [$total - $dibayar, 'bayar modal penuh'];
        }

        VendorLedger::create([
            'brand_id' => $batch->brand_id, 'batch_id' => $batch->id, 'tanggal' => now(),
            'tipe' => \App\Enums\LedgerTipe::Cash->value, 'jumlah' => $nilai,
            'keterangan' => 'Cash batch '.$batch->nomor_batch.' — '.$label.' ke Diferd',
            'bukti_transfer' => $bukti,
        ]);

        return $nilai;
    }

    /**
     * Set `cash_dibayar` (stok keluar sistem) bila SEMUA invoice cash TM sudah lunas.
     * DP butuh 2 invoice (DP + sisa) lunas; cash biasa butuh 1. Dipanggil dari markPaid.
     */
    public function reconcileCashLunas(Batch $batch): void
    {
        if (! $batch->isCash() || $batch->cash_dibayar) {
            return;
        }
        $inv = $this->invoiceCash($batch);
        $butuh = $batch->isCashDP() ? 2 : 1;
        if ($inv->count() >= $butuh && $inv->every(fn ($i) => $i->status === 'lunas')) {
            $batch->update(['cash_dibayar' => true, 'tgl_cash' => now()]);
        }
    }

    /**
     * Keadaan lengkap pembayaran cash sebuah batch (untuk UI Settlement).
     * @return array<string,mixed>
     */
    public function cashStatus(Batch $batch): array
    {
        $t = $this->cashTotals($batch);
        $split = $batch->isCashDP() ? $this->cashDpSplit($batch) : null;
        $inv = $this->invoiceCash($batch);
        $diferdDibayar = $this->diferdCashDibayar($batch);

        $diterima = $this->semuaDiterima($batch);

        $dpModal = $split ? $split['dp']['diferd'] : 0;
        $tahapDiferd = ! $batch->isCashDP() ? 'penuh' : ($diferdDibayar < $dpModal ? 'dp' : 'sisa');

        // Modal efektif ke Diferd: DP batch yg sudah diterima dipotong reject (Diferd tak dibayar
        // untuk pcs yang tak sampai). Sebelum diterima, reject belum final → pakai total penuh.
        $rejectModal = ($batch->isCashDP() && $diterima) ? $this->gantiCashObligasi($batch)['diferd'] : 0;
        $diferdEfektif = max(0, $t['diferd'] - $rejectModal);

        return [
            'pakai_dp' => $batch->isCashDP(),
            'dp_nominal' => $split ? (int) $split['dp']['tm420'] : 0,
            'totals' => $t,
            'split' => $split,
            'dp_inv' => $inv->get(0),
            'sisa_inv' => $inv->get(1),
            'jumlah_invoice' => $inv->count(),
            'lunas' => (bool) $batch->cash_dibayar,
            'diterima' => $diterima,
            'reject_modal' => $rejectModal,
            // Sisi Diferd (420F→vendor) — total sudah net reject bila DP & diterima.
            'diferd_total' => $diferdEfektif,
            'diferd_dibayar' => $diferdDibayar,
            'diferd_sisa' => max(0, $diferdEfektif - $diferdDibayar),
            'tahap_diferd' => $tahapDiferd,          // dp | sisa | penuh
            // Aksi tersedia — pelunasan (invoice sisa & sisa-modal) baru SETELAH barang diterima.
            'bisa_terbit_sisa' => $batch->isCashDP() && $inv->count() === 1 && $diterima,
            'bisa_bayar_diferd' => $diferdDibayar < $diferdEfektif
                && ($tahapDiferd !== 'sisa' || $diterima),
        ];
    }

    /** Nilai sisa stok belum terjual di batch ini (× harga Diferd) — dasar buy-out. */
    public function sisaStokValue(Batch $batch): int
    {
        return $this->sisaStok($batch)['nilai'];
    }

    /**
     * Sisa stok belum terjual di batch: jumlah pcs & nilainya.
     *  - nilai    : × harga Diferd (hak Diferd bila di-buy-out)
     *  - nilai_tm : × harga tagihan TM (yang ditagihkan ke TM saat buy-out; VOOJAH = diferd)
     * @return array{pcs:int, nilai:int, nilai_tm:int}
     */
    public function sisaStok(Batch $batch): array
    {
        $pos = PurchaseOrder::where('batch_id', $batch->id)->with('sizeItems')->get();

        $combos = [];
        foreach ($pos as $po) {
            foreach ($po->sizeItems as $si) {
                $combos[$po->product_id.'|'.$si->ukuran->value] = [
                    'product_id' => $po->product_id,
                    'ukuran' => $si->ukuran->value,
                ];
            }
        }

        if (empty($combos)) {
            return ['pcs' => 0, 'nilai' => 0, 'nilai_tm' => 0];
        }

        $products = Product::with('category.prices')
            ->whereIn('id', array_unique(array_column($combos, 'product_id')))
            ->get()->keyBy('id');

        $pcs = 0; $nilai = 0; $nilaiTm = 0;
        foreach ($combos as $c) {
            $available = $this->stock->availableInBatch($batch->id, $c['product_id'], $c['ukuran']);
            if ($available <= 0) {
                continue;
            }
            $product = $products->get($c['product_id']);
            $tier = SizeTier::forUkuran($c['ukuran']);
            $diferd = $product?->effectiveDiferd($tier) ?? 0;
            $tm = $product?->hargaTagihan($tier) ?? 0;
            $pcs += $available;
            $nilai += $available * $diferd;
            $nilaiTm += $available * $tm;
        }

        return ['pcs' => $pcs, 'nilai' => $nilai, 'nilai_tm' => $nilaiTm];
    }

    /** Ringkasan lengkap satu batch. */
    public function batchSummary(Batch $batch): array
    {
        $ledgerDeposit = (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'deposit')->sum('jumlah');

        // Batch CASH: settlement konsinyasi tak berlaku — hak Diferd = dibayar (cash), saldo 0.
        if ($batch->isCash()) {
            $cash = $this->cashTotals($batch);
            $terbayarCash = (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'cash')->sum('jumlah');
            $gantiOb = $this->gantiCashObligasi($batch);
            $gantiDone = $this->gantiCashTerselesaikan($batch);
            $gantiSisa = $this->gantiCashSisa($batch);

            // Alur cash berbasis TAGIHAN: TM ditagih lewat invoice, 420F bayar Diferd terpisah + bukti.
            $cs = $this->cashStatus($batch);

            return [
                'cash' => true,
                'cash_dibayar' => (bool) $batch->cash_dibayar,
                'dp_nominal' => (int) ($batch->dp_nominal ?? 0),
                'cash_status' => $cs,
                'kewajiban' => $cash['diferd'],
                'hak_jual' => $cash['diferd'],
                'terbayar' => $cs['diferd_dibayar'],
                'pembayaran' => $cs['diferd_dibayar'],
                'penarikan' => 0,
                'saldo' => $cs['diferd_sisa'],
                'deposit' => (int) $batch->deposit_awal,
                'ledger_deposit' => $ledgerDeposit,
                'modal' => $this->modalProduksi($batch),
                'rekonsiliasi' => (bool) $batch->deposit_rekonsiliasi,
                'buyout' => 0,
                'fee420f' => $cash['fee'],
                'sisa_stok_value' => 0,
                'cash_tm' => $cash['tm420'],
                // Kewajiban ganti Diferd atas reject (batch cash dibayar penuh di muka).
                'ganti_obligasi_pcs' => $gantiOb['pcs'],
                'ganti_obligasi_diferd' => $gantiOb['diferd'],
                'ganti_barang_pcs' => $gantiDone['barang_pcs'],
                'ganti_refund_pcs' => $gantiDone['refund_pcs'],
                'ganti_refund_diferd' => $gantiDone['refund_diferd'],
                'ganti_refund_tm420' => $gantiDone['refund_tm420'],
                'ganti_sisa_pcs' => $gantiSisa['pcs'],
                'ganti_sisa_diferd' => $gantiSisa['diferd'],
                'ganti_sisa_tm420' => $gantiSisa['tm420'],
            ];
        }

        $kewajiban = $this->kewajiban($batch->id);
        $terbayar = $this->pembayaranLedger($batch->id);   // sudah termasuk hasil penarikan
        $penarikan = $this->penarikanBatch($batch->id);
        $ledger = $terbayar - $penarikan;                  // sisanya = entri manual 420F

        return [
            'cash' => false,
            'kewajiban' => $kewajiban,
            'hak_jual' => $this->hakJual($batch->id),
            'terbayar' => $terbayar,
            'pembayaran' => $ledger,
            'penarikan' => $penarikan,
            'saldo' => $kewajiban - $terbayar,
            'deposit' => (int) $batch->deposit_awal,
            'ledger_deposit' => $ledgerDeposit,
            'modal' => $this->modalProduksi($batch),
            'rekonsiliasi' => (bool) $batch->deposit_rekonsiliasi,
            'buyout' => $this->buyout($batch->id),
            'fee420f' => $this->fee420f($batch->id),
            'sisa_stok_value' => $this->sisaStokValue($batch),
        ];
    }
}

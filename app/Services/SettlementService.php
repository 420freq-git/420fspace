<?php

namespace App\Services;

use App\Enums\SizeTier;
use App\Models\Batch;
use App\Models\BrandLedger;
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

    /** Fee 420F dari penjualan batch ini (hanya pesanan lunas). */
    public function fee420f(int $batchId): int
    {
        return (int) Sale::where('batch_id', $batchId)
            ->whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->whereNotNull('harga_tm420')->sum(DB::raw('qty * (harga_tm420 - harga_diferd)'));
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
    public function prosesCashBatch(Batch $batch): ?array
    {
        if (! $batch->isCash() || $batch->cash_dibayar) {
            return null;
        }
        $t = $this->cashTotals($batch);
        if ($t['pcs'] <= 0) {
            return null;
        }

        DB::transaction(function () use ($batch, $t) {
            VendorLedger::create([
                'brand_id' => $batch->brand_id, 'batch_id' => $batch->id, 'tanggal' => now(),
                'tipe' => \App\Enums\LedgerTipe::Cash->value, 'jumlah' => $t['diferd'],
                'keterangan' => 'Cash batch '.$batch->nomor_batch.' — bayar Diferd di muka ('.$t['pcs'].' pcs)',
            ]);
            BrandLedger::create([
                'brand_id' => $batch->brand_id, 'tanggal' => now(), 'jumlah' => $t['tm420'],
                'keterangan' => 'Cash batch '.$batch->nomor_batch.' (TM bayar 420F di muka)',
            ]);
            $batch->update(['cash_dibayar' => true, 'tgl_cash' => now()]);
        });

        return $t;
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

            return [
                'cash' => true,
                'cash_dibayar' => (bool) $batch->cash_dibayar,
                'kewajiban' => $cash['diferd'],
                'hak_jual' => $cash['diferd'],
                'terbayar' => $terbayarCash,
                'pembayaran' => $terbayarCash,
                'penarikan' => 0,
                'saldo' => $batch->cash_dibayar ? 0 : $cash['diferd'],
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

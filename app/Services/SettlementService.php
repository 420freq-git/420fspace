<?php

namespace App\Services;

use App\Enums\SizeTier;
use App\Models\Batch;
use App\Models\Penarikan;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\VendorLedger;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function __construct(private StockService $stock) {}

    /** Kewajiban ke vendor dari barang batch ini yang sudah terjual. */
    public function kewajiban(int $batchId): int
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

    /** Nilai sisa stok belum terjual di batch ini (× harga Diferd) — dasar buy-out. */
    public function sisaStokValue(Batch $batch): int
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
            return 0;
        }

        $products = Product::with('category.prices')
            ->whereIn('id', array_unique(array_column($combos, 'product_id')))
            ->get()->keyBy('id');

        $value = 0;
        foreach ($combos as $c) {
            $available = $this->stock->availableInBatch($batch->id, $c['product_id'], $c['ukuran']);
            if ($available <= 0) {
                continue;
            }
            $product = $products->get($c['product_id']);
            $diferd = $product?->effectiveDiferd(SizeTier::forUkuran($c['ukuran'])) ?? 0;
            $value += $available * $diferd;
        }

        return $value;
    }

    /** Ringkasan lengkap satu batch. */
    public function batchSummary(Batch $batch): array
    {
        $kewajiban = $this->kewajiban($batch->id);
        $terbayar = $this->pembayaranLedger($batch->id);   // sudah termasuk hasil penarikan
        $penarikan = $this->penarikanBatch($batch->id);
        $ledger = $terbayar - $penarikan;                  // sisanya = entri manual 420F
        $ledgerDeposit = (int) VendorLedger::where('batch_id', $batch->id)->where('tipe', 'deposit')->sum('jumlah');

        return [
            'kewajiban' => $kewajiban,
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

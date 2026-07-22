<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\SizeTier;
use App\Enums\Ukuran;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokController extends Controller
{
    private const KRITIS = 5;   // sisa ≤ ini = kritis/merah
    private const TIPIS = 15;   // sisa ≤ ini = menipis/amber

    public function __construct(private StockService $stock) {}

    /** @return array{label:string,tone:string} */
    private function statusStok(int $produced, int $sisa): array
    {
        if ($produced === 0) {
            return ['label' => 'Belum produksi', 'tone' => 'sand'];
        }
        if ($sisa <= 0) {
            return ['label' => 'Habis', 'tone' => 'red'];
        }
        if ($sisa <= self::KRITIS) {
            return ['label' => 'Kritis', 'tone' => 'red'];
        }
        if ($sisa <= self::TIPIS) {
            return ['label' => 'Menipis', 'tone' => 'amber'];
        }

        return ['label' => 'Aman', 'tone' => 'brand'];
    }

    public function index(Request $request)
    {
        $query = Product::with(['brand', 'category.prices'])->where('aktif', true)->orderBy('nama_artikel');

        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        // Basis nilai stok: TM420 pakai harga dari 420F (harga_tm420); 420F & Diferd pakai harga ke 420F (harga_diferd).
        $pakaiTm = in_array($user->role, [Role::Tm420, Role::Voojah], true);

        $rows = [];
        foreach ($query->get() as $p) {
            $sizes = [];
            $totProduced = $totSold = $totDiterima = $totBuyout = 0;
            $totVendor = $totJalan = $totBelumCair = $totReject = $totRejectArsip = 0;
            $nilai = 0;
            foreach (Ukuran::cases() as $u) {
                $prod = $this->stock->producedTotal($p->id, $u->value);
                $sold = $this->stock->soldTotal($p->id, $u->value);
                $terima = $this->stock->receivedTotal($p->id, $u->value);
                $vendor = $this->stock->unshippedTotal($p->id, $u->value);
                $jalan = $this->stock->inTransitTotal($p->id, $u->value);
                $belumCair = $this->stock->soldUnsettledTotal($p->id, $u->value);
                $reject = $this->stock->rejectTotal($p->id, $u->value);
                $rejectArsip = $this->stock->rejectSelesaiTotal($p->id, $u->value);
                $buyout = $this->stock->boughtOutTotal($p->id, $u->value);   // sudah jadi milik TM

                $sisaSize = $terima - $sold - $buyout;   // stok jual di tangan brand, di luar yg di-buyout
                $sizes[$u->value] = ['produced' => $prod, 'sold' => $sold, 'sisa' => $sisaSize];

                $totProduced += $prod;
                $totSold += $sold;
                $totDiterima += $terima;
                $totBuyout += $buyout;
                $totVendor += $vendor;
                $totJalan += $jalan;
                $totBelumCair += $belumCair;
                $totReject += $reject;
                $totRejectArsip += $rejectArsip;

                if ($sisaSize > 0) {
                    $tier = SizeTier::forUkuran($u->value);
                    // "Harga dari 420F" = harga yang 420F tagih ke brand: TM420 → tm420, VOOJAH → diferd
                    // (VOOJAH ditagih modal). hargaTagihan() sudah memilih sesuai tipe brand.
                    $harga = $pakaiTm ? $p->hargaTagihan($tier) : $p->effectiveDiferd($tier);
                    $nilai += $sisaSize * (int) ($harga ?? 0);
                }
            }
            $sisa = $totDiterima - $totSold - $totBuyout;   // stok jual, di luar yg sudah di-buyout
            $sellThrough = $totDiterima > 0 ? (int) round($totSold / $totDiterima * 100) : 0;
            $rows[] = [
                'product' => $p,
                'sizes' => $sizes,
                'produced' => $totProduced,
                'diterima' => $totDiterima,
                'sold' => $totSold,
                'sisa' => $sisa,
                'di_vendor' => $totVendor,
                'di_jalan' => $totJalan,
                'belum_cair' => $totBelumCair,
                'reject' => $totReject,
                'reject_arsip' => $totRejectArsip,
                'sell_through' => $sellThrough,
                'status' => $this->statusStok($totProduced, $sisa),
                // Saran produksi ulang: pernah diproduksi, stok tipis/habis, & laris (sell-through tinggi).
                'reorder' => $totProduced > 0 && $sisa <= self::TIPIS && $sellThrough >= 60,
                'saran_qty' => max($totSold, self::TIPIS + 1),
                'nilai' => $nilai,
                'kategori' => $p->category->nama ?? 'Tanpa kategori',
            ];
        }

        $reorderCount = collect($rows)->where('reorder', true)->count();
        $tipisCount = collect($rows)->filter(fn ($r) => $r['produced'] > 0 && $r['sisa'] <= self::TIPIS)->count();

        // Ringkasan hanya dari artikel yang pernah diproduksi — item null-batch (belum tertaut
        // produksi) sengaja dikecualikan agar total kartu & tabel kategori konsisten.
        $diproduksi = collect($rows)->filter(fn ($r) => $r['produced'] > 0);

        $byKategori = $diproduksi
            ->groupBy('kategori')
            ->map(fn ($g) => [
                'artikel' => $g->count(),
                'sisa' => (int) $g->sum('sisa'),
                'terjual' => (int) $g->sum('sold'),
                'diproduksi' => (int) $g->sum('produced'),
                'nilai' => (int) $g->sum('nilai'),
            ])->sortKeys();

        return view('stok.index', [
            'rows' => $rows,
            'ukurans' => Ukuran::cases(),
            'nullBatchCount' => $this->nullBatchQuery($request)->count(),
            'byKategori' => $byKategori,
            'totalSisa' => (int) $diproduksi->sum('sisa'),
            'totalProduced' => (int) $diproduksi->sum('produced'),
            'totalSold' => (int) $diproduksi->sum('sold'),
            'totalDiterima' => (int) $diproduksi->sum('diterima'),
            'totalVendor' => (int) $diproduksi->sum('di_vendor'),
            'totalJalan' => (int) $diproduksi->sum('di_jalan'),
            'totalBelumCair' => (int) $diproduksi->sum('belum_cair'),
            'totalReject' => (int) $diproduksi->sum('reject'),
            'totalRejectArsip' => (int) $diproduksi->sum('reject_arsip'),
            'jalanRows' => $diproduksi
                ->filter(fn ($r) => $r['di_vendor'] > 0 || $r['di_jalan'] > 0 || $r['belum_cair'] > 0 || $r['reject'] > 0)
                ->sortByDesc(fn ($r) => $r['di_vendor'] + $r['di_jalan'] + $r['belum_cair'] + $r['reject'])
                ->values(),
            'totalNilai' => (int) $diproduksi->sum('nilai'),
            'basisHarga' => $pakaiTm ? 'harga dari 420F' : 'harga ke 420F',
            'reorderCount' => $reorderCount,
            'tipisCount' => $tipisCount,
        ]);
    }

    /** Halaman rekonsiliasi: item pesanan (impor) yang belum tertaut batch produksi. */
    public function reconcile(Request $request)
    {
        $items = $this->nullBatchQuery($request)->with(['product.brand'])->get();

        $groups = $items->groupBy(fn ($s) => $s->product_id.'|'.$s->ukuran->value)->map(function ($g) {
            $first = $g->first();

            return [
                'product' => $first->product,
                'ukuran' => $first->ukuran,
                'qty' => (int) $g->sum('qty'),
                'available' => $this->stock->availableTotal($first->brand_id, $first->product_id, $first->ukuran->value),
            ];
        })->values();

        return view('stok.rekonsiliasi', ['groups' => $groups]);
    }

    /** Jalankan: alokasikan item null-batch ke batch tersedia (FIFO). */
    public function reconcileRun(Request $request)
    {
        $items = $this->nullBatchQuery($request)->get();
        $reconciled = 0;

        DB::transaction(function () use ($items, &$reconciled) {
            foreach ($items as $item) {
                $result = $this->stock->allocate($item->brand_id, $item->product_id, $item->ukuran->value, $item->qty);
                if (empty($result['alloc'])) {
                    continue; // belum ada batch — tetap null
                }
                foreach ($result['alloc'] as $a) {
                    $this->cloneItem($item, $a['batch']->id, $a['qty']);
                    $reconciled += $a['qty'];
                }
                if ($result['remaining'] > 0) {
                    $this->cloneItem($item, null, $result['remaining']); // sisa tetap tanpa batch
                }
                $item->delete();
            }
        });

        return redirect()->route('stok.reconcile')->with('success', "{$reconciled} unit berhasil ditautkan ke batch.");
    }

    private function cloneItem(Sale $item, ?int $batchId, int $qty): void
    {
        Sale::create([
            'order_id' => $item->order_id,
            'brand_id' => $item->brand_id,
            'product_id' => $item->product_id,
            'batch_id' => $batchId,
            'ukuran' => $item->ukuran->value,
            'qty' => $qty,
            'tanggal_terjual' => $item->tanggal_terjual,
            'marketplace' => $item->marketplace->value,
            'nomor_pesanan' => $item->nomor_pesanan,
            'harga_diferd' => $item->harga_diferd,
            'harga_tm420' => $item->harga_tm420,
            'kondisi_retur' => $item->kondisi_retur,
        ]);
    }

    private function nullBatchQuery(Request $request)
    {
        $query = Sale::whereNull('batch_id')->consuming();
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        return $query;
    }
}

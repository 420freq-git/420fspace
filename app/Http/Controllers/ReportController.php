<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\SizeTier;
use App\Models\Batch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // ===== Laporan Perputaran Stok (3 bulan terakhir) =====

    public function perputaran(Request $request)
    {
        return view('laporan.perputaran', $this->perputaranData($request));
    }

    public function perputaranPdf(Request $request)
    {
        return Pdf::loadView('laporan.pdf.perputaran', $this->perputaranData($request))
            ->setPaper('a4', 'portrait')->stream('laporan-perputaran-stok.pdf');
    }

    private function perputaranData(Request $request): array
    {
        $user = $request->user();
        $ownBrand = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;
        $sejak = now()->subMonths(3)->startOfDay();

        // Barang keluar 3 bulan terakhir (perputaran).
        $keluar3 = Sale::query()->consuming()
            ->where('tanggal_terjual', '>=', $sejak)
            ->when($ownBrand, fn ($q) => $q->where('brand_id', $ownBrand))
            ->groupBy('product_id')->selectRaw('product_id as pid, SUM(qty) as qty')->pluck('qty', 'pid');

        $produced = DB::table('po_size_items as psi')
            ->join('purchase_orders as po', 'psi.purchase_order_id', '=', 'po.id')
            ->join('products as pr', 'po.product_id', '=', 'pr.id')
            ->when($ownBrand, fn ($q) => $q->where('pr.brand_id', $ownBrand))
            ->groupBy('po.product_id')->selectRaw('po.product_id as pid, SUM(psi.qty) as qty')->pluck('qty', 'pid');

        $soldAll = Sale::query()->consuming()
            ->when($ownBrand, fn ($q) => $q->where('brand_id', $ownBrand))
            ->groupBy('product_id')->selectRaw('product_id as pid, SUM(qty) as qty')->pluck('qty', 'pid');

        $short = DB::table('pengiriman_items as pi')
            ->join('pengiriman as sj', 'pi.pengiriman_id', '=', 'sj.id')
            ->join('products as pr', 'pi.product_id', '=', 'pr.id')
            ->where('sj.status', 'diterima')->whereNotNull('pi.qty_diterima')
            ->when($ownBrand, fn ($q) => $q->where('pr.brand_id', $ownBrand))
            ->groupBy('pi.product_id')
            ->selectRaw('pi.product_id as pid, SUM(GREATEST(pi.qty - pi.qty_diterima, 0)) as s')->pluck('s', 'pid');

        $rows = Product::with(['brand', 'category'])
            ->when($ownBrand, fn ($q) => $q->where('brand_id', $ownBrand))
            ->get()
            ->map(function ($p) use ($keluar3, $produced, $soldAll, $short) {
                $prod = (int) ($produced[$p->id] ?? 0);
                $sisa = max(0, $prod - (int) ($soldAll[$p->id] ?? 0) - (int) ($short[$p->id] ?? 0));
                $t3 = (int) ($keluar3[$p->id] ?? 0);
                $perBulan = round($t3 / 3, 1);
                $bulanStok = $perBulan > 0 ? round($sisa / $perBulan, 1) : null;

                if ($t3 === 0) {
                    $status = $sisa > 0 ? ['label' => 'Stok mati', 'tone' => 'red'] : ['label' => 'Tidak bergerak', 'tone' => 'sand'];
                } elseif ($bulanStok !== null && $bulanStok < 2) {
                    $status = ['label' => 'Cepat', 'tone' => 'brand'];
                } else {
                    $status = ['label' => 'Lambat', 'tone' => 'amber'];
                }

                return [
                    'product' => $p, 'kategori' => $p->category->nama ?? 'Tanpa kategori',
                    'produced' => $prod, 'sisa' => $sisa, 'keluar3' => $t3,
                    'per_bulan' => $perBulan, 'bulan_stok' => $bulanStok, 'status' => $status,
                ];
            })
            ->filter(fn ($r) => $r['produced'] > 0)   // hanya artikel yang pernah diproduksi
            ->sortBy([['status.label', 'asc'], ['keluar3', 'desc']])->values();

        return [
            'rows' => $rows,
            'sejak' => $sejak,
            'stats' => [
                'cepat' => $rows->where('status.label', 'Cepat')->count(),
                'lambat' => $rows->where('status.label', 'Lambat')->count(),
                'mati' => $rows->where('status.label', 'Stok mati')->count(),
                'sisaMati' => (int) $rows->where('status.label', 'Stok mati')->sum('sisa'),
            ],
        ];
    }

    // ===== Laporan Produk Terjual per Bulan by Kategori =====

    public function terjualKategori(Request $request)
    {
        return view('laporan.terjual-kategori', $this->terjualKategoriData($request));
    }

    public function terjualKategoriPdf(Request $request)
    {
        return Pdf::loadView('laporan.pdf.terjual-kategori', $this->terjualKategoriData($request))
            ->setPaper('a4', 'landscape')->stream('laporan-terjual-kategori.pdf');
    }

    private function terjualKategoriData(Request $request): array
    {
        $user = $request->user();
        $ownBrand = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;
        $since = now()->startOfMonth()->subMonths(5);

        $raw = DB::table('sales as s')
            ->join('orders as o', 's.order_id', '=', 'o.id')
            ->join('products as p', 's.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('o.status', 'lunas')
            ->where('s.tanggal_terjual', '>=', $since)
            ->when($ownBrand, fn ($q) => $q->where('s.brand_id', $ownBrand))
            ->groupBy('kategori', 'ym')
            ->selectRaw("COALESCE(c.nama,'Tanpa kategori') as kategori, DATE_FORMAT(s.tanggal_terjual,'%Y-%m') as ym, "
                .'SUM(s.qty) as qty, SUM(s.qty * s.harga_tm420) as nilai_tm, SUM(s.qty * s.harga_diferd) as nilai_diferd')
            ->get();

        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        $rows = $raw->groupBy('kategori')->map(function ($g) use ($months) {
            $perBulan = $months->map(fn ($m) => (int) ($g->firstWhere('ym', $m->format('Y-m'))->qty ?? 0));

            return [
                'per_bulan' => $perBulan->all(),
                'qty' => (int) $g->sum('qty'),
                'nilai_tm' => (int) $g->sum('nilai_tm'),
                'nilai_diferd' => (int) $g->sum('nilai_diferd'),
            ];
        })->sortKeys();

        return [
            'rows' => $rows,
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M y'))->all(),
            'showTm' => in_array($user->role, [Role::Admin, Role::Tm420, Role::Voojah], true),
            'showDiferd' => in_array($user->role, [Role::Admin, Role::Diferd], true),
            'totals' => [
                'qty' => (int) $rows->sum('qty'),
                'nilai_tm' => (int) $rows->sum('nilai_tm'),
                'nilai_diferd' => (int) $rows->sum('nilai_diferd'),
                'per_bulan' => $months->map(fn ($m, $i) => (int) $rows->sum(fn ($r) => $r['per_bulan'][$i]))->all(),
            ],
        ];
    }

    // ===== Laporan Produksi & Performa Vendor =====

    public function produksi(Request $request)
    {
        return view('laporan.produksi', $this->produksiData($request));
    }

    public function produksiPdf(Request $request)
    {
        return Pdf::loadView('laporan.pdf.produksi', $this->produksiData($request))
            ->setPaper('a4', 'portrait')->stream('laporan-produksi.pdf');
    }

    private function produksiData(Request $request): array
    {
        $user = $request->user();
        $q = Batch::with(['brand', 'purchaseOrders'])->orderBy('tanggal_order', 'desc');
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $q->where('brand_id', $user->brand_id);
        }

        $rows = $q->get()->map(function ($b) {
            $pos = $b->purchaseOrders;
            $deadline = $b->deadline_produksi ?? $b->deadline;
            $progress = $pos->isEmpty() ? 0 : (int) round($pos->avg(fn ($p) => $p->tahap->progress()));
            $selesai = $progress >= 100;
            $sisa = $deadline ? (int) now()->startOfDay()->diffInDays($deadline, false) : null;

            return [
                'batch' => $b,
                'deadline' => $deadline,
                'progress' => $progress,
                'selesai' => $selesai,
                'sisa' => $sisa,
                'telat' => $sisa !== null && $sisa < 0 && ! $selesai,
                'aktif' => $b->status->value === 'aktif',
                'poCount' => $pos->count(),
                'qty' => (int) $pos->sum(fn ($p) => $p->total_qty),
            ];
        });

        $aktif = $rows->where('aktif', true);

        return [
            'rows' => $rows,
            'stats' => [
                'total' => $rows->count(),
                'aktif' => $aktif->count(),
                'selesai' => $rows->where('selesai', true)->count(),
                'telat' => $rows->where('telat', true)->count(),
                'avgProgress' => $aktif->isEmpty() ? 0 : (int) round($aktif->avg('progress')),
            ],
        ];
    }

    // ===== Laporan Keuangan Bulanan =====

    public function keuangan(Request $request)
    {
        return view('laporan.keuangan', $this->keuanganData($request));
    }

    public function keuanganPdf(Request $request)
    {
        return Pdf::loadView('laporan.pdf.keuangan', $this->keuanganData($request))
            ->setPaper('a4', 'portrait')->stream('laporan-keuangan.pdf');
    }

    private function keuanganData(Request $request): array
    {
        $user = $request->user();
        $brandId = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;
        $since = now()->startOfMonth()->subMonths(11);

        $raw = Sale::whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->where('tanggal_terjual', '>=', $since)
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->selectRaw("DATE_FORMAT(tanggal_terjual,'%Y-%m') as ym, SUM(qty) as unit, "
                .'SUM(qty * harga_tm420) as nilai_tm, SUM(qty * harga_diferd) as hak_diferd, '
                .'SUM(qty * (harga_tm420 - harga_diferd)) as fee')
            ->groupBy('ym')->get()->keyBy('ym');

        $rows = collect(range(11, 0))->map(function ($i) use ($raw) {
            $m = now()->startOfMonth()->subMonths($i);
            $r = $raw->get($m->format('Y-m'));

            return [
                'bulan' => $m,
                'unit' => (int) ($r->unit ?? 0),
                'nilai_tm' => (int) ($r->nilai_tm ?? 0),
                'hak_diferd' => (int) ($r->hak_diferd ?? 0),
                'fee' => (int) ($r->fee ?? 0),
            ];
        });

        return [
            'rows' => $rows,
            'showTm' => in_array($user->role, [Role::Admin, Role::Tm420, Role::Voojah], true),
            'showDiferd' => in_array($user->role, [Role::Admin, Role::Diferd], true),
            'showFee' => $user->isAdmin(),
            'totals' => [
                'unit' => $rows->sum('unit'),
                'nilai_tm' => $rows->sum('nilai_tm'),
                'hak_diferd' => $rows->sum('hak_diferd'),
                'fee' => $rows->sum('fee'),
            ],
        ];
    }

    // ===== Laporan Penjualan =====

    public function penjualan(Request $request)
    {
        return view('laporan.penjualan', $this->penjualanData($request));
    }

    public function penjualanPdf(Request $request)
    {
        $pdf = Pdf::loadView('laporan.pdf.penjualan', $this->penjualanData($request))->setPaper('a4', 'portrait');

        return $pdf->stream('laporan-penjualan.pdf');
    }

    private function penjualanData(Request $request): array
    {
        $dari = $request->filled('dari') ? Carbon::parse($request->input('dari')) : now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->input('sampai')) : now();

        $user = $request->user();
        // TM420 dikunci ke brand-nya; 420F & Diferd bebas filter brand.
        $ownBrand = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;
        $filterBrand = $ownBrand ?: $request->input('brand');

        $items = Sale::whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->whereBetween('tanggal_terjual', [$dari->copy()->startOfDay(), $sampai->copy()->endOfDay()])
            ->when($filterBrand, fn ($q) => $q->where('brand_id', $filterBrand))
            ->with(['product.brand'])->get();

        $byProduct = $items->groupBy('product_id')->map(fn ($g) => [
            'product' => $g->first()->product,
            'qty' => (int) $g->sum('qty'),
            'diferd' => (int) $g->sum(fn ($s) => $s->qty * $s->harga_diferd),
            'tm420' => (int) $g->sum(fn ($s) => $s->qty * ($s->harga_tm420 ?? 0)),
            'fee' => (int) $g->sum(fn ($s) => $s->qty * (($s->harga_tm420 ?? 0) - $s->harga_diferd)),
        ])->sortByDesc('qty')->values();

        $byMarketplace = $items->groupBy(fn ($s) => $s->marketplace->value)
            ->map(fn ($g) => ['label' => $g->first()->marketplace->label(), 'qty' => (int) $g->sum('qty')])
            ->sortByDesc('qty')->values();

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'byProduct' => $byProduct,
            'byMarketplace' => $byMarketplace,
            // Visibilitas nilai per role: TM tak pernah lihat harga Diferd/fee.
            'showTm' => in_array($user->role, [Role::Admin, Role::Tm420, Role::Voojah], true),
            'showDiferd' => in_array($user->role, [Role::Admin, Role::Diferd], true),
            'showFee' => $user->isAdmin(),
            'brands' => $ownBrand ? collect() : Brand::orderBy('nama')->get(),
            'filterBrand' => $filterBrand,
            'totalQty' => (int) $items->sum('qty'),
            'totalDiferd' => (int) $items->sum(fn ($s) => $s->qty * $s->harga_diferd),
            'totalTm420' => (int) $items->sum(fn ($s) => $s->qty * ($s->harga_tm420 ?? 0)),
            'totalFee' => (int) $items->sum(fn ($s) => $s->qty * (($s->harga_tm420 ?? 0) - $s->harga_diferd)),
            'jumlahArtikel' => $byProduct->count(),
        ];
    }

    // ===== Laporan Kerugian =====

    public function kerugian(Request $request)
    {
        return view('laporan.kerugian', $this->kerugianData($request));
    }

    public function kerugianPdf(Request $request)
    {
        $pdf = Pdf::loadView('laporan.pdf.kerugian', $this->kerugianData($request))->setPaper('a4', 'portrait');

        return $pdf->stream('laporan-kerugian.pdf');
    }

    private function kerugianData(Request $request): array
    {
        $q = Sale::with(['order', 'product', 'brand'])->where('kondisi_retur', 'rusak')->latest('id');

        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $q->where('brand_id', $user->brand_id);
        }

        $showTm = in_array($user->role, [Role::Admin, Role::Tm420, Role::Voojah], true);
        $showDiferd = in_array($user->role, [Role::Admin, Role::Diferd], true);

        // Tiap pihak hanya menerima datanya sendiri — bukan sekadar disembunyikan di tampilan.
        $items = $showTm ? $q->get() : collect();

        // Kerugian dipisah per pihak yang menanggung:
        //  - TM420 (brand): produk retur yang tidak bisa dijual lagi
        //  - Diferd (vendor): qty PO yang tidak sampai jadi stok jual — reject & kurang/cacat
        $produksi = $showDiferd ? $this->kerugianProduksi($request) : collect();

        return [
            'items' => $items,
            'totalQty' => (int) $items->sum('qty'),
            'totalNilai' => (int) $items->sum(fn ($s) => $s->qty * $s->harga_diferd),
            'produksi' => $produksi,
            'produksiQty' => (int) $produksi->sum('qty'),
            'produksiNilai' => (int) $produksi->sum('nilai'),
            'showTm' => $showTm,
            'showDiferd' => $showDiferd,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array> */
    private function kerugianProduksi(Request $request)
    {
        $stock = app(\App\Services\StockService::class);
        $user = $request->user();

        $poQuery = \App\Models\PurchaseOrder::with(['product.category.prices', 'sizeItems', 'batch'])
            ->where('tahap', \App\Enums\TahapProduksi::Terkirim->value);
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $poQuery->whereHas('batch', fn ($b) => $b->where('brand_id', $user->brand_id));
        }

        $baris = collect();

        // 1. Reject produksi — diproduksi tapi tidak pernah dikirim.
        foreach ($poQuery->get() as $po) {
            foreach ($po->sizeItems as $si) {
                $uk = $si->ukuran->value;
                $qty = $stock->rejectInBatch($po->batch_id, $po->product_id, $uk);
                if ($qty <= 0) {
                    continue;
                }
                $harga = (int) ($po->product->effectiveDiferd(SizeTier::forUkuran($uk)) ?? 0);

                // Alasan diambil dari surat jalan batch ini yang vendor tandai kirim kurang.
                $alasan = \App\Models\Pengiriman::where('batch_id', $po->batch_id)
                    ->whereNotNull('alasan_kurang_kirim')
                    ->whereHas('items', fn ($q) => $q->where('product_id', $po->product_id))
                    ->latest('id')->first()?->alasan_kurang_kirim;

                $baris->push([
                    'jenis' => $alasan?->label() ?? 'Reject produksi',
                    'batch' => $po->batch->nomor_batch,
                    'produk' => $po->product->nama_artikel,
                    'ukuran' => $uk,
                    'qty' => $qty,
                    'harga' => $harga,
                    'nilai' => $qty * $harga,
                    'keterangan' => 'Di PO tapi tidak pernah dikirim',
                ]);
            }
        }

        // 2. Kurang/cacat saat penerimaan.
        $itemQuery = \App\Models\PengirimanItem::with(['product.category.prices', 'pengiriman.batch'])
            ->whereNotNull('qty_diterima')
            ->whereHas('pengiriman', fn ($p) => $p->where('status', 'diterima'));
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $itemQuery->whereHas('pengiriman.batch', fn ($b) => $b->where('brand_id', $user->brand_id));
        }

        foreach ($itemQuery->get() as $it) {
            $kurang = max(0, $it->qty - $it->qty_diterima);
            if ($kurang <= 0) {
                continue;
            }
            $uk = $it->ukuran->value;
            $harga = (int) ($it->product->effectiveDiferd(SizeTier::forUkuran($uk)) ?? 0);
            $alasan = $it->pengiriman->alasan_kurang_terima;
            $baris->push([
                'jenis' => $alasan?->label() ?? 'Kurang / cacat',
                'batch' => $it->pengiriman->batch->nomor_batch,
                'produk' => $it->product->nama_artikel,
                'ukuran' => $uk,
                'qty' => $kurang,
                'harga' => $harga,
                'nilai' => $kurang * $harga,
                'keterangan' => 'Dikirim '.$it->qty.', diterima '.$it->qty_diterima.' ('.$it->pengiriman->nomor_sj.')'
                    .($it->pengiriman->catatan_selisih_terima ? ' · '.$it->pengiriman->catatan_selisih_terima : ''),
            ]);
        }

        return $baris->sortByDesc('nilai')->values();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Brand;
use App\Models\BrandLedger;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\VendorLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const ICON_MONEY = 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9';
    private const ICON_BATCH = 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 4.5A2.25 2.25 0 0013.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25';
    private const ICON_STOCK = 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z';
    private const ICON_ALERT = 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z';
    private const ICON_CLOCK = 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z';

    public function index(Request $request)
    {
        $user = $request->user();
        $brandId = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;

        $rupiah = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
        $pcs = fn ($n) => number_format((int) $n, 0, ',', '.').' pcs';

        $mepetHari = Setting::intVal('mepet_hari', 3);

        // ===== Produksi (batch aktif → progress + deadline + flag) =====
        $activeBatches = Batch::with(['brand', 'purchaseOrders.product'])
            ->where('status', 'aktif')
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->get();

        $batchRows = collect();
        $poRows = collect();

        foreach ($activeBatches as $b) {
            $pos = $b->purchaseOrders;
            $deadlineProd = $b->deadline_produksi ?? $b->deadline;
            $sisaHari = $deadlineProd ? (int) now()->startOfDay()->diffInDays($deadlineProd, false) : null;
            $progress = $pos->isEmpty() ? 0 : (int) round($pos->avg(fn ($p) => $p->tahap->progress()));
            $selesai = $progress >= 100;

            $batchRows->push([
                'batch' => $b,
                'deadlineProd' => $deadlineProd,
                'sisaHari' => $sisaHari,
                'progress' => $progress,
                'posTotal' => $pos->count(),
                'catatanCount' => $pos->filter(fn ($p) => filled($p->catatan_vendor))->count(),
                'telat' => $sisaHari !== null && $sisaHari < 0 && ! $selesai,
                'mepet' => $sisaHari !== null && $sisaHari >= 0 && $sisaHari <= $mepetHari && ! $selesai,
                'selesai' => $selesai,
            ]);

            foreach ($pos as $p) {
                $ready = $p->tahap->isReady();
                $hari = $p->hari_di_tahap;
                $poRows->push([
                    'batch' => $b,
                    'po' => $p,
                    'deadlineProd' => $deadlineProd,
                    'sisaHari' => $sisaHari,
                    'ready' => $ready,
                    'hari' => $hari,
                    'telat' => $sisaHari !== null && $sisaHari < 0 && ! $ready,
                    'mepet' => $sisaHari !== null && $sisaHari >= 0 && $sisaHari <= $mepetHari && ! $ready,
                    'mandek' => ! $ready && $hari !== null && $hari >= 5,
                ]);
            }
        }

        $batchRows = $batchRows->sortBy(fn ($r) => $r['sisaHari'] ?? PHP_INT_MAX)->values();
        $poRows = $poRows->sortBy(fn ($r) => $r['sisaHari'] ?? PHP_INT_MAX)->values();

        $batchAktif = $activeBatches->count();
        $batchPerhatian = $batchRows->filter(fn ($r) => $r['telat'] || $r['mepet'])->count();
        $poProduksi = $poRows->filter(fn ($r) => ! $r['ready'])->count();
        $poTelat = $poRows->filter(fn ($r) => $r['telat'] || $r['mepet'])->count();
        $poReady = $poRows->filter(fn ($r) => $r['ready'])->count();

        // ===== Stok per produk =====
        $producedByProduct = DB::table('po_size_items as psi')
            ->join('purchase_orders as po', 'psi.purchase_order_id', '=', 'po.id')
            ->join('products as pr', 'po.product_id', '=', 'pr.id')
            ->when($brandId, fn ($q) => $q->where('pr.brand_id', $brandId))
            ->groupBy('po.product_id')
            ->selectRaw('po.product_id as pid, SUM(psi.qty) as qty')
            ->pluck('qty', 'pid');

        $soldByProduct = Sale::query()->consuming()
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->groupBy('product_id')
            ->selectRaw('product_id as pid, SUM(qty) as qty')
            ->pluck('qty', 'pid');

        // Kekurangan/cacat penerimaan per produk (mengurangi stok, ditanggung vendor).
        $shortByProduct = DB::table('pengiriman_items as pi')
            ->join('pengiriman as sj', 'pi.pengiriman_id', '=', 'sj.id')
            ->join('products as pr', 'pi.product_id', '=', 'pr.id')
            ->where('sj.status', 'diterima')->whereNotNull('pi.qty_diterima')
            ->when($brandId, fn ($q) => $q->where('pr.brand_id', $brandId))
            ->groupBy('pi.product_id')
            ->selectRaw('pi.product_id as pid, SUM(GREATEST(pi.qty - pi.qty_diterima, 0)) as s')
            ->pluck('s', 'pid');

        $stokRows = Product::query()
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->with('brand')->get()
            ->map(function ($p) use ($producedByProduct, $soldByProduct, $shortByProduct) {
                $prod = (int) ($producedByProduct[$p->id] ?? 0);
                $sold = (int) ($soldByProduct[$p->id] ?? 0);
                $short = (int) ($shortByProduct[$p->id] ?? 0);

                return ['product' => $p, 'produced' => $prod, 'sisa' => max(0, $prod - $sold - $short)];
            });

        $totalSisa = (int) $stokRows->sum('sisa');
        $stokMenipis = $stokRows->filter(fn ($r) => $r['produced'] > 0 && $r['sisa'] <= 5)
            ->sortBy('sisa')->take(6)->values();

        // ===== Grafik penjualan (scoped per role) =====
        // 420F & Diferd lihat semua brand; TM420 hanya brand-nya. Diferd tanpa nilai rupiah.
        $chartBrands = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $brandId)
            ? Brand::where('id', $brandId)->get()
            : Brand::orderBy('nama')->get();

        $since = now()->startOfMonth()->subMonths(5);
        $rawChart = Sale::whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->where('tanggal_terjual', '>=', $since)
            ->whereIn('brand_id', $chartBrands->pluck('id'))
            ->selectRaw("brand_id, DATE_FORMAT(tanggal_terjual,'%Y-%m') as ym, SUM(qty) as qty, SUM(qty * harga_tm420) as nilai")
            ->groupBy('brand_id', 'ym')->get();

        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $palette = ['bg-brand-500', 'bg-blue-500', 'bg-amber-500', 'bg-violet-500'];

        $chartSeries = $chartBrands->values()->map(function ($b, $idx) use ($rawChart, $months, $palette) {
            $data = $months->map(fn ($m) => (int) ($rawChart->first(fn ($r) => $r->brand_id == $b->id && $r->ym === $m->format('Y-m'))->qty ?? 0));

            return [
                'brand' => $b->nama,
                'color' => $palette[$idx % count($palette)],
                'data' => $data->all(),
                'total' => (int) $data->sum(),
                'nilai' => (int) $rawChart->where('brand_id', $b->id)->sum('nilai'),
            ];
        });

        // ===== Pesanan / monitoring (brand & admin) =====
        $perluDicek = Order::query()
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->whereIn('status', ['dipesan', 'dikirim'])
            ->whereDate('tanggal_pesanan', '<=', now()->subDays(Setting::intVal('monitor_hari', 12)))
            ->count();

        $returPending = Order::query()
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->where('status', 'retur')
            ->whereNull('tgl_retur_diterima')
            ->count();

        // ===== Uang (per role) =====
        $sold = fn () => Sale::when($brandId, fn ($q) => $q->where('brand_id', $brandId))->sold();

        if (in_array($user->role, [Role::Tm420, Role::Voojah], true)) {
            $tagihan = (int) $sold()->whereNotNull('harga_tm420')->sum(DB::raw('qty * harga_tm420'));
            // Kecualikan transfer buy-out (bukan pembayaran penjualan) dari sisa tagihan penjualan.
            $ditransfer = (int) BrandLedger::where('brand_id', $brandId)
                ->where('keterangan', 'not like', 'Buy-out sisa stok%')->sum('jumlah');
            $sisaTagihan = $tagihan - $ditransfer;

            $money = ['tagihan' => $tagihan, 'ditransfer' => $ditransfer, 'sisa' => $sisaTagihan];
            $cards = [
                $this->card('Sisa tagihan ke 420F', $rupiah($sisaTagihan), 'dari '.$rupiah($tagihan).' total penjualan', self::ICON_MONEY, $sisaTagihan > 0 ? 'warn' : 'brand'),
                $this->card('Perlu perhatian', (string) $batchPerhatian, 'batch telat/mepet deadline', self::ICON_ALERT, $batchPerhatian > 0 ? 'danger' : null),
                $this->card('Batch aktif', (string) $batchAktif, 'produksi berjalan', self::ICON_BATCH),
                $this->card('Sisa stok siap jual', $pcs($totalSisa), 'stok brand tersisa', self::ICON_STOCK),
            ];
        } elseif ($user->role === Role::Diferd) {
            // Konsinyasi saja — batch cash sudah lunas di muka, tak menambah hak berjalan.
            $hak = (int) Sale::sold()->consignment()->sum(DB::raw('qty * harga_diferd'));
            // whereNull penarikan_id: baris beku hasil penarikan sudah terwakili totalnya sendiri.
            $terbayar = (int) VendorLedger::where('tipe', 'pembayaran')->whereNull('penarikan_id')->sum('jumlah')
                + (int) \App\Models\Penarikan::where('status', 'disetujui')->sum('jumlah');
            $sisaTerima = $hak - $terbayar;
            $modal = app(\App\Services\SettlementService::class)->depositMengendap();

            $money = ['hak' => $hak, 'dibayar' => $terbayar, 'sisa' => $sisaTerima, 'modal' => $modal];
            $cards = [
                $this->card('Akan diterima', $rupiah(max(0, $sisaTerima)), $sisaTerima > 0 ? 'sisa hak dari '.$rupiah($hak) : 'hak sudah lunas', self::ICON_MONEY, $sisaTerima > 0 ? 'warn' : 'brand'),
                $this->card('PO telat/mepet', (string) $poTelat, 'perlu dikebut', self::ICON_ALERT, $poTelat > 0 ? 'danger' : null),
                $this->card('PO dalam produksi', (string) $poProduksi, 'belum siap kirim', self::ICON_CLOCK),
                $this->card('Batch aktif', (string) $batchAktif, 'sedang dikerjakan', self::ICON_BATCH),
            ];
        } else {
            $lunas = fn () => Sale::consignment()->whereHas('order', fn ($o) => $o->where('status', 'lunas'))->whereNotNull('harga_tm420');
            $saleTagihan = (int) $lunas()->sum(DB::raw('qty * harga_tm420'));
            $cashTm = (int) BrandLedger::where('keterangan', 'like', 'Cash batch%')->sum('jumlah');
            $cashDiferd = (int) VendorLedger::where('tipe', 'cash')->sum('jumlah');
            // Buy-out kini alur tagihan: hak Diferd (diferd) + invoice ke TM (tm420), 420F ambil margin.
            $buyoutDiferd = (int) VendorLedger::where('tipe', 'buyout')->sum('jumlah');
            $buyoutInvoice = (int) \App\Models\Invoice::where('jumlah_manual', '>', 0)->sum('jumlah_manual');
            $tagihanTM = $saleTagihan + $buyoutInvoice;
            $fee = (int) $lunas()->sum(DB::raw('qty * (harga_tm420 - harga_diferd)')) + ($cashTm - $cashDiferd) + ($buyoutInvoice - $buyoutDiferd);
            $penarikanCair = (int) \App\Models\Penarikan::where('status', 'disetujui')->sum('jumlah');
            $kewajibanDiferd = (int) Sale::sold()->consignment()->sum(DB::raw('qty * harga_diferd')) + $buyoutDiferd;
            // whereNull penarikan_id: baris beku hasil penarikan sudah terwakili $penarikanCair.
            $pembayaranDiferd = (int) VendorLedger::where('tipe', 'pembayaran')->whereNull('penarikan_id')->sum('jumlah') + $penarikanCair;
            // Modal (deposit) yang masih mengendap di vendor — global, di luar kas 420F.
            $modalDiferd = app(\App\Services\SettlementService::class)->depositMengendap();
            // Buy-out belum dibayar (hak) — ditutup lewat pembayaran/penarikan, sudah di $pembayaranDiferd.
            $dibayarDiferd = $pembayaranDiferd + $cashDiferd;
            $ditransferTM = (int) BrandLedger::sum('jumlah');
            $posisiKas = $ditransferTM - $dibayarDiferd;   // semua transfer − semua bayar (incl cash) → margin cash tercermin
            // Sisa tagihan penjualan pakai transfer penjualan konsinyasi saja (buy-out lama & cash dipisah).
            $ditransferPenjualan = $ditransferTM - (int) BrandLedger::where('keterangan', 'like', 'Buy-out sisa stok%')
                ->orWhere('keterangan', 'like', 'Cash batch%')->sum('jumlah');

            $money = [
                'posisiKas' => $posisiKas, 'fee' => $fee,
                'sisaDiferd' => $kewajibanDiferd - $pembayaranDiferd,
                'sisaTM' => $tagihanTM - $ditransferPenjualan,
                'modal' => $modalDiferd,
            ];
            $cards = [
                $this->card('Posisi kas 420F', $rupiah($posisiKas), 'uang TM masuk − uang keluar ke Diferd', self::ICON_MONEY, $posisiKas >= 0 ? 'brand' : 'danger'),
                $this->card('Fee 420F (margin)', $rupiah($fee), 'markup penjualan lunas', self::ICON_MONEY),
                $this->card('Sisa bayar ke Diferd', $rupiah($money['sisaDiferd']), 'kewajiban belum dibayar', self::ICON_MONEY, $money['sisaDiferd'] > 0 ? 'warn' : null),
                $this->card('Batch telat/mepet', (string) $batchPerhatian, 'butuh perhatian produksi', self::ICON_ALERT, $batchPerhatian > 0 ? 'danger' : null),
            ];
        }

        return view('dashboard', [
            'role' => $user->role,
            'cards' => $cards,
            'money' => $money,
            'chartSeries' => $chartSeries,
            'chartLabels' => $months->map(fn ($m) => $m->translatedFormat('M'))->all(),
            'chartMax' => max(1, $chartSeries->flatMap(fn ($s) => $s['data'])->max() ?? 0),
            'chartShowNilai' => $user->role !== Role::Diferd,
            'batchRows' => $batchRows->take(6),
            'vendorQueue' => $poRows->filter(fn ($r) => ! $r['ready'])->take(8)->values(),
            'stokMenipis' => $stokMenipis,
            'perluDicek' => $perluDicek,
            'returPending' => $returPending,
            'poReady' => $poReady,
            'rupiah' => $rupiah,
        ]);
    }

    private function card(string $label, string $value, string $note, string $icon, ?string $tone = null): array
    {
        return compact('label', 'value', 'note', 'icon', 'tone');
    }
}

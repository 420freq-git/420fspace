<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Penarikan;
use App\Models\Pengiriman;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /** @return array<int, array{label:string, count:int, url:string, tone:string}> */
    public function for(User $user): array
    {
        // Brand user (TM420 & VOOJAH) di-scope ke brand-nya; admin/vendor lihat semua.
        $brandId = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;
        $items = [];

        // ---- Penjualan (admin & brand) ----
        if (in_array($user->role, [Role::Admin, Role::Tm420, Role::Voojah], true)) {
            $monitorHari = Setting::intVal('monitor_hari', 12);
            $perluDicek = Order::when($brandId, fn ($q) => $q->where('brand_id', $brandId))
                ->whereIn('status', ['dipesan', 'dikirim'])
                ->whereDate('tanggal_pesanan', '<=', now()->subDays($monitorHari))->count();
            $this->push($items, 'Pesanan perlu dicek', $perluDicek, route('monitoring.cek'), 'warn');

            $retur = Order::when($brandId, fn ($q) => $q->where('brand_id', $brandId))
                ->where('status', 'retur')->whereNull('tgl_retur_diterima')->count();
            $this->push($items, 'Barang kembali menunggu', $retur, route('monitoring.kembali'), 'danger');

            $invoice = Invoice::when($brandId, fn ($q) => $q->where('brand_id', $brandId))
                ->where('status', 'belum_bayar')->count();
            $this->push($items, 'Invoice belum dibayar', $invoice, route('invoices.index'), 'warn');

            $this->push($items, 'Stok kritis', $this->stokKritis($brandId), route('stok.index'), 'danger');
        }

        // ---- Produksi mepet/telat (semua role) ----
        $this->push($items, 'Produksi mepet / telat', $this->produksiPerhatian($brandId), route('monitoring-produksi.index'), 'danger');

        // ---- Keuangan & logistik (admin & Diferd) ----
        if (in_array($user->role, [Role::Admin, Role::Diferd], true)) {
            $penarikan = Penarikan::where('status', 'diajukan')->count();
            $this->push($items, $user->isAdmin() ? 'Penarikan menunggu persetujuan' : 'Penarikan menunggu diproses',
                $penarikan, route('penarikan.index'), 'warn');

            $sj = Pengiriman::where('status', 'dikirim')
                ->when($brandId, fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('brand_id', $brandId)))
                ->count();
            $this->push($items, 'Surat jalan belum diterima', $sj, route('pengiriman.index'), 'info');
        }

        return $items;
    }

    private function push(array &$items, string $label, int $count, string $url, string $tone): void
    {
        if ($count > 0) {
            $items[] = compact('label', 'count', 'url', 'tone');
        }
    }

    private function stokKritis(?int $brandId): int
    {
        $produced = DB::table('po_size_items as psi')
            ->join('purchase_orders as po', 'psi.purchase_order_id', '=', 'po.id')
            ->join('products as pr', 'po.product_id', '=', 'pr.id')
            ->when($brandId, fn ($q) => $q->where('pr.brand_id', $brandId))
            ->groupBy('po.product_id')->selectRaw('po.product_id as pid, SUM(psi.qty) as qty')->pluck('qty', 'pid');

        $sold = Sale::query()->consuming()
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->groupBy('product_id')->selectRaw('product_id as pid, SUM(qty) as qty')->pluck('qty', 'pid');

        return Product::query()->when($brandId, fn ($q) => $q->where('brand_id', $brandId))->pluck('id')
            ->filter(function ($id) use ($produced, $sold) {
                $p = (int) ($produced[$id] ?? 0);
                return $p > 0 && ($p - (int) ($sold[$id] ?? 0)) <= 5;
            })->count();
    }

    private function produksiPerhatian(?int $brandId): int
    {
        $mepetHari = Setting::intVal('mepet_hari', 3);

        return Batch::with('purchaseOrders')->where('status', 'aktif')
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->get()
            ->filter(function ($b) use ($mepetHari) {
                $deadline = $b->deadline_produksi ?? $b->deadline;
                if (! $deadline) {
                    return false;
                }
                $sisa = (int) now()->startOfDay()->diffInDays($deadline, false);
                $progress = $b->purchaseOrders->isEmpty() ? 0 : $b->purchaseOrders->avg(fn ($p) => $p->tahap->progress());

                return $progress < 100 && $sisa <= $mepetHari;
            })->count();
    }
}

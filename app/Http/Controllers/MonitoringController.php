<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    private const HARI_MASUK_MONITORING = 12; // belum cair > 12 hari
    private const HARI_CEK_ULANG = 2;          // notif cek muncul lagi setelah 2 hari

    /** Pesanan belum cair yang perlu dicek di Seller Center. */
    public function perluDicek(Request $request)
    {
        $batasMasuk = now()->subDays(\App\Models\Setting::intVal('monitor_hari', self::HARI_MASUK_MONITORING))->endOfDay();

        $query = Order::with(['brand', 'items.product'])
            ->whereIn('status', ['dipesan', 'dikirim'])
            ->whereDate('tanggal_pesanan', '<=', $batasMasuk)
            ->orderBy('tanggal_pesanan'); // tertua dulu

        $this->scope($query, $request);

        if ($request->filled('channel')) {
            $query->where('marketplace', $request->input('channel'));
        }

        $orders = $query->get();
        $batasCek = now()->subDays(\App\Models\Setting::intVal('cek_ulang_hari', self::HARI_CEK_ULANG))->endOfDay();

        $perluSekarang = $orders->filter(fn ($o) => $this->perluDicekSekarang($o, $batasCek));

        return view('orders.monitoring-cek', [
            'orders' => $orders,
            'batasCek' => $batasCek,
            'stats' => [
                'total' => $orders->count(),
                'perlu_sekarang' => $perluSekarang->count(),
                'belum_pernah' => $orders->where('jumlah_cek', 0)->count(),
                'tiktok' => $orders->where('marketplace', \App\Enums\Marketplace::Tiktokshop)->count(),
                'shopee' => $orders->where('marketplace', \App\Enums\Marketplace::Shopee)->count(),
            ],
        ]);
    }

    /** Tandai sudah dicek: catat keterangan, tambah hitungan, reset jadwal cek ulang. */
    public function sudahDicek(Request $request, Order $order)
    {
        $data = $request->validate(['keterangan' => ['nullable', 'string', 'max:255']]);

        $order->update([
            'jumlah_cek' => $order->jumlah_cek + 1,
            'tgl_cek_terakhir' => now(),
            'keterangan' => $data['keterangan'] ?: $order->keterangan,
        ]);

        return back()->with('success', "Pesanan {$order->nomor_pesanan} ditandai sudah dicek.");
    }

    public function perluDicekSekarang(Order $order, $batasCek): bool
    {
        return $order->jumlah_cek === 0
            || ($order->tgl_cek_terakhir && $order->tgl_cek_terakhir->lte($batasCek));
    }

    /** Paket ditolak pembeli → status retur (barang dalam perjalanan balik). */
    public function tolakRetur(Request $request, Order $order)
    {
        $this->ensureOwn($request, $order);
        $data = $request->validate(['alasan_batal' => ['nullable', 'string', 'max:255']]);

        $order->update([
            'status' => 'retur',
            'tgl_retur' => now(),
            'alasan_batal' => $data['alasan_batal'] ?: 'Ditolak pembeli',
        ]);

        return back()->with('success', "Pesanan {$order->nomor_pesanan} ditandai retur (menunggu barang kembali).");
    }

    /** Monitoring barang retur yang belum diterima kembali. */
    public function barangKembali(Request $request)
    {
        $query = Order::with(['brand', 'items.product'])
            ->where('status', 'retur')->whereNull('tgl_retur_diterima')
            ->orderBy('tgl_retur');
        $this->scope($query, $request);

        $orders = $query->get();

        return view('orders.barang-kembali', [
            'orders' => $orders,
            'total' => $orders->count(),
        ]);
    }

    /** Barang retur diterima → validasi kondisi: layak (masuk stok) / rusak (kerugian). */
    public function terimaRetur(Request $request, Order $order)
    {
        $this->ensureOwn($request, $order);
        $data = $request->validate([
            'kondisi' => ['required', 'in:layak,rusak'],
            // Alasan wajib bila dinyatakan rusak/hilang — jadi dasar catatan kerugian.
            'alasan_rusak' => ['nullable', 'required_if:kondisi,rusak', 'string', 'max:255'],
        ], [
            'alasan_rusak.required_if' => 'Alasan wajib diisi bila barang dinyatakan rusak/hilang.',
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->items()->update(['kondisi_retur' => $data['kondisi']]);
            $order->update([
                'status' => 'batal',
                'tgl_retur_diterima' => now(),
                'alasan_rusak' => $data['kondisi'] === 'rusak' ? $data['alasan_rusak'] : null,
            ]);
        });

        $msg = $data['kondisi'] === 'layak'
            ? "Barang retur {$order->nomor_pesanan} diterima — layak jual, stok dikembalikan."
            : "Barang retur {$order->nomor_pesanan} diterima — rusak, dicatat sebagai kerugian (brand tetap bayar produksi).";

        return back()->with('success', $msg);
    }

    private function ensureOwn(Request $request, Order $order): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id && $order->brand_id !== $user->brand_id) {
            abort(403);
        }
    }

    private function scope($query, Request $request): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }
    }
}

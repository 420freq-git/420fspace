<?php

namespace App\Http\Controllers;

use App\Models\BrandLedger;
use App\Models\Penarikan;
use App\Models\Sale;
use App\Models\VendorLedger;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;

/**
 * Buku kas 420F — mutasi saldo berjalan.
 *
 * Berbeda dari Cashflow (agregat ringkasan), halaman ini menyusun SETIAP pergerakan uang 420F
 * secara kronologis dengan saldo berjalan tiap baris, sehingga terlihat kapan kas naik/turun.
 * Saldo akhir = posisi kas 420F (uang masuk dari brand − uang keluar ke Diferd).
 *
 * Deposit modal TIDAK masuk kas 420F (TM menalangi langsung ke Diferd) — ditampilkan terpisah.
 */
class SaldoController extends Controller
{
    public function __construct(private SettlementService $settlement) {}

    public function index()
    {
        $moves = collect();

        // MASUK — transfer dari brand (TM420 / VOOJAH) ke 420F.
        // Jumlah negatif = refund reject cash yang 420F kembalikan ke TM → jadi uang KELUAR.
        foreach (BrandLedger::with('brand')->get() as $b) {
            $refund = (int) $b->jumlah < 0;
            $moves->push([
                'tanggal' => $b->tanggal,
                'urut' => $b->created_at,
                'arah' => $refund ? 'keluar' : 'masuk',
                'jumlah' => abs((int) $b->jumlah),
                'label' => $refund
                    ? 'Refund reject ke '.($b->brand->nama ?? 'brand')
                    : 'Transfer dari '.($b->brand->nama ?? 'brand'),
                'ket' => $b->keterangan,
            ]);
        }

        // KELUAR — penarikan Diferd yang cair (kas nyata keluar).
        foreach (Penarikan::where('status', 'disetujui')->get() as $p) {
            $moves->push([
                'tanggal' => $p->tanggal_cair ?? $p->tanggal_ajuan,
                'urut' => $p->tanggal_cair ?? $p->created_at,
                'arah' => 'keluar',
                'jumlah' => (int) $p->jumlah,
                'label' => 'Penarikan Diferd #'.$p->id,
                'ket' => $p->catatan,
            ]);
        }

        // KELUAR — pembayaran manual ke Diferd (di luar penarikan; baris beku penarikan dikecualikan).
        foreach (VendorLedger::where('tipe', 'pembayaran')->whereNull('penarikan_id')->get() as $v) {
            $moves->push([
                'tanggal' => $v->tanggal,
                'urut' => $v->created_at,
                'arah' => 'keluar',
                'jumlah' => (int) $v->jumlah,
                'label' => 'Pembayaran Diferd',
                'ket' => $v->keterangan,
            ]);
        }

        // Catatan: buy-out TIDAK lagi jadi uang keluar seketika. Kini ia menambah HAK Diferd
        // (ditutup via pembayaran/penarikan di atas) & menerbitkan invoice ke TM (uang masuk lewat
        // 'Pembayaran invoice ...' di blok MASUK). Jadi tak ada baris keluar khusus buy-out.

        // KELUAR — pembayaran cash batch di muka (420F → Diferd).
        // Jumlah negatif = refund reject yang Diferd kembalikan ke 420F → jadi uang MASUK.
        foreach (VendorLedger::where('tipe', 'cash')->get() as $v) {
            $refund = (int) $v->jumlah < 0;
            $moves->push([
                'tanggal' => $v->tanggal,
                'urut' => $v->created_at,
                'arah' => $refund ? 'masuk' : 'keluar',
                'jumlah' => abs((int) $v->jumlah),
                'label' => $refund ? 'Refund reject dari Diferd' : 'Bayar cash Diferd (di muka)',
                'ket' => $v->keterangan,
            ]);
        }

        // Urutkan kronologis, lalu hitung saldo berjalan.
        $moves = $moves->sortBy([
            fn ($a, $b) => $a['tanggal'] <=> $b['tanggal'],
            fn ($a, $b) => ($a['urut'] ?? $a['tanggal']) <=> ($b['urut'] ?? $b['tanggal']),
        ])->values();

        $saldo = 0;
        $rows = $moves->map(function ($m) use (&$saldo) {
            $saldo += $m['arah'] === 'masuk' ? $m['jumlah'] : -$m['jumlah'];
            $m['saldo'] = $saldo;

            return $m;
        })->reverse()->values();   // tampilkan terbaru di atas

        $totalMasuk = (int) $moves->where('arah', 'masuk')->sum('jumlah');
        $totalKeluar = (int) $moves->where('arah', 'keluar')->sum('jumlah');

        // Fee 420F = markup dari pesanan lunas (uang yang jadi milik 420F, bukan titipan).
        $fee = (int) Sale::whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->whereNotNull('harga_tm420')->sum(DB::raw('qty * (harga_tm420 - harga_diferd)'));

        return view('saldo.index', [
            'rows' => $rows,
            'saldo' => $saldo,
            'totalMasuk' => $totalMasuk,
            'totalKeluar' => $totalKeluar,
            'fee' => $fee,
            'depositMengendap' => $this->settlement->depositMengendap(),
        ]);
    }
}

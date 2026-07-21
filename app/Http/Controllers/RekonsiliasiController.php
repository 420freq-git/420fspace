<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\BrandLedger;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rekonsiliasi pembayaran mingguan TM → 420F.
 *
 * TM biasanya membayar tiap minggu. Halaman ini membandingkan, per minggu: tagihan yang CAIR
 * (pesanan jadi lunas minggu itu, × harga_tm420) vs transfer yang DITERIMA, lalu tunggakan
 * kumulatif — supaya pembayaran yang meleset langsung kelihatan.
 */
class RekonsiliasiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $brandId = in_array($user->role, [Role::Tm420, Role::Voojah], true) ? $user->brand_id : null;

        // Tagihan yang cair per minggu (dari pesanan lunas, berdasar tgl_cair).
        $orders = Order::with('items')->where('status', 'lunas')->whereNotNull('tgl_cair')
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))->get();

        $tagihanMinggu = [];
        foreach ($orders as $o) {
            $key = $this->pekan($o->tgl_cair);
            $tagihanMinggu[$key] = ($tagihanMinggu[$key] ?? 0)
                + (int) $o->items->sum(fn ($s) => $s->qty * ($s->harga_tm420 ?? 0));
        }

        // Transfer diterima per minggu (kecuali buy-out, yang bukan pembayaran penjualan).
        $transfers = BrandLedger::where('keterangan', 'not like', 'Buy-out sisa stok%')
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))->get();

        $transferMinggu = [];
        foreach ($transfers as $t) {
            $key = $this->pekan($t->tanggal);
            $transferMinggu[$key] = ($transferMinggu[$key] ?? 0) + (int) $t->jumlah;
        }

        // Gabung semua pekan, urut, hitung tunggakan kumulatif.
        $pekanSemua = collect(array_keys($tagihanMinggu + $transferMinggu))->unique()->sort()->values();

        $kumTagihan = 0; $kumTransfer = 0; $rows = [];
        foreach ($pekanSemua as $key) {
            $tagih = $tagihanMinggu[$key] ?? 0;
            $terima = $transferMinggu[$key] ?? 0;
            $kumTagihan += $tagih; $kumTransfer += $terima;
            [$mulai, $selesai] = $this->rentang($key);
            $rows[] = [
                'label' => $mulai->format('d/m').'–'.$selesai->format('d/m/Y'),
                'tagihan' => $tagih,
                'terima' => $terima,
                'selisih' => $terima - $tagih,
                'tunggakan_kumulatif' => $kumTagihan - $kumTransfer,
            ];
        }
        $rows = array_reverse($rows);   // terbaru di atas

        return view('rekonsiliasi.index', [
            'rows' => $rows,
            'totalTagihan' => $kumTagihan,
            'totalTransfer' => $kumTransfer,
            'tunggakan' => $kumTagihan - $kumTransfer,
        ]);
    }

    private function pekan(Carbon $t): string
    {
        return $t->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(string $key): array
    {
        $mulai = Carbon::parse($key);

        return [$mulai, $mulai->copy()->endOfWeek(Carbon::SUNDAY)];
    }
}

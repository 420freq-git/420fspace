<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Ukuran;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;

/**
 * Rekomendasi produksi ulang — daftar produk laris yang stoknya menipis, jadi bahan PO batch
 * berikutnya. Menutup lingkaran kembali ke pembuatan batch: sinyal "reorder" yang tadinya
 * tersembunyi sebagai flag di tabel Stok kini jadi daftar yang bisa langsung ditindak.
 */
class RekomendasiController extends Controller
{
    private const TIPIS = 15;          // sisa ≤ ini = perlu perhatian
    private const LARIS_PERSEN = 60;   // sell-through ≥ ini = laris

    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Product::with(['brand', 'category'])->where('aktif', true)->orderBy('nama_artikel');
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        $rows = [];
        foreach ($query->get() as $p) {
            $prod = $sold = $terima = $buyout = 0;
            foreach (Ukuran::cases() as $u) {
                $prod += $this->stock->producedTotal($p->id, $u->value);
                $sold += $this->stock->soldTotal($p->id, $u->value);
                $terima += $this->stock->receivedTotal($p->id, $u->value);
                $buyout += $this->stock->boughtOutTotal($p->id, $u->value);
            }
            if ($prod === 0) {
                continue;   // belum pernah diproduksi → bukan "produksi ULANG"
            }
            $sisa = $terima - $sold - $buyout;
            $sellThrough = $terima > 0 ? (int) round($sold / $terima * 100) : 0;

            // Kandidat: laris & stok menipis/habis.
            if ($sisa > self::TIPIS || $sellThrough < self::LARIS_PERSEN) {
                continue;
            }

            $rows[] = [
                'product' => $p,
                'kategori' => $p->category->nama ?? '—',
                'diproduksi' => $prod,
                'terjual' => $sold,
                'sisa' => $sisa,
                'sell_through' => $sellThrough,
                // Saran qty: tutup penjualan sejauh ini, minimal di atas ambang tipis.
                'saran_qty' => max($sold, self::TIPIS + 1),
                'habis' => $sisa <= 0,
            ];
        }

        // Paling mendesak dulu: stok paling tipis, lalu paling laris.
        usort($rows, fn ($a, $b) => [$a['sisa'], -$a['sell_through']] <=> [$b['sisa'], -$b['sell_through']]);

        return view('rekomendasi.index', [
            'rows' => $rows,
            'totalSaran' => array_sum(array_column($rows, 'saran_qty')),
            'habisCount' => count(array_filter($rows, fn ($r) => $r['habis'])),
            'bolehBuatBatch' => $user->isAdmin() || in_array($user->role, [Role::Tm420, Role::Voojah], true),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Ukuran;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rapor per artikel — kinerja tiap produk di satu tabel untuk kurasi katalog:
 * diproduksi, terjual, sell-through, sisa stok, cacat, dan nilai/fee. Menjawab
 * "artikel mana yang layak dilanjut, mana yang di-drop".
 */
class RaporProdukController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $showFee = $user->isAdmin();

        $query = Product::with(['brand', 'category'])->where('aktif', true)->orderBy('nama_artikel');
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        // Omzet & fee per produk (dari penjualan lunas), sekali query.
        $uang = Sale::whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->whereNotNull('harga_tm420')
            ->selectRaw('product_id, SUM(qty * harga_tm420) AS omzet, SUM(qty * (harga_tm420 - harga_diferd)) AS fee')
            ->groupBy('product_id')->get()->keyBy('product_id');

        $rows = [];
        foreach ($query->get() as $p) {
            $prod = $sold = $terima = $buyout = $reject = $kurang = 0;
            foreach (Ukuran::cases() as $u) {
                $prod += $this->stock->producedTotal($p->id, $u->value);
                $sold += $this->stock->soldTotal($p->id, $u->value);
                $terima += $this->stock->receivedTotal($p->id, $u->value);
                $buyout += $this->stock->boughtOutTotal($p->id, $u->value);
                $reject += $this->stock->rejectTotal($p->id, $u->value);
                $kurang += $this->stock->shortfallTotal($p->id, $u->value);
            }
            $sisa = $terima - $sold - $buyout;
            $cacat = $reject + $kurang;

            $rows[] = [
                'product' => $p,
                'kategori' => $p->category->nama ?? '—',
                'diproduksi' => $prod,
                'terjual' => $sold,
                'sisa' => $sisa,
                'sell_through' => $terima > 0 ? (int) round($sold / $terima * 100) : 0,
                'cacat' => $cacat,
                'cacat_persen' => $prod > 0 ? round($cacat / $prod * 100, 1) : 0,
                'omzet' => (int) ($uang[$p->id]->omzet ?? 0),
                'fee' => (int) ($uang[$p->id]->fee ?? 0),
                'pernah_produksi' => $prod > 0,
            ];
        }

        // Default: paling laku (terjual) di atas.
        usort($rows, fn ($a, $b) => $b['terjual'] <=> $a['terjual']);

        return view('rapor-produk.index', [
            'rows' => $rows,
            'showFee' => $showFee,
            'totalOmzet' => array_sum(array_column($rows, 'omzet')),
            'totalFee' => array_sum(array_column($rows, 'fee')),
            'aktifCount' => count(array_filter($rows, fn ($r) => $r['pernah_produksi'])),
        ]);
    }
}

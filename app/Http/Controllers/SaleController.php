<?php

namespace App\Http\Controllers;

use App\Enums\Marketplace;
use App\Enums\Role;
use App\Enums\SizeTier;
use App\Enums\Ukuran;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaleController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $query = Sale::with(['product', 'brand', 'batch'])->latest('tanggal_terjual')->latest('id');
        $this->scope($query, $request);

        if ($request->filled('marketplace')) {
            $query->where('marketplace', $request->input('marketplace'));
        }
        if ($request->filled('dari')) {
            $query->whereDate('tanggal_terjual', '>=', $request->date('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal_terjual', '<=', $request->date('sampai'));
        }

        $sales = $query->get();

        return view('sales.index', [
            'sales' => $sales,
            'totalQty' => $sales->sum('qty'),
            'totalFee' => $sales->sum(fn ($s) => $s->fee_420f ?? 0),
            'totalDiferd' => $sales->sum('nilai_diferd'),
            'marketplaces' => Marketplace::cases(),
        ]);
    }

    public function create(Request $request)
    {
        $products = $this->scopedProducts($request);

        // Peta stok tersedia per produk+ukuran
        $stockMap = [];
        foreach ($products as $p) {
            $row = [];
            foreach (Ukuran::cases() as $u) {
                $row[$u->value] = $this->stock->availableTotal($p->brand_id, $p->id, $u->value);
            }
            $stockMap[$p->id] = $row;
        }

        return view('sales.create', [
            'sale' => new Sale(['tanggal_terjual' => now()]),
            'products' => $products,
            'stockMap' => $stockMap,
            'marketplaces' => Marketplace::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', $this->productRule($request)],
            'ukuran' => ['required', new Enum(Ukuran::class)],
            'qty' => ['required', 'integer', 'min:1'],
            'tanggal_terjual' => ['required', 'date'],
            'marketplace' => ['required', new Enum(Marketplace::class)],
            'nomor_pesanan' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $tier = SizeTier::forUkuran($data['ukuran']);
        $diferd = $product->effectiveDiferd($tier) ?? 0;
        $tm420 = $product->effectiveTm420($tier);

        $result = $this->stock->allocate($product->brand_id, $product->id, $data['ukuran'], $data['qty']);

        if ($result['remaining'] > 0) {
            $tersedia = $this->stock->availableTotal($product->brand_id, $product->id, $data['ukuran']);

            return back()->withInput()->with('error', "Stok tidak cukup untuk ukuran {$data['ukuran']}. Tersedia {$tersedia} pcs.");
        }

        DB::transaction(function () use ($result, $product, $data, $diferd, $tm420) {
            foreach ($result['alloc'] as $a) {
                Sale::create([
                    'brand_id' => $product->brand_id,
                    'product_id' => $product->id,
                    'batch_id' => $a['batch']->id,
                    'ukuran' => $data['ukuran'],
                    'qty' => $a['qty'],
                    'tanggal_terjual' => $data['tanggal_terjual'],
                    'marketplace' => $data['marketplace'],
                    'nomor_pesanan' => $data['nomor_pesanan'] ?? null,
                    'harga_diferd' => $diferd,
                    'harga_tm420' => $tm420,
                    'keterangan' => $data['keterangan'] ?? null,
                ]);
            }
        });

        $n = count($result['alloc']);
        $msg = $n > 1
            ? "Penjualan dicatat (stok ditarik FIFO dari {$n} batch)."
            : 'Penjualan dicatat.';

        return redirect()->route('sales.index')->with('success', $msg);
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return redirect()->route('sales.index')->with('success', 'Penjualan dihapus, stok dikembalikan.');
    }

    // ----- helpers -----

    private function scope($query, Request $request): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }
    }

    private function scopedProducts(Request $request)
    {
        $query = Product::with(['brand', 'category.prices'])->where('aktif', true)->orderBy('nama_artikel');
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        return $query->get();
    }

    private function productRule(Request $request)
    {
        $rule = Rule::exists('products', 'id');
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $rule->where('brand_id', $user->brand_id);
        }

        return $rule;
    }
}

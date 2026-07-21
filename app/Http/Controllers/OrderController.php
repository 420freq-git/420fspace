<?php

namespace App\Http\Controllers;

use App\Enums\Marketplace;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\SizeTier;
use App\Enums\Ukuran;
use App\Models\Order;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class OrderController extends Controller
{
    public function __construct(private StockService $stock) {}

    private const HARI_PERLU_DICEK = 12;

    public function index(Request $request)
    {
        $query = Order::with(['brand', 'items.product'])->latest('tanggal_pesanan')->latest('id');
        $this->scope($query, $request);

        if ($request->filled('cari')) {
            $c = $request->input('cari');
            $query->where(fn ($q) => $q->where('nomor_pesanan', 'like', "%{$c}%")->orWhere('resi', 'like', "%{$c}%"));
        }
        if ($request->filled('channel')) {
            $query->where('marketplace', $request->input('channel'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->boolean('belum_cair')) {
            $query->whereNotIn('status', ['lunas', 'batal']);
        }
        if ($request->filled('dari')) {
            $query->whereDate('tanggal_pesanan', '>=', $request->date('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal_pesanan', '<=', $request->date('sampai'));
        }

        // Banner monitoring (hanya bila ada)
        $bannerBase = fn () => Order::query()->tap(fn ($q) => $this->scope($q, $request));
        $perluDicek = $bannerBase()->whereIn('status', ['dipesan', 'dikirim'])
            ->whereDate('tanggal_pesanan', '<=', now()->subDays(\App\Models\Setting::intVal('monitor_hari', self::HARI_PERLU_DICEK)))->count();
        $returPending = $bannerBase()->where('status', 'retur')->whereNull('tgl_retur_diterima')->count();

        return view('orders.index', [
            'orders' => $query->paginate(30)->withQueryString(),
            'perluDicek' => $perluDicek,
            'returPending' => $returPending,
            'marketplaces' => Marketplace::cases(),
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function create(Request $request)
    {
        return view('orders.create', [
            'products' => $this->scopedProducts($request),
            'marketplaces' => Marketplace::cases(),
            'ukurans' => Ukuran::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nomor_pesanan' => ['required', 'string', 'max:255', Rule::unique('orders', 'nomor_pesanan')],
            'resi' => ['nullable', 'string', 'max:255'],
            'marketplace' => ['required', new Enum(Marketplace::class)],
            'tanggal_pesanan' => ['required', 'date'],
            'status' => ['required', new Enum(OrderStatus::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->productRule($request)],
            'items.*.ukuran' => ['required', new Enum(Ukuran::class)],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $marketplace = Marketplace::from($data['marketplace']);
        // Channel langsung (offline/web) → order langsung LUNAS.
        $status = $marketplace->isLangsungLunas() ? OrderStatus::Lunas : OrderStatus::from($data['status']);

        // Gabungkan baris dengan produk+ukuran sama, lalu muat produk.
        $lines = collect($data['items'])
            ->groupBy(fn ($i) => $i['product_id'].'|'.$i['ukuran'])
            ->map(fn ($g) => [
                'product' => Product::find($g->first()['product_id']),
                'ukuran' => $g->first()['ukuran'],
                'qty' => (int) $g->sum('qty'),
            ])->values();

        // Semua artikel harus satu brand (satu pesanan = satu brand).
        if ($lines->pluck('product.brand_id')->unique()->count() > 1) {
            return back()->withInput()->with('error', 'Semua artikel dalam satu pesanan harus dari brand yang sama.');
        }

        // Cek stok tiap baris dulu (sebelum membuat apa pun).
        $planned = [];
        foreach ($lines as $line) {
            $product = $line['product'];
            $result = $this->stock->allocate($product->brand_id, $product->id, $line['ukuran'], $line['qty']);
            if ($result['remaining'] > 0) {
                $tersedia = $this->stock->availableTotal($product->brand_id, $product->id, $line['ukuran']);

                return back()->withInput()->with('error', "Stok tidak cukup: {$product->nama_artikel} {$line['ukuran']} — tersedia {$tersedia} pcs.");
            }
            $planned[] = ['line' => $line, 'alloc' => $result['alloc']];
        }

        DB::transaction(function () use ($data, $marketplace, $status, $planned) {
            $brandId = $planned[0]['line']['product']->brand_id;
            $order = Order::create([
                'brand_id' => $brandId,
                'nomor_pesanan' => $data['nomor_pesanan'],
                'resi' => $data['resi'] ?? null,
                'marketplace' => $marketplace->value,
                'tanggal_pesanan' => $data['tanggal_pesanan'],
                'status' => $status->value,
                'tgl_kirim' => in_array($status, [OrderStatus::Dikirim, OrderStatus::Lunas]) ? $data['tanggal_pesanan'] : null,
                'tgl_cair' => $status === OrderStatus::Lunas ? $data['tanggal_pesanan'] : null,
                'sumber' => 'manual',
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            foreach ($planned as $p) {
                $product = $p['line']['product'];
                $ukuran = $p['line']['ukuran'];
                $tier = SizeTier::forUkuran($ukuran);
                foreach ($p['alloc'] as $a) {
                    $order->items()->create([
                        'brand_id' => $product->brand_id,
                        'product_id' => $product->id,
                        'batch_id' => $a['batch']->id,
                        'ukuran' => $ukuran,
                        'qty' => $a['qty'],
                        'tanggal_terjual' => $data['tanggal_pesanan'],
                        'marketplace' => $marketplace->value,
                        'nomor_pesanan' => $data['nomor_pesanan'],
                        'harga_diferd' => $product->effectiveDiferd($tier) ?? 0,
                        'harga_tm420' => $product->hargaTagihan($tier),
                    ]);
                }
            }
        });

        return redirect()->route('orders.index')->with('success', 'Pesanan dicatat'.($status === OrderStatus::Lunas ? ' (langsung lunas).' : '.'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', new Enum(OrderStatus::class)]]);
        $status = OrderStatus::from($data['status']);

        $patch = ['status' => $status->value];
        if ($status === OrderStatus::Dikirim && ! $order->tgl_kirim) {
            $patch['tgl_kirim'] = now();
        }
        if ($status === OrderStatus::Lunas && ! $order->tgl_cair) {
            $patch['tgl_cair'] = now();
        }
        if ($status === OrderStatus::Retur && ! $order->tgl_retur) {
            $patch['tgl_retur'] = now();
        }

        $order->update($patch);

        return back()->with('success', "Status pesanan {$order->nomor_pesanan} → {$status->label()}.");
    }

    public function destroy(Order $order)
    {
        $order->delete(); // cascade items → stok kembali

        return redirect()->route('orders.index')->with('success', 'Pesanan dihapus, stok dikembalikan.');
    }

    /** Ubah status banyak pesanan sekaligus (mis. tandai dikirim). */
    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['integer'],
            'status' => ['required', new Enum(OrderStatus::class)],
        ]);

        $status = OrderStatus::from($data['status']);
        $query = Order::whereIn('id', $data['order_ids']);
        $this->scope($query, $request);

        $count = 0;
        foreach ($query->get() as $order) {
            $patch = ['status' => $status->value];
            if ($status === OrderStatus::Dikirim && ! $order->tgl_kirim) {
                $patch['tgl_kirim'] = now();
            }
            if ($status === OrderStatus::Lunas && ! $order->tgl_cair) {
                $patch['tgl_cair'] = now();
            }
            if ($status === OrderStatus::Retur && ! $order->tgl_retur) {
                $patch['tgl_retur'] = now();
            }
            $order->update($patch);
            $count++;
        }

        return back()->with('success', "{$count} pesanan diubah ke status {$status->label()}.");
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

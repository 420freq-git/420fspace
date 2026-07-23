<?php

namespace Tests;

use App\Enums\BrandType;
use App\Models\Batch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryPrice;
use App\Models\Order;
use App\Models\Pengiriman;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

/**
 * Base untuk test jalur kritis 420Frequency (uang/stok/status/scoping).
 * Seed master minimal + helper alur yang memanggil controller/service asli.
 *
 * Harga kategori Reguler: s_xl diferd 60.000 / tm420 70.000 (markup 10.000); xxl 65.000 / 75.000.
 */
abstract class ErpTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $tm;
    protected User $voojah;
    protected User $diferd;
    protected Brand $brandTm;
    protected Brand $brandVoojah;
    protected Product $produkTm;
    protected Product $produkTm2;
    protected Product $produkVoojah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedErp();
    }

    protected function seedErp(): void
    {
        $this->brandTm = Brand::create(['nama' => 'TM420', 'tipe' => BrandType::Eksternal->value, 'kode' => 'TM', 'aktif' => true]);
        $this->brandVoojah = Brand::create(['nama' => 'VOOJAH', 'tipe' => BrandType::MilikSendiri->value, 'kode' => 'VJ', 'aktif' => true]);

        $cat = Category::create(['nama' => 'Reguler', 'aktif' => true]);
        CategoryPrice::create(['category_id' => $cat->id, 'size_tier' => 's_xl', 'harga_diferd' => 60000, 'harga_tm420' => 70000]);
        CategoryPrice::create(['category_id' => $cat->id, 'size_tier' => 'xxl', 'harga_diferd' => 65000, 'harga_tm420' => 75000]);

        $this->produkTm = $this->buatProduk($this->brandTm, $cat, 'TM-A', 'Kaos TM');
        $this->produkTm2 = $this->buatProduk($this->brandTm, $cat, 'TM-B', 'Kaos TM 2');
        $this->produkVoojah = $this->buatProduk($this->brandVoojah, $cat, 'VJ-A', 'Kaos VOOJAH');

        $this->admin = $this->buatUser('420f', null);
        $this->tm = $this->buatUser('tm420', $this->brandTm->id);
        $this->voojah = $this->buatUser('voojah', $this->brandVoojah->id);
        $this->diferd = $this->buatUser('difred', null);
    }

    private function buatProduk(Brand $brand, Category $cat, string $sku, string $nama): Product
    {
        $p = Product::create([
            'brand_id' => $brand->id, 'category_id' => $cat->id,
            'sku_induk' => $sku, 'nama_artikel' => $nama, 'aktif' => true,
        ]);
        foreach (['M', 'L', 'XL'] as $u) {
            ProductSize::create(['product_id' => $p->id, 'ukuran' => $u, 'sku_turunan' => $sku.'-'.$u]);
        }

        return $p->load('brand', 'category.prices');
    }

    private function buatUser(string $role, ?int $brandId): User
    {
        return User::create([
            'name' => $role, 'email' => $role.'-'.uniqid().'@test.local',
            'password' => bcrypt('rahasia123'), 'role' => $role, 'brand_id' => $brandId,
        ]);
    }

    protected function req(User $u, array $d = [], string $m = 'POST'): Request
    {
        $r = Request::create('/x', $m, $d);
        $r->setUserResolver(fn () => $u);

        return $r;
    }

    // ---------- Helper alur (pakai controller/service asli) ----------

    /** Batch aktif + 1 PO. $qty = ['M'=>10,'L'=>5] (jenis 'pendek'). */
    protected function batchAktif(Product $produk, array $qty, string $tipe = 'termin'): Batch
    {
        $bc = app(\App\Http\Controllers\BatchController::class);
        $poc = app(\App\Http\Controllers\PurchaseOrderController::class);
        $pemilik = $produk->brand_id === $this->brandTm->id ? $this->tm : $this->voojah;

        $bc->store($this->req($pemilik, [
            'brand_id' => $produk->brand_id, 'tanggal_order' => now()->subDays(10)->toDateString(),
            'jenis_order' => 'full_order', 'type_payment' => $tipe,
        ]));
        $batch = Batch::latest('id')->first();
        $batch->update(['deadline' => now()->addDays(30)->toDateString(), 'deadline_produksi' => now()->addDays(10)->toDateString()]);

        $poQty = [];
        foreach ($qty as $uk => $n) {
            $poQty[$uk] = ['pendek' => $n];
        }
        $poc->store($this->req($pemilik, ['product_id' => $produk->id, 'qty' => $poQty]), $batch);
        $bc->approve($this->req($this->admin), $batch->fresh());

        return $batch->fresh();
    }

    /** Majukan produksi -> siap_kirim -> surat jalan -> terima. $terima keyed 'productId|ukuran' => qty (default penuh). */
    protected function produksiTerima(Batch $batch, array $terima = []): void
    {
        $poc = app(\App\Http\Controllers\PurchaseOrderController::class);
        $peng = app(\App\Http\Controllers\PengirimanController::class);

        foreach (PurchaseOrder::where('batch_id', $batch->id)->get() as $po) {
            $poc->updateStatus($this->req($this->admin, ['tahap' => 'siap_kirim'], 'PATCH'), $batch, $po);
        }

        $items = [];
        foreach (PurchaseOrder::with('sizeItems')->where('batch_id', $batch->id)->get() as $po) {
            foreach ($po->sizeItems as $si) {
                $items[] = ['product_id' => $po->product_id, 'ukuran' => $si->ukuran->value, 'qty' => (int) $si->qty];
            }
        }
        $peng->store($this->req($this->admin, ['batch_id' => $batch->id, 'tanggal_kirim' => now()->toDateString(), 'items' => $items]));

        $sj = Pengiriman::where('batch_id', $batch->id)->latest('id')->first()->load('items');
        $dt = [];
        $kurang = false;
        foreach ($sj->items as $it) {
            $key = $it->product_id.'|'.$it->ukuran->value;
            $qtyTerima = $terima[$key] ?? (int) $it->qty;
            $dt[$it->id] = $qtyTerima;
            if ($qtyTerima < (int) $it->qty) {
                $kurang = true;
            }
        }
        $payload = ['diterima' => $dt];
        if ($kurang) {
            $payload['alasan_kurang_terima'] = 'reject';
        }
        $peng->terima($this->req($this->admin, $payload, 'PATCH'), $sj);
    }

    /** Jual: buat pesanan + item, tandai lunas. */
    protected function jual(Product $produk, string $uk, int $qty, string $mp = 'tiktokshop'): Order
    {
        $oc = app(\App\Http\Controllers\OrderController::class);
        $u = $produk->brand_id === $this->brandTm->id ? $this->tm : $this->voojah;
        $nomor = 'ORD-'.uniqid();

        $oc->store($this->req($u, [
            'nomor_pesanan' => $nomor, 'marketplace' => $mp, 'tanggal_pesanan' => now()->toDateString(),
            'status' => 'dipesan', 'items' => [['product_id' => $produk->id, 'ukuran' => $uk, 'qty' => $qty]],
        ]));
        $order = Order::where('nomor_pesanan', $nomor)->first();
        if ($order->status->value !== 'lunas') {
            $oc->updateStatus($this->req($this->admin, ['status' => 'lunas'], 'PATCH'), $order);
        }

        return $order->fresh();
    }

    protected function settlement(): \App\Services\SettlementService
    {
        return app(\App\Services\SettlementService::class);
    }

    protected function stock(): \App\Services\StockService
    {
        return app(\App\Services\StockService::class);
    }
}

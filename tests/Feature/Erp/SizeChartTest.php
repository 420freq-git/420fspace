<?php

namespace Tests\Feature\Erp;

use App\Enums\JenisProduksi;
use App\Models\Batch;
use App\Models\Category;
use App\Models\CategoryPrice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Support\SizeChart;
use Tests\ErpTestCase;

/**
 * Jenis produksi otomatis dari kategori + size chart per kategori di Master PO.
 * (Dikunci 25 Jul 2026 — cegah salah input jenis & jaga PDF satu halaman.)
 */
class SizeChartTest extends ErpTestCase
{
    /** Pemetaan kategori → jenis produksi sesuai daftar vendor. */
    public function test_kategori_menentukan_jenis_produksi(): void
    {
        $peta = [
            'Reguler 24s' => JenisProduksi::Pendek,
            'Oversized 20s' => JenisProduksi::Pendek,
            'Oversized 24s' => JenisProduksi::Pendek,
            'Longsleeve 24s' => JenisProduksi::Panjang,
            'Longsleeve Biowash 24s' => JenisProduksi::Panjang,
            'Double Layer 24s' => JenisProduksi::Panjang,
            'Hoodie Jumper 280gsm' => JenisProduksi::Panjang,
            'Lekbong 24s' => JenisProduksi::Lekbong,
            'Raglan 24s' => JenisProduksi::Raglan34,
        ];

        foreach ($peta as $nama => $jenis) {
            $cat = new Category(['nama' => $nama]);
            $this->assertSame($jenis, $cat->jenisProduksi(), "Kategori {$nama} salah jenis");
        }
    }

    /** Simpan PO dengan qty per ukuran (tanpa pilih jenis) → jenis diisi dari kategori. */
    public function test_simpan_po_mengisi_jenis_dari_kategori(): void
    {
        $ls = $this->buatKategoriProduk('Longsleeve 24s', 'LS-1');

        $poc = app(\App\Http\Controllers\PurchaseOrderController::class);
        app(\App\Http\Controllers\BatchController::class)->store($this->req($this->tm, [
            'brand_id' => $this->brandTm->id, 'tanggal_order' => now()->toDateString(),
            'jenis_order' => 'full_order', 'type_payment' => 'termin',
        ]));
        $batch = Batch::latest('id')->first();

        // Format BARU: satu qty per ukuran, tanpa dimensi jenis.
        $poc->store($this->req($this->tm, ['product_id' => $ls->id, 'qty' => ['S' => 7, 'XL' => 3]]), $batch);

        $po = PurchaseOrder::with('sizeItems')->where('batch_id', $batch->id)->latest('id')->first();
        $this->assertSame(10, $po->total_qty);
        foreach ($po->sizeItems as $si) {
            $this->assertSame(JenisProduksi::Panjang, $si->jenis, 'Longsleeve harus jenis panjang');
        }
    }

    /** Size chart benar per kategori + file gambarnya ada. */
    public function test_size_chart_per_kategori(): void
    {
        $reg = new Category(['nama' => 'Reguler 24s']);
        $chart = SizeChart::forCategory($reg);
        $this->assertSame(['Panjang', 'Lebar', 'Lengan'], $chart['kolom']);
        $this->assertSame([69, 48, 21], $chart['baris']['S']);
        $this->assertNotNull(SizeChart::imagePath($reg), 'gambar reguler.png harus ada');

        $ov = new Category(['nama' => 'Oversized 24s']);
        $this->assertContains('Bahu', SizeChart::forCategory($ov)['kolom']);
        $this->assertNotNull(SizeChart::imagePath($ov));

        $dl = new Category(['nama' => 'Double Layer 24s']);
        $this->assertSame([69, 48, 21, 61], SizeChart::forCategory($dl)['baris']['S']);
    }

    private function buatKategoriProduk(string $nama, string $sku): Product
    {
        $cat = Category::create(['nama' => $nama, 'aktif' => true]);
        CategoryPrice::create(['category_id' => $cat->id, 'size_tier' => 's_xl', 'harga_diferd' => 60000, 'harga_tm420' => 70000]);
        CategoryPrice::create(['category_id' => $cat->id, 'size_tier' => 'xxl', 'harga_diferd' => 65000, 'harga_tm420' => 75000]);

        $p = Product::create([
            'brand_id' => $this->brandTm->id, 'category_id' => $cat->id,
            'sku_induk' => $sku, 'nama_artikel' => 'Artikel '.$sku, 'aktif' => true,
        ]);
        foreach (['S', 'M', 'L', 'XL', 'XXL'] as $u) {
            \App\Models\ProductSize::create(['product_id' => $p->id, 'ukuran' => $u, 'sku_turunan' => $sku.'-'.$u]);
        }

        return $p->load('category');
    }
}

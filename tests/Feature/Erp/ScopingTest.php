<?php

namespace Tests\Feature\Erp;

use App\Models\Order;
use App\Models\ProductFile;
use Tests\ErpTestCase;

class ScopingTest extends ErpTestCase
{
    private function orderVoojah(): Order
    {
        return Order::create([
            'brand_id' => $this->brandVoojah->id, 'nomor_pesanan' => 'VJ-'.uniqid(),
            'marketplace' => 'tiktokshop', 'tanggal_pesanan' => now(), 'status' => 'dipesan', 'sumber' => 'manual',
        ]);
    }

    public function test_tm_tak_bisa_hapus_pesanan_brand_lain(): void
    {
        $order = $this->orderVoojah();
        $this->actingAs($this->tm)->delete(route('orders.destroy', $order))->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_tm_tak_bisa_ubah_status_pesanan_brand_lain(): void
    {
        $order = $this->orderVoojah();
        $this->actingAs($this->tm)
            ->patch(route('orders.status', $order), ['status' => 'batal'])
            ->assertForbidden();
        $this->assertSame('dipesan', $order->fresh()->status->value);
    }

    public function test_tm_tak_bisa_hapus_file_produk_brand_lain(): void
    {
        $file = ProductFile::create([
            'product_id' => $this->produkVoojah->id, 'tipe' => 'mockup',
            'path' => 'produk/x.jpg', 'nama_asli' => 'x.jpg',
        ]);
        $this->actingAs($this->tm)->delete(route('product-files.destroy', $file))->assertForbidden();
        $this->assertDatabaseHas('product_files', ['id' => $file->id]);
    }

    public function test_tm_boleh_hapus_pesanan_brand_sendiri(): void
    {
        $order = Order::create([
            'brand_id' => $this->brandTm->id, 'nomor_pesanan' => 'TM-'.uniqid(),
            'marketplace' => 'tiktokshop', 'tanggal_pesanan' => now(), 'status' => 'dipesan', 'sumber' => 'manual',
        ]);
        $this->actingAs($this->tm)->delete(route('orders.destroy', $order))->assertRedirect();
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }
}

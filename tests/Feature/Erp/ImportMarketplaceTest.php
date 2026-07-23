<?php

namespace Tests\Feature\Erp;

use App\Services\MarketplaceImportService;
use Illuminate\Http\UploadedFile;
use Tests\ErpTestCase;

class ImportMarketplaceTest extends ErpTestCase
{
    /** Bangun CSV format export TikTok. $rows = [[orderId,status,resi,sku,qty,tgl,nama], ...]. */
    private function csvTiktok(array $rows): UploadedFile
    {
        $headers = ['Order ID', 'Order Status', 'Tracking ID', 'Seller SKU', 'Quantity', 'Created Time', 'Product Name'];
        $lines = [implode(',', $headers)];
        foreach ($rows as $r) {
            $lines[] = implode(',', $r);
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, implode("\n", $lines));

        return new UploadedFile($path, 'tiktok.csv', 'text/csv', null, true);
    }

    public function test_import_tiktok_buat_pesanan_dan_lewati_stok_nol(): void
    {
        // Hanya produk TM-A yang punya stok (M=5). TM-B belum diproduksi (stok 0).
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);

        $result = app(MarketplaceImportService::class)->import($this->csvTiktok([
            ['ORD-A', 'Completed', 'TRK1', 'TM-A-M', '2', '2026-07-20 10:00', 'Kaos TM'],
            ['ORD-B', 'Completed', 'TRK2', 'TM-B-M', '1', '2026-07-20 10:00', 'Kaos TM 2'],
        ]));

        // ORD-A dibuat (stok ada); ORD-B dilewati (stok 0) → guard tak buat pesanan hantu.
        $this->assertSame(1, (int) $result['imported_orders']);
        $this->assertDatabaseHas('orders', ['nomor_pesanan' => 'ORD-A']);
        $this->assertDatabaseMissing('orders', ['nomor_pesanan' => 'ORD-B']);

        // Snapshot harga TM = tm420 (retail).
        $sale = \App\Models\Sale::whereHas('order', fn ($o) => $o->where('nomor_pesanan', 'ORD-A'))->first();
        $this->assertSame(60000, (int) $sale->harga_diferd);
        $this->assertSame(70000, (int) $sale->harga_tm420);
    }

    public function test_satu_file_berisi_pesanan_tm_dan_voojah_masuk_brand_masing_masing(): void
    {
        // Kedua brand punya stok.
        $this->produksiTerima($this->batchAktif($this->produkTm, ['M' => 5]));
        $this->produksiTerima($this->batchAktif($this->produkVoojah, ['M' => 5]));

        // Satu file, dua pesanan: satu SKU TM, satu SKU VOOJAH.
        $result = app(MarketplaceImportService::class)->import($this->csvTiktok([
            ['ORD-TM', 'Completed', 'T1', 'TM-A-M', '2', '2026-07-20 10:00', 'Kaos TM'],
            ['ORD-VJ', 'Completed', 'T2', 'VJ-A-M', '1', '2026-07-20 10:00', 'Kaos VOOJAH'],
        ]));

        $this->assertSame(2, (int) $result['imported_orders']);
        // Tiap pesanan masuk ke brand yang benar (dari SKU), tanpa perlu pisah file.
        $this->assertDatabaseHas('orders', ['nomor_pesanan' => 'ORD-TM', 'brand_id' => $this->brandTm->id]);
        $this->assertDatabaseHas('orders', ['nomor_pesanan' => 'ORD-VJ', 'brand_id' => $this->brandVoojah->id]);
    }

    public function test_import_lewati_pesanan_dibatalkan(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 5]);
        $this->produksiTerima($batch);

        $result = app(MarketplaceImportService::class)->import($this->csvTiktok([
            ['ORD-X', 'Canceled', 'TRK9', 'TM-A-M', '1', '2026-07-20 10:00', 'Kaos TM'],
        ]));

        $this->assertSame(0, (int) $result['imported_orders']);
        $this->assertDatabaseMissing('orders', ['nomor_pesanan' => 'ORD-X']);
    }
}

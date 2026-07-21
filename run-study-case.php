<?php

use App\Models\User;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryPrice;
use App\Models\Product;
use App\Models\Batch;
use App\Models\PurchaseOrder;
use App\Models\PoSizeItem;
use App\Models\Pengiriman;
use App\Models\Sale;
use App\Models\VendorLedger;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;

// Reset dan Run testing script
echo "Mulai Pengujian Study Case: Deposit Opsi B & Alur Penuh...\n";

try {
    DB::beginTransaction();

    // 1. SETUP DATA MASTER
    echo "\n[Tahap 1] Setup Master Data...\n";
    $f420 = User::where('role', '420f')->first() ?? User::factory()->create(['role' => '420f']);
    $tm420User = User::where('role', 'tm420')->first() ?? User::factory()->create(['role' => 'tm420']);
    $difredUser = User::where('role', 'difred')->first() ?? User::factory()->create(['role' => 'difred']);

    $brandTm = Brand::firstOrCreate(['nama' => 'TM420', 'tipe' => 'eksternal']);

    $kategori = Category::firstOrCreate(['nama' => 'Reguler 24s', 'aktif' => true]);
    CategoryPrice::updateOrCreate(
        ['category_id' => $kategori->id, 'size_tier' => 's_xl'],
        ['harga_diferd' => 62000, 'harga_tm420' => 67000]
    );

    $produk = Product::firstOrCreate(
        ['brand_id' => $brandTm->id, 'nama_artikel' => 'T-Shirt Hitam Test'],
        ['category_id' => $kategori->id, 'sku_induk' => 'TM-TSB-01']
    );
    echo "Produk dibuat: {$produk->nama_artikel}\n";

    // Deposit Global Awal
    $depositAwal = 10000000;
    VendorLedger::create([
        'brand_id' => $brandTm->id,
        'batch_id' => null,
        'tanggal' => now(),
        'tipe' => 'deposit',
        'jumlah' => $depositAwal,
        'keterangan' => 'Deposit Modal Kerja Sama (Opsi B)'
    ]);
    echo "Deposit Global dicatat: Rp " . number_format($depositAwal, 0, ',', '.') . "\n";


    // 2. PENGAJUAN PO
    echo "\n[Tahap 2] Pengajuan Batch & PO...\n";
    $batch = Batch::create([
        'brand_id' => $brandTm->id,
        'vendor' => 'Diferd',
        'nomor_batch' => 'BATCH-TEST-001',
        'tanggal_order' => now(),
        'deadline' => now()->addYear(),
        'jenis_order' => 'full_order',
        'type_payment' => 'termin',
        // deposit_awal dihapus sesuai spek baru (jika ada error disini berarti db blm di migrate)
        'status' => 'aktif' // Langsung setujui
    ]);
    echo "Batch {$batch->nomor_batch} dibuat & disetujui.\n";

    $po = PurchaseOrder::create([
        'batch_id' => $batch->id,
        'product_id' => $produk->id,
        'nomor_po' => 'PO.TM.07.26.01',
        'status_produksi' => 'antri'
    ]);

    PoSizeItem::insert([
        ['purchase_order_id' => $po->id, 'ukuran' => 'S', 'jenis' => 'pendek', 'qty' => 10],
        ['purchase_order_id' => $po->id, 'ukuran' => 'M', 'jenis' => 'pendek', 'qty' => 20],
        ['purchase_order_id' => $po->id, 'ukuran' => 'L', 'jenis' => 'pendek', 'qty' => 20],
    ]);
    echo "PO {$po->nomor_po} dengan QTY 50 (S:10, M:20, L:20) dibuat.\n";


    // 3. PRODUKSI & PENERIMAAN
    echo "\n[Tahap 3] Produksi & Penerimaan...\n";
    $po->update(['tahap' => 'terkirim']); // 'tahap' di PurchaseOrder
    echo "Status PO diubah Vendor -> Terkirim\n";

    $pengiriman = Pengiriman::create([
        'batch_id' => $batch->id,
        'nomor_sj' => 'SJ-TEST-001',
        'tanggal_kirim' => now(),
        'status' => 'diterima',
        'tgl_diterima' => now(),
        'catatan' => 'Penerimaan Test'
    ]);

    // Add items for stock (this is what is used by StokService)
    \App\Models\PengirimanItem::insert([
        ['pengiriman_id' => $pengiriman->id, 'product_id' => $produk->id, 'ukuran' => 'S', 'qty' => 10, 'qty_diterima' => 10],
        ['pengiriman_id' => $pengiriman->id, 'product_id' => $produk->id, 'ukuran' => 'M', 'qty' => 20, 'qty_diterima' => 20],
        ['pengiriman_id' => $pengiriman->id, 'product_id' => $produk->id, 'ukuran' => 'L', 'qty' => 20, 'qty_diterima' => 20],
    ]);

    echo "Barang diterima 420F, Stok tersedia.\n";


    // 4. PENJUALAN
    echo "\n[Tahap 4] Penjualan & Hak Diferd...\n";

    $order = \App\Models\Order::create([
        'brand_id' => $brandTm->id,
        'nomor_pesanan' => 'ORD-TEST-001',
        'marketplace' => 'shopee',
        'tanggal_pesanan' => now(),
        'status' => 'lunas' // Needs lunas to be considered kewajiban vendor
    ]);

    Sale::create([
        'order_id' => $order->id,
        'brand_id' => $brandTm->id,
        'product_id' => $produk->id,
        'batch_id' => $batch->id,
        'ukuran' => 'M',
        'qty' => 10,
        'tanggal_terjual' => now(),
        'marketplace' => 'shopee',
        'nomor_pesanan' => 'ORD-TEST-001',
        'harga_diferd' => 62000,
        'harga_tm420' => 67000
    ]);

    echo "Penjualan 10 pcs M @ Shopee dicatat.\n";

    $service = new SettlementService(new \App\Services\StockService());
    $dataSettlement = $service->batchSummary($batch);
    echo "\nCek Settlement Batch {$batch->nomor_batch}:\n";
    echo "- Kewajiban (Hak Diferd 10pcs * 62k) : Rp " . number_format($dataSettlement['kewajiban'], 0, ',', '.') . "\n";
    echo "- Saldo (Sisa Hak Belum Dibayar)     : Rp " . number_format($dataSettlement['saldo'], 0, ',', '.') . "\n";
    echo "- Fee 420F (10pcs * 5k)              : Rp " . number_format($dataSettlement['fee420f'], 0, ',', '.') . "\n";
    echo "- Deposit Mengendap (Global)         : Rp " . number_format($service->depositMengendap(), 0, ',', '.') . "\n";

    if ($dataSettlement['saldo'] !== 620000) {
        throw new Exception("Error: Saldo tidak sesuai! Harus 620.000 (tidak potong deposit)");
    }


    // 5. SETTLEMENT DEPOSIT (SIMULASI AKHIR KERJA SAMA)
    echo "\n[Tahap 5] Offset Deposit (Penyelesaian Kerja Sama)...\n";

    // Simulate Controller logic
    VendorLedger::create([
        'brand_id' => $brandTm->id,
        'batch_id' => null,
        'tanggal' => now(),
        'tipe' => 'deposit_selesai',
        'jumlah' => $depositAwal,
        'keterangan' => 'Deposit di-offset ke hak Diferd (test)'
    ]);
    \App\Models\BrandLedger::create([
        'brand_id' => $brandTm->id,
        'tanggal' => now(),
        'jumlah' => $depositAwal,
        'keterangan' => 'Uang muka (deposit) — test'
    ]);
    $rencana = $service->rencanaAlokasi($depositAwal);
    foreach ($rencana['alokasi'] as $bId => $bagian) {
        VendorLedger::create([
            'brand_id' => $batch->brand_id,
            'batch_id' => $bId,
            'tanggal' => now(),
            'tipe' => 'pembayaran',
            'jumlah' => $bagian,
            'keterangan' => 'Offset deposit (test)'
        ]);
    }
    if ($rencana['sisa'] > 0) {
        VendorLedger::create([
            'brand_id' => $brandTm->id,
            'batch_id' => null,
            'tanggal' => now(),
            'tipe' => 'pembayaran',
            'jumlah' => $rencana['sisa'],
            'keterangan' => 'Offset deposit — tak terserap'
        ]);
    }

    $sisaModal = $service->depositMengendap();
    $dataSettlementSetelahOffset = $service->batchSummary($batch);
    echo "Deposit Mengendap setelah Offset     : Rp " . number_format($sisaModal, 0, ',', '.') . "\n";
    echo "Saldo Batch setelah Offset Deposit   : Rp " . number_format($dataSettlementSetelahOffset['saldo'], 0, ',', '.') . "\n";

    if ($sisaModal !== 0) {
        throw new Exception("Error: Modal mengendap harus 0 setelah diselesaikan!");
    }
    if ($dataSettlementSetelahOffset['saldo'] !== 0) {
        throw new Exception("Error: Saldo hak Diferd di batch harus tertutup oleh deposit (menjadi 0)");
    }

    echo "\n✅ SUCCESS: Semua skenario berjalan sesuai Aturan Deposit Global!\n";
    DB::rollBack();
    echo "(Rollback DB - data uji tidak disimpan permanen)\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PenarikanController;
use App\Http\Controllers\PengirimanController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\MonitoringProduksiController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\StokController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Produk — daftar bisa dilihat semua role (TM420 discope ke brand-nya di controller)
    Route::get('products', [ProductController::class, 'index'])->name('products.index');

    // Export & unduh desain per batch/PO — semua role (TM420 discope)
    Route::get('batches/{batch}/designs.zip', [BatchController::class, 'downloadDesigns'])->name('batches.designs');
    Route::get('batches/{batch}/purchase-orders/{purchaseOrder}/pdf', [BatchController::class, 'poPdf'])->name('purchase-orders.pdf');
    Route::get('batches/{batch}/purchase-orders/{purchaseOrder}/riwayat', [PurchaseOrderController::class, 'riwayat'])->name('purchase-orders.riwayat');

    // Status produksi PO — admin 420F & vendor Diferd
    Route::middleware('role:420f,difred')->patch('batches/{batch}/purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])->name('purchase-orders.status');
    Route::middleware('role:420f,difred')->patch('batches/{batch}/purchase-orders/{purchaseOrder}/catatan', [PurchaseOrderController::class, 'updateCatatan'])->name('purchase-orders.catatan');

    // Dashboard monitoring produksi — semua role (TM420 discope ke brand-nya)
    Route::get('monitoring-produksi', [MonitoringProduksiController::class, 'index'])->name('monitoring-produksi.index');

    // Radar deadline & paparan buy-out — 420F & brand (discope). Vendor tak perlu (bukan risikonya).
    Route::middleware('role:420f,tm420,voojah')->get('radar-deadline', [\App\Http\Controllers\RadarController::class, 'index'])->name('radar.index');

    // Scorecard vendor — 420F & Diferd (vendor lihat kinerjanya sendiri).
    Route::middleware('role:420f,difred')->get('scorecard-vendor', [\App\Http\Controllers\ScorecardController::class, 'index'])->name('scorecard.index');

    // Rekomendasi produksi ulang — 420F & brand (yang memutuskan produksi).
    Route::middleware('role:420f,tm420,voojah')->get('produksi-ulang', [\App\Http\Controllers\RekomendasiController::class, 'index'])->name('rekomendasi.index');

    // Rapor per artikel — 420F & brand (discope); fee hanya admin.
    Route::middleware('role:420f,tm420,voojah')->get('rapor-produk', [\App\Http\Controllers\RaporProdukController::class, 'index'])->name('rapor-produk.index');

    // Analisis per channel marketplace — 420F & brand (discope).
    Route::middleware('role:420f,tm420,voojah')->get('analisis-channel', [\App\Http\Controllers\ChannelController::class, 'index'])->name('channel.index');

    // Pengiriman / Surat Jalan — lihat semua (scoped); buat: 420F & Diferd; terima: 420F
    Route::get('pengiriman', [PengirimanController::class, 'index'])->name('pengiriman.index');
    Route::middleware('role:420f,difred')->group(function () {
        Route::get('pengiriman/create', [PengirimanController::class, 'create'])->name('pengiriman.create');
        Route::post('pengiriman', [PengirimanController::class, 'store'])->name('pengiriman.store');
        Route::delete('pengiriman/{pengiriman}', [PengirimanController::class, 'destroy'])->name('pengiriman.destroy');
    });
    Route::get('pengiriman/{pengiriman}', [PengirimanController::class, 'show'])->name('pengiriman.show');
    Route::get('pengiriman/{pengiriman}/pdf', [PengirimanController::class, 'pdf'])->name('pengiriman.pdf');
    Route::middleware('role:420f,tm420,voojah')->patch('pengiriman/{pengiriman}/terima', [PengirimanController::class, 'terima'])->name('pengiriman.terima');

    // Stok — semua role (TM420 discope ke brand-nya)
    Route::get('stok', [StokController::class, 'index'])->name('stok.index');
    Route::middleware('role:420f,tm420,voojah')->group(function () {
        Route::get('stok/rekonsiliasi', [StokController::class, 'reconcile'])->name('stok.reconcile');
        Route::post('stok/rekonsiliasi', [StokController::class, 'reconcileRun'])->name('stok.reconcile.run');
    });

    // Pesanan — admin 420F & brand TM420
    Route::middleware('role:420f,tm420,voojah')->group(function () {
        Route::get('pesanan', [OrderController::class, 'index'])->name('orders.index');
        Route::get('pesanan/create', [OrderController::class, 'create'])->name('orders.create');
        Route::get('pesanan/import', [ImportController::class, 'form'])->name('orders.import.form');
        Route::post('pesanan/import', [ImportController::class, 'store'])->name('orders.import.store');
        Route::get('pesanan/settlement', [ImportController::class, 'settlementForm'])->name('orders.settlement.form');
        Route::post('pesanan/settlement', [ImportController::class, 'settlementStore'])->name('orders.settlement.store');
        Route::get('pesanan/monitoring', [MonitoringController::class, 'perluDicek'])->name('monitoring.cek');
        Route::post('pesanan/monitoring/{order}/dicek', [MonitoringController::class, 'sudahDicek'])->name('monitoring.dicek');
        Route::post('pesanan/monitoring/{order}/tolak', [MonitoringController::class, 'tolakRetur'])->name('monitoring.tolak');
        Route::get('pesanan/barang-kembali', [MonitoringController::class, 'barangKembali'])->name('monitoring.kembali');
        Route::post('pesanan/barang-kembali/{order}/terima', [MonitoringController::class, 'terimaRetur'])->name('monitoring.terima');
        Route::post('pesanan/bulk-status', [OrderController::class, 'bulkStatus'])->name('orders.bulk-status');
        Route::post('pesanan', [OrderController::class, 'store'])->name('orders.store');
        Route::patch('pesanan/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::delete('pesanan/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    });

    // Laporan — 420F, TM420 & Diferd (nilai disesuaikan per role di controller)
    Route::middleware('role:420f,tm420,voojah,difred')->group(function () {
        Route::get('laporan/penjualan', [ReportController::class, 'penjualan'])->name('laporan.penjualan');
        Route::get('laporan/penjualan/pdf', [ReportController::class, 'penjualanPdf'])->name('laporan.penjualan.pdf');
        Route::get('laporan/produksi', [ReportController::class, 'produksi'])->name('laporan.produksi');
        Route::get('laporan/produksi/pdf', [ReportController::class, 'produksiPdf'])->name('laporan.produksi.pdf');
        Route::get('laporan/keuangan', [ReportController::class, 'keuangan'])->name('laporan.keuangan');
        Route::get('laporan/keuangan/pdf', [ReportController::class, 'keuanganPdf'])->name('laporan.keuangan.pdf');
        Route::get('laporan/perputaran', [ReportController::class, 'perputaran'])->name('laporan.perputaran');
        Route::get('laporan/perputaran/pdf', [ReportController::class, 'perputaranPdf'])->name('laporan.perputaran.pdf');
    });
    // Terjual per kategori — TM420 tidak diberi akses (permintaan pemilik sistem).
    Route::middleware('role:420f,difred')->group(function () {
        Route::get('laporan/terjual-kategori', [ReportController::class, 'terjualKategori'])->name('laporan.terjual-kategori');
        Route::get('laporan/terjual-kategori/pdf', [ReportController::class, 'terjualKategoriPdf'])->name('laporan.terjual-kategori.pdf');
    });
    // Kerugian — dipisah per pihak: TM420 lihat rugi retur, Diferd lihat rugi produksi, 420F keduanya.
    Route::middleware('role:420f,tm420,voojah,difred')->group(function () {
        Route::get('laporan/kerugian', [ReportController::class, 'kerugian'])->name('laporan.kerugian');
        Route::get('laporan/kerugian/pdf', [ReportController::class, 'kerugianPdf'])->name('laporan.kerugian.pdf');
    });

    // Cashflow & Saldo 420F — admin 420F saja
    Route::middleware('role:420f')->group(function () {
        Route::get('saldo', [\App\Http\Controllers\SaldoController::class, 'index'])->name('saldo.index');
        Route::get('cashflow', [CashflowController::class, 'index'])->name('cashflow.index');
        Route::get('rekonsiliasi-tm', [\App\Http\Controllers\RekonsiliasiController::class, 'index'])->name('rekonsiliasi.index');
        Route::post('cashflow/transfer', [CashflowController::class, 'storeTransfer'])->name('cashflow.transfer.store');
        Route::delete('cashflow/transfer/{brandLedger}', [CashflowController::class, 'destroyTransfer'])->name('cashflow.transfer.destroy');
    });

    // Invoice ke TM — lihat: admin 420F & brand TM420 (discope); kelola: admin 420F
    Route::middleware('role:420f,tm420,voojah')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}/bukti', [InvoiceController::class, 'bukti'])->name('invoices.bukti');
        Route::post('invoices/{invoice}/bukti', [InvoiceController::class, 'uploadBukti'])->name('invoices.bukti.upload');
    });
    Route::middleware('role:420f')->group(function () {
        Route::post('invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::patch('invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    });

    // Manajemen user — admin 420F saja
    Route::middleware('role:420f')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('pengaturan', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('pengaturan', [SettingController::class, 'update'])->name('settings.update');
        Route::post('pengaturan/reset-transaksi', [SettingController::class, 'resetTransaksi'])->name('settings.reset');

        Route::get('audit-log', [AuditController::class, 'index'])->name('audit.index');
    });

    // Penarikan Diferd — lihat & ajukan: admin 420F & vendor Diferd; setujui: admin 420F
    Route::middleware('role:420f,difred')->group(function () {
        Route::get('penarikan', [PenarikanController::class, 'index'])->name('penarikan.index');
        Route::post('penarikan', [PenarikanController::class, 'store'])->name('penarikan.store');
        Route::delete('penarikan/{penarikan}', [PenarikanController::class, 'destroy'])->name('penarikan.destroy');
        Route::post('penarikan/{penarikan}/bukti', [PenarikanController::class, 'uploadBukti'])->name('penarikan.bukti.upload');
        Route::get('penarikan/{penarikan}/bukti/{jenis}', [PenarikanController::class, 'bukti'])->name('penarikan.bukti');
    });
    Route::middleware('role:420f')->group(function () {
        Route::patch('penarikan/{penarikan}/approve', [PenarikanController::class, 'approve'])->name('penarikan.approve');
        Route::patch('penarikan/{penarikan}/reject', [PenarikanController::class, 'reject'])->name('penarikan.reject');
    });

    // Settlement / Saldo Vendor — lihat: admin 420F & vendor Diferd; catat: admin 420F
    Route::middleware('role:420f,difred')->group(function () {
        Route::get('settlement', [SettlementController::class, 'index'])->name('settlement.index');
        Route::get('settlement/{batch}', [SettlementController::class, 'show'])->name('settlement.show');
    });
    Route::middleware('role:420f')->group(function () {
        Route::post('settlement/{batch}/ledger', [SettlementController::class, 'storeLedger'])->name('settlement.ledger.store');
        Route::delete('settlement/ledger/{ledger}', [SettlementController::class, 'destroyLedger'])->name('settlement.ledger.destroy');
        Route::patch('settlement/{batch}/status', [SettlementController::class, 'markStatus'])->name('settlement.status');
        Route::patch('settlement/{batch}/buyout', [SettlementController::class, 'buyout'])->name('settlement.buyout');
        Route::patch('settlement/{batch}/bayar-cash', [SettlementController::class, 'bayarCash'])->name('settlement.bayar-cash');
        Route::patch('settlement/{batch}/lunasi-sisa-cash', [SettlementController::class, 'lunasiSisaCash'])->name('settlement.lunasi-sisa-cash');
        Route::post('settlement/{batch}/bayar-diferd-cash', [SettlementController::class, 'bayarDiferdCash'])->name('settlement.bayar-diferd-cash');
        Route::post('settlement/{batch}/ganti-cash', [SettlementController::class, 'gantiCash'])->name('settlement.ganti-cash');
        Route::post('settlement/cash-ganti/{cashGanti}/refund-diferd', [SettlementController::class, 'refundDiferdMasuk'])->name('settlement.refund-diferd');
        Route::post('settlement/cash-ganti/{cashGanti}/refund-teruskan', [SettlementController::class, 'refundTeruskanTm'])->name('settlement.refund-teruskan');
        Route::patch('settlement/deposit/selesaikan', [SettlementController::class, 'selesaikanDeposit'])->name('settlement.deposit.selesaikan');
        Route::patch('settlement/{batch}/rekonsiliasi', [SettlementController::class, 'rekonsiliasiDeposit'])->name('settlement.rekonsiliasi');
    });

    // Produk — 420F & TM420 (TM dikunci ke brand-nya di controller); hapus tetap admin saja.
    Route::middleware('role:420f,tm420,voojah')->group(function () {
        Route::resource('products', ProductController::class)->except(['index', 'show', 'destroy']);
        Route::delete('product-files/{file}', [ProductController::class, 'destroyFile'])->name('product-files.destroy');
    });

    // Persetujuan batch — hanya 420F yang boleh menyetujui/menolak ajuan TM420.
    Route::middleware('role:420f')->group(function () {
        Route::patch('batches/{batch}/approve', [BatchController::class, 'approve'])->name('batches.approve');
        Route::patch('batches/{batch}/reject', [BatchController::class, 'reject'])->name('batches.reject');
    });
    // TM420 mengajukan ulang batch yang ditolak setelah diperbaiki.
    Route::middleware('role:420f,tm420,voojah')->patch('batches/{batch}/reajukan', [BatchController::class, 'reajukan'])->name('batches.reajukan');

    // Kelola master (hanya admin 420F) — rute statis 'create' didaftarkan sebelum wildcard {batch}
    Route::middleware('role:420f')->group(function () {
        Route::resource('brands', BrandController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // Batch (Master PO) — TM420 boleh mengajukan, hasilnya menunggu persetujuan 420F.
    Route::middleware('role:420f,tm420,voojah')->group(function () {
        Route::get('batches/create', [BatchController::class, 'create'])->name('batches.create');
        Route::post('batches', [BatchController::class, 'store'])->name('batches.store');
        Route::get('batches/{batch}/edit', [BatchController::class, 'edit'])->name('batches.edit');
        Route::put('batches/{batch}', [BatchController::class, 'update'])->name('batches.update');
        Route::delete('batches/{batch}', [BatchController::class, 'destroy'])->name('batches.destroy');

        // PO per-artikel di dalam batch
        Route::get('batches/{batch}/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('batches/{batch}/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('batches/{batch}/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('batches/{batch}/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::delete('batches/{batch}/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
    });

    // Detail produk + unduh file mentahan (semua role; TM420 discope ke brand-nya)
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('products/{product}/download-zip', [ProductController::class, 'downloadZip'])->name('products.download-zip');
    Route::get('product-files/{file}/download', [ProductController::class, 'downloadFile'])->name('product-files.download');

    // Batch: daftar / detail / export PDF — semua role (TM420 discope), wildcard setelah rute statis
    Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
    Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
    Route::get('batches/{batch}/pdf', [BatchController::class, 'pdf'])->name('batches.pdf');
});

require __DIR__.'/auth.php';

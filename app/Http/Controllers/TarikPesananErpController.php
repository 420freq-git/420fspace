<?php

namespace App\Http\Controllers;

use App\Services\ErpPesananClient;
use App\Services\MarketplaceImportService;
use Illuminate\Http\Request;

/**
 * Tarik pesanan marketplace dari ERP 420F (yang meng-import file), lalu buat Order+Sale &
 * potong stok di produksi. Memakai mesin import yang sama (alokasi FIFO + dedupe nomor_pesanan)
 * → tarik ulang aman.
 */
class TarikPesananErpController extends Controller
{
    public function __construct(private ErpPesananClient $erp, private MarketplaceImportService $importer) {}

    public function index()
    {
        return view('orders.tarik-erp', [
            'status' => $this->cekKoneksi(),
            'dariDefault' => now()->startOfMonth()->toDateString(),
            'sampaiDefault' => now()->toDateString(),
        ]);
    }

    public function tarik(Request $request)
    {
        $v = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        try {
            $rows = $this->erp->pesananMarketplace($v['dari'] ?? null, $v['sampai'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $summary = $this->importer->importDariErp($rows);

        return view('orders.tarik-erp', [
            'status' => ['ok' => true, 'pesan' => null],
            'summary' => $summary,
            'dariDefault' => $v['dari'] ?? now()->startOfMonth()->toDateString(),
            'sampaiDefault' => $v['sampai'] ?? now()->toDateString(),
        ]);
    }

    private function cekKoneksi(): array
    {
        try {
            $p = $this->erp->ping();

            return ['ok' => true, 'pesan' => ($p['app'] ?? 'ERP').' · '.($p['server_time'] ?? '')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'pesan' => $e->getMessage()];
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ErpTmPenjualanClient;
use App\Services\TarikPenjualanTmService;
use Illuminate\Http\Request;

/**
 * Tarik penjualan TM dari ERP TM420 — dasar tagihan ongkos produksi ke TM.
 *
 * Bedakan dari {@see TarikPesananErpController}, yang menarik pesanan VOOJAH
 * dari ERP 420F. Yang ini menarik penjualan brand TM420 atas barang yang
 * diproduksi di sistem ini, dan hanya yang uangnya SUDAH CAIR — karena itulah
 * pemicu TM wajib membayar.
 *
 * Setelah tertarik, pesanannya masuk mesin tagihan yang sudah ada: invoice per
 * brand, penomoran `INV.TM…`, PDF, dan tandai lunas. Tidak ada mesin tagihan
 * kedua yang harus dirawat sejajar.
 */
class TarikPenjualanTmController extends Controller
{
    public function __construct(
        private ErpTmPenjualanClient $erp,
        private TarikPenjualanTmService $penarik,
    ) {}

    public function index()
    {
        return view('orders.tarik-tm', [
            'status' => $this->cekKoneksi(),
            'dariDefault' => now()->startOfMonth()->toDateString(),
            'sampaiDefault' => now()->toDateString(),
        ]);
    }

    public function tarik(Request $request)
    {
        $v = $request->validate([
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari'],
        ]);

        try {
            $ringkas = $this->penarik->jalankan($v['dari'], $v['sampai']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return view('orders.tarik-tm', [
            'status' => ['ok' => true, 'pesan' => null],
            'ringkas' => $ringkas,
            'dariDefault' => $v['dari'],
            'sampaiDefault' => $v['sampai'],
        ]);
    }

    /** @return array{ok:bool, pesan:?string} */
    private function cekKoneksi(): array
    {
        try {
            $p = $this->erp->ping();

            return ['ok' => true, 'pesan' => 'lingkup '.implode(', ', $p['lingkup'] ?? []).' · '.($p['waktu_server'] ?? '')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'pesan' => $e->getMessage()];
        }
    }
}

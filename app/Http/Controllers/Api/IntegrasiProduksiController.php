<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penarikan;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint BACA read-only untuk ERP 420F (dijaga middleware erp.token).
 * Sumber kebenaran tetap di sini; ERP hanya mencatat angka jadi ke dompet.
 *
 * Amplop standar: { ok, message, data, errors }.
 */
class IntegrasiProduksiController extends Controller
{
    public function ping(): JsonResponse
    {
        return $this->ok(['app' => '420Frequency', 'server_time' => now()->toIso8601String()]);
    }

    /**
     * Katalog produk brand milik sendiri (VOOJAH & 420F) untuk disinkron ke ERP.
     * ERP memetakan brand via prefix SKU (VOO- → voojah, 420- → 420f) & mengisi harga jualnya
     * sendiri (harga jual marketplace tidak ada di app produksi).
     */
    public function produk(Request $request): JsonResponse
    {
        $rows = Product::query()
            ->whereHas('brand', fn ($b) => $b->where('tipe', 'milik_sendiri'))
            ->with('brand:id,kode,nama')
            ->orderBy('sku_induk')
            ->get()
            ->map(fn (Product $p) => [
                'prod_id' => $p->id,
                'sku' => $p->sku_induk,
                'nama' => $p->nama_artikel,
                'brand_kode' => $p->brand?->kode,
                'aktif' => (bool) $p->aktif,
            ]);

        return $this->ok($rows, $rows->count().' produk');
    }

    /**
     * Komisi 420F + hak Diferd per penjualan, HANYA brand eksternal (TM420 = penengah).
     * Brand milik sendiri (VOOJAH) dikecualikan: harga_tm420 null & penjualannya masuk
     * lewat Tarikan TikTok di ERP — mencegah dobel-hitung.
     */
    public function komisiDiferd(Request $request): JsonResponse
    {
        [$dari, $sampai] = $this->rentang($request);

        $rows = Sale::query()
            ->sold()->consignment()
            ->whereNotNull('harga_tm420')
            ->whereHas('brand', fn ($b) => $b->where('tipe', 'eksternal'))
            ->whereBetween('tanggal_terjual', [$dari, $sampai])
            ->with(['brand:id,nama,kode', 'order:id,nomor_pesanan'])
            ->orderBy('tanggal_terjual')->orderBy('id')
            ->get()
            ->map(fn (Sale $s) => [
                'sale_id' => $s->id,
                'tanggal' => $s->tanggal_terjual?->toDateString(),
                'brand' => $s->brand?->kode,
                'nomor_pesanan' => $s->order?->nomor_pesanan ?? $s->nomor_pesanan,
                'qty' => $s->qty,
                'harga_tm420' => $s->harga_tm420,
                'harga_diferd' => $s->harga_diferd,
                'komisi_420f' => $s->fee420f,       // qty × (harga_tm420 − harga_diferd)
                'hak_diferd' => $s->nilai_diferd,   // qty × harga_diferd
            ]);

        return $this->ok($rows, $rows->count().' penjualan');
    }

    /** Penarikan Diferd yang sudah disetujui/cair (uang keluar dari dompet Diferd di ERP). */
    public function penarikanDiferd(Request $request): JsonResponse
    {
        [$dari, $sampai] = $this->rentang($request);

        $rows = Penarikan::query()
            ->where('status', 'disetujui')
            ->whereNotNull('tanggal_cair')
            ->whereBetween('tanggal_cair', [$dari, $sampai])
            ->orderBy('tanggal_cair')->orderBy('id')
            ->get(['id', 'tanggal_cair', 'jumlah', 'catatan'])
            ->map(fn (Penarikan $p) => [
                'penarikan_id' => $p->id,
                'tanggal' => $p->tanggal_cair?->toDateString(),
                'jumlah' => (int) $p->jumlah,
                'catatan' => $p->catatan,
            ]);

        return $this->ok($rows, $rows->count().' penarikan');
    }

    /** @return array{0:string,1:string} [dari, sampai] tervalidasi. */
    private function rentang(Request $request): array
    {
        $v = $request->validate([
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari'],
        ]);

        return [$v['dari'], $v['sampai']];
    }

    private function ok(mixed $data, string $message = 'OK'): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $message, 'data' => $data, 'errors' => []]);
    }
}

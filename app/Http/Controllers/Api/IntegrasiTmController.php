<?php

namespace App\Http\Controllers\Api;

use App\Enums\BrandType;
use App\Enums\SizeTier;
use App\Enums\TahapProduksi;
use App\Http\Controllers\Controller;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Endpoint BACA read-only untuk ERP TM420 (dijaga middleware `erp.token:tm`, token TERPISAH).
 *
 * Prinsip yang DIPEGANG (permintaan pemilik TM, 10 Sep 2026):
 *  - Angka ini INFORMATIF ("apa yang akan datang" untuk gudang). BUKAN dorongan stok, tak dipakai
 *    sebagai stok. Penerimaan/lolos-reject tetap diputuskan di ERP TM (pihak penerima), bukan di sini.
 *  - TIDAK menerbitkan kewajiban apa pun. `biaya_produksi_satuan` hanya angka acuan
 *    (= ongkos disepakati / `hargaTagihan`), penagihan nyata tetap saat settlement cair.
 *  - `kode_sku` = `sku_turunan` (per ukuran) — kunci SKU bersama yang sama dgn /orders & daftar ongkos.
 *    Satu "request produksi" = satu PO (per artikel) yang dipecah menjadi beberapa baris per ukuran,
 *    jadi beberapa baris berbagi `nomor_request` yang sama.
 */
class IntegrasiTmController extends Controller
{
    /**
     * GET /api/tm420/pengiriman — surat jalan ke TM420, untuk dijadikan draft penerimaan di ERP TM.
     *
     * Parameter opsional `sejak` (Y-m-d) menyaring menurut `tanggal_kirim`; tanpa itu, 90 hari
     * terakhir. Batasnya ada supaya satu permintaan tidak diam-diam menarik seluruh riwayat lalu
     * jadi dasar penerimaan atas periode yang tidak dimaksud siapa pun.
     *
     * ## Yang dikirim, dan yang SENGAJA tidak
     *
     * Dikirim: nomor surat jalan, tanggal, kode SKU per ukuran, qty kirim, qty diterima menurut
     * catatan sini, dan ongkos produksi satuan.
     *
     * TIDAK dikirim: keputusan lolos/reject. Penerimaan di sini mencatat apa yang PABRIK serahkan;
     * penerimaan di ERP TM mencatat apa yang lolos periksa di gudang. Dua angka itu berbeda persis
     * di hari yang penting — dan reject ditanggung vendor, jadi menyalin angka pabrik berarti TM
     * membayar barang yang ia tolak sendiri. `qty_diterima` di sini informatif; ERP TM memakainya
     * sebagai usulan, bukan keputusan.
     *
     * `nomor_sj` adalah kunci anti-dobel di sisi ERP TM — pola yang sama dengan nomor invoice
     * pada buy out.
     *
     * VOOJAH dikecualikan, sama seperti `produksiBerjalan`. Barang titipan memang sampai juga ke
     * gudang TM, tapi ia tidak punya harga beli sama sekali (HPP-nya nol, dan nol itu benar);
     * mengirimkannya lewat pintu yang membawa `biaya_produksi_satuan` mengundang ERP mencatatnya
     * sebagai pembelian. Kalau kelak dibutuhkan, ia perlu pintu sendiri yang tidak berharga.
     */
    public function pengiriman(Request $request): JsonResponse
    {
        $sejak = $request->query('sejak');
        $sejak = $sejak ? Carbon::parse($sejak)->toDateString() : now()->subDays(90)->toDateString();

        $daftar = Pengiriman::query()
            ->whereDate('tanggal_kirim', '>=', $sejak)
            ->whereHas('batch.brand', fn ($br) => $br->where('tipe', BrandType::Eksternal->value))
            ->with([
                'batch:id,nomor_batch,brand_id',
                'items.product:id,brand_id,sku_induk,category_id',
                'items.product.sizes:id,product_id,ukuran,sku_turunan',
                'items.product.category.prices',
            ])
            ->orderBy('tanggal_kirim')->orderBy('id')
            ->get();

        $rows = [];

        foreach ($daftar as $sj) {
            $baris = [];

            foreach ($sj->items as $it) {
                $p = $it->product;

                if (! $p) {
                    continue;
                }

                $uk = $it->ukuran->value;
                $sku = $p->sizes->firstWhere(fn ($s) => $s->ukuran->value === $uk)?->sku_turunan;

                // Kode kosong TIDAK dikirim. Penerima tidak bisa membedakan "belum dipetakan"
                // dari "kode kebetulan kosong", dan menebaknya adalah cara barang masuk ke SKU
                // yang salah tanpa satu pun peringatan.
                if (! $sku) {
                    continue;
                }

                $baris[] = [
                    'kode_sku' => $sku,
                    'ukuran' => $uk,
                    'qty_kirim' => (int) $it->qty,
                    'qty_diterima' => $it->qty_diterima === null ? null : (int) $it->qty_diterima,
                    'biaya_produksi_satuan' => $p->hargaTagihan(SizeTier::forUkuran($uk)),
                ];
            }

            if (! $baris) {
                continue;
            }

            $rows[] = [
                'nomor_sj' => $sj->nomor_sj,
                'nomor_batch' => $sj->batch?->nomor_batch,
                'tanggal_kirim' => $sj->tanggal_kirim?->toDateString(),
                'tanggal_diterima' => $sj->tgl_diterima?->toDateString(),
                'status' => $sj->status,
                'alasan_kurang_kirim' => $sj->alasan_kurang_kirim?->value,
                'baris' => $baris,
            ];
        }

        return response()->json([
            'ok' => true,
            'message' => count($rows).' surat jalan sejak '.$sejak,
            'data' => $rows,
            'errors' => [],
        ]);
    }

    /**
     * GET /api/tm420/produksi-berjalan — produksi TM420 yang sedang berjalan (belum tiba penuh).
     * Cakupan: batch TM420 (brand eksternal) berstatus `aktif`, PO yang belum `terkirim`.
     */
    public function produksiBerjalan(StockService $stock): JsonResponse
    {
        $pos = PurchaseOrder::query()
            ->where('tahap', '!=', TahapProduksi::Terkirim->value)
            ->whereHas('batch', fn ($b) => $b->where('status', 'aktif')
                ->whereHas('brand', fn ($br) => $br->where('tipe', BrandType::Eksternal->value)))
            ->with([
                'batch:id,nomor_batch,tanggal_order,deadline_produksi,brand_id',
                'product:id,brand_id,sku_induk,category_id',
                'product.sizes:id,product_id,ukuran,sku_turunan',
                'product.category.prices',
                'sizeItems',
            ])
            ->orderBy('batch_id')->orderBy('id')
            ->get();

        $rows = [];
        foreach ($pos as $po) {
            $p = $po->product;
            if (! $p) {
                continue;
            }
            // Produksi "selesai" hanya diketahui pada level PO (tahap ≥ siap kirim); belum ada
            // penyelesaian parsial per ukuran. Sebelum siap kirim → 0; sesudah → penuh (rencana).
            $ready = $po->tahap->isReady();
            $skuBySize = $p->sizes->keyBy(fn ($s) => $s->ukuran->value);

            foreach ($po->sizeItems as $si) {
                $uk = $si->ukuran->value;
                $sku = $skuBySize[$uk]?->sku_turunan;
                if (! $sku) {
                    continue;   // tak ada pemetaan SKU untuk ukuran ini — lewati (jangan kirim kode kosong)
                }
                $qty = (int) $si->qty;

                $rows[] = [
                    'nomor_request' => $po->nomor_po,
                    'tanggal_request' => $po->batch?->tanggal_order?->toDateString(),
                    'status' => $po->tahap->value,
                    'kode_sku' => $sku,
                    'qty_dipesan' => $qty,
                    'qty_selesai' => $ready ? $qty : 0,
                    'qty_dikirim' => $stock->shippedInBatch($po->batch_id, $p->id, $uk),
                    'biaya_produksi_satuan' => $p->hargaTagihan(SizeTier::forUkuran($uk)),
                    'estimasi_selesai' => $po->batch?->deadline_produksi?->toDateString(),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'message' => count($rows).' baris produksi berjalan',
            'data' => $rows,
            'errors' => [],
        ]);
    }
}

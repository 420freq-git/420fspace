<?php

namespace App\Http\Controllers\Api;

use App\Enums\BrandType;
use App\Enums\SizeTier;
use App\Enums\TahapProduksi;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;

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

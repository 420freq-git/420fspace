<?php

namespace App\Http\Controllers;

use App\Enums\TahapProduksi;
use App\Models\Batch;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use App\Services\TahapTimelineService;

/**
 * Scorecard vendor (Diferd) — mengubah data produksi jadi alat kelola.
 *
 * Diolah dari data yang sudah ada: produksi vs terkirim vs diterima (reject & kurang/cacat),
 * durasi tiap tahap (audit log), dan ketepatan deadline produksi. Semua all-time, lintas batch.
 */
class ScorecardController extends Controller
{
    public function __construct(
        private StockService $stock,
        private TahapTimelineService $timeline,
    ) {}

    public function index()
    {
        $batches = Batch::with(['purchaseOrders.sizeItems', 'purchaseOrders.product'])->get();

        $totProduksi = $totReject = $totKurang = $totDikirim = 0;
        $perProduk = [];   // product_id => [nama, produksi, reject, kurang]

        foreach ($batches as $batch) {
            foreach ($batch->purchaseOrders as $po) {
                $pid = $po->product_id;
                $perProduk[$pid] ??= ['nama' => $po->product->nama_artikel, 'produksi' => 0, 'reject' => 0, 'kurang' => 0];

                foreach ($po->sizeItems as $si) {
                    $uk = $si->ukuran->value;
                    $prod = $this->stock->producedInBatch($batch->id, $pid, $uk);
                    $reject = $this->stock->rejectInBatch($batch->id, $pid, $uk);
                    $kurang = $this->stock->shortfallInBatch($batch->id, $pid, $uk);
                    $dikirim = $this->stock->shippedInBatch($batch->id, $pid, $uk);

                    $totProduksi += $prod; $totReject += $reject; $totKurang += $kurang; $totDikirim += $dikirim;
                    $perProduk[$pid]['produksi'] += $prod;
                    $perProduk[$pid]['reject'] += $reject;
                    $perProduk[$pid]['kurang'] += $kurang;
                }
            }
        }

        // Ranking produk paling banyak cacat (reject + kurang), yang punya cacat saja.
        $rankCacat = collect($perProduk)
            ->map(fn ($p) => $p + ['cacat' => $p['reject'] + $p['kurang']])
            ->filter(fn ($p) => $p['cacat'] > 0)
            ->sortByDesc('cacat')->values();

        // Durasi rata-rata tiap tahap (detik) dari seluruh PO.
        $stageDetik = []; $stageN = [];
        $poDurasiTotal = []; $onTime = 0; $late = 0; $adaDeadline = 0;

        foreach (PurchaseOrder::with('batch')->get() as $po) {
            $tl = $this->timeline->untuk($po);
            foreach ($tl['baris'] as $b) {
                if ($b['berjalan']) {
                    continue;   // tahap belum selesai, jangan dirata-rata
                }
                $key = $b['tahap']->value;
                $stageDetik[$key] = ($stageDetik[$key] ?? 0) + $b['detik'];
                $stageN[$key] = ($stageN[$key] ?? 0) + 1;
            }

            // Ketepatan deadline: PO selesai (terkirim) vs deadline_produksi batch.
            if ($tl['selesai'] && $po->batch?->deadline_produksi && $tl['tuntas_pada']) {
                $adaDeadline++;
                $tl['tuntas_pada']->lessThanOrEqualTo($po->batch->deadline_produksi->endOfDay()) ? $onTime++ : $late++;
            }
        }

        $tahapRata = [];
        foreach (TahapProduksi::cases() as $t) {
            if (! empty($stageN[$t->value])) {
                $tahapRata[] = [
                    'tahap' => $t,
                    'rata_detik' => (int) round($stageDetik[$t->value] / $stageN[$t->value]),
                    'n' => $stageN[$t->value],
                ];
            }
        }
        $terlamaDetik = collect($tahapRata)->max('rata_detik') ?: 1;

        // Mutu: fraksi produksi yang bebas cacat (reject + kurang). Stok yang belum dikirim
        // dianggap baik-sejauh-ini, jadi tidak menghukum kemajuan pengiriman.
        $bebasCacat = max(0, $totProduksi - $totReject - $totKurang);

        return view('scorecard.index', [
            'totProduksi' => $totProduksi,
            'totReject' => $totReject,
            'totKurang' => $totKurang,
            'totDikirim' => $totDikirim,
            'rejectRate' => $totProduksi > 0 ? round($totReject / $totProduksi * 100, 1) : 0,
            'kurangRate' => $totDikirim > 0 ? round($totKurang / $totDikirim * 100, 1) : 0,
            'lolosRate' => $totProduksi > 0 ? round($bebasCacat / $totProduksi * 100, 1) : 0,
            'rankCacat' => $rankCacat,
            'tahapRata' => $tahapRata,
            'terlamaDetik' => $terlamaDetik,
            'onTime' => $onTime,
            'late' => $late,
            'adaDeadline' => $adaDeadline,
            'onTimeRate' => $adaDeadline > 0 ? round($onTime / $adaDeadline * 100, 1) : null,
        ]);
    }
}

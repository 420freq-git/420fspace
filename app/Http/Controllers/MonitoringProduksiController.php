<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TahapProduksi;
use App\Models\Batch;
use App\Models\Pengiriman;
use App\Services\TahapTimelineService;
use Illuminate\Http\Request;

class MonitoringProduksiController extends Controller
{
    public function __construct(private \App\Services\StockService $stock) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Batch::with(['brand', 'purchaseOrders.product', 'purchaseOrders.sizeItems'])
            ->where('status', 'aktif');

        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        $batches = $query->get();
        $mepetHari = \App\Models\Setting::intVal('mepet_hari', 3);

        // Ringkas tiap batch.
        $rows = $batches->map(function ($b) use ($mepetHari) {
            $pos = $b->purchaseOrders;
            $deadlineProd = $b->deadline_produksi ?? $b->deadline;
            $sisaHari = $deadlineProd ? (int) now()->startOfDay()->diffInDays($deadlineProd, false) : null;
            $progress = $pos->isEmpty() ? 0 : (int) round($pos->avg(fn ($p) => $p->tahap->progress()));

            // "Selesai" untuk monitoring = produksi rampung DAN semua sudah dikirim/diterima:
            //   - setiap PO minimal siap_kirim (produksi tuntas), dan
            //   - tak ada qty yang masih menunggu surat jalan (produced − shipped == 0 pada PO
            //     yang belum ditutup). PO yang sudah terkirim, sisa tak-terkirimnya = reject final.
            $semuaReady = $pos->isNotEmpty() && $pos->every(fn ($p) => $p->tahap->isReady());
            $selesai = $semuaReady && $this->stock->menungguKirimBatch($b) === 0;

            $final = $selesai ? $this->infoFinal($b, $pos, $deadlineProd) : null;

            return [
                'batch' => $b,
                'deadlineProd' => $deadlineProd,
                'sisaHari' => $sisaHari,
                'progress' => $progress,
                'posTotal' => $pos->count(),
                'posReady' => $pos->filter(fn ($p) => $p->tahap->isReady())->count(),
                'telat' => $sisaHari !== null && $sisaHari < 0 && ! $selesai,
                'mepet' => $sisaHari !== null && $sisaHari >= 0 && $sisaHari <= $mepetHari && ! $selesai,
                'selesai' => $selesai,
                'final' => $final,
            ];
        });

        // Batch berjalan di ATAS (urut deadline terdekat/telat dulu), batch selesai di bawah
        // (yang terbaru rampung dulu).
        $berjalan = $rows->where('selesai', false)
            ->sortBy(fn ($r) => $r['sisaHari'] ?? PHP_INT_MAX)->values();
        $tuntas = $rows->where('selesai', true)
            ->sortByDesc(fn ($r) => optional($r['final']['selesaiPada'] ?? null)->timestamp)->values();
        $rows = $berjalan->concat($tuntas)->values();

        $allPos = $batches->flatMap->purchaseOrders;

        $funnel = collect(TahapProduksi::cases())->map(fn ($t) => [
            'tahap' => $t,
            'count' => $allPos->filter(fn ($p) => $p->tahap === $t)->count(),
        ]);
        $maxFunnel = max(1, $funnel->max('count'));

        return view('produksi.monitoring', [
            'rows' => $rows,
            'funnel' => $funnel,
            'maxFunnel' => $maxFunnel,
            'stats' => [
                'batchAktif' => $rows->count(),
                'batchBerjalan' => $berjalan->count(),
                'poProduksi' => $allPos->filter(fn ($p) => ! $p->tahap->isReady())->count(),
                'poReady' => $allPos->filter(fn ($p) => $p->tahap->isReady())->count(),
                'batchTelat' => $rows->where('telat', true)->count(),
            ],
            'canUpdate' => $user->isAdmin() || $user->role === Role::Diferd,
        ]);
    }

    /**
     * Info final batch selesai: total & rincian reject, lama pengerjaan, dan apakah lewat deadline.
     * @return array{rejectPcs:int, rejectProduk:array, durasi:string, selesaiPada:?\Illuminate\Support\Carbon, lewatDeadline:bool}
     */
    private function infoFinal(Batch $b, $pos, $deadlineProd): array
    {
        // Reject = qty PO − qty diterima, hanya pada PO yang sudah ditutup (terkirim).
        $rejectPcs = 0;
        $rejectProduk = [];
        $kombinasi = [];
        foreach ($pos as $po) {
            if ($po->tahap !== TahapProduksi::Terkirim) {
                continue;
            }
            foreach ($po->sizeItems as $si) {
                $kombinasi[$po->product_id.'|'.$si->ukuran->value] = [$po, $si->ukuran->value];
            }
        }
        foreach ($kombinasi as [$po, $uk]) {
            $kurang = max(0, $this->stock->producedInBatch($b->id, $po->product_id, $uk)
                - $this->stock->receivedInBatch($b->id, $po->product_id, $uk));
            if ($kurang > 0) {
                $rejectPcs += $kurang;
                $nama = $po->product->nama_artikel ?? 'Produk #'.$po->product_id;
                $rejectProduk[$nama] = ($rejectProduk[$nama] ?? 0) + $kurang;
            }
        }

        // Selesai pada = terima surat jalan terakhir; kalau masih di jalan (dikirim), pakai kirim terakhir.
        $selesaiPada = Pengiriman::where('batch_id', $b->id)->where('status', 'diterima')->max('tgl_diterima')
            ?? Pengiriman::where('batch_id', $b->id)->max('tanggal_kirim');
        $selesaiPada = $selesaiPada ? \Illuminate\Support\Carbon::parse($selesaiPada) : null;

        $mulai = $pos->min(fn ($p) => $p->created_at) ?? $b->tanggal_order;
        $mulai = $mulai ? \Illuminate\Support\Carbon::parse($mulai) : null;

        $durasi = ($mulai && $selesaiPada)
            ? TahapTimelineService::durasi((int) abs($mulai->diffInSeconds($selesaiPada)))
            : '—';

        $lewatDeadline = $deadlineProd && $selesaiPada
            && $selesaiPada->startOfDay()->greaterThan(\Illuminate\Support\Carbon::parse($deadlineProd)->startOfDay());

        arsort($rejectProduk);

        return [
            'rejectPcs' => $rejectPcs,
            'rejectProduk' => $rejectProduk,
            'durasi' => $durasi,
            'selesaiPada' => $selesaiPada,
            'lewatDeadline' => (bool) $lewatDeadline,
        ];
    }
}

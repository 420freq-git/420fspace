<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TahapProduksi;
use App\Models\Batch;
use Illuminate\Http\Request;

class MonitoringProduksiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Batch::with(['brand', 'purchaseOrders.product'])
            ->where('status', 'aktif');

        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        $batches = $query->get()
            ->sortBy(fn ($b) => optional($b->deadline_produksi ?? $b->deadline)->timestamp)
            ->values();

        $mepetHari = \App\Models\Setting::intVal('mepet_hari', 3);

        // Ringkas tiap batch.
        $rows = $batches->map(function ($b) use ($mepetHari) {
            $pos = $b->purchaseOrders;
            $deadlineProd = $b->deadline_produksi ?? $b->deadline;
            $sisaHari = $deadlineProd ? (int) now()->startOfDay()->diffInDays($deadlineProd, false) : null;
            $progress = $pos->isEmpty() ? 0 : (int) round($pos->avg(fn ($p) => $p->tahap->progress()));
            $selesai = $progress >= 100;

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
            ];
        });

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
                'poProduksi' => $allPos->filter(fn ($p) => ! $p->tahap->isReady())->count(),
                'poReady' => $allPos->filter(fn ($p) => $p->tahap->isReady())->count(),
                'batchTelat' => $rows->where('telat', true)->count(),
            ],
            'canUpdate' => $user->isAdmin() || $user->role === Role::Diferd,
        ]);
    }
}

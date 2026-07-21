<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Setting;
use App\Services\SettlementService;
use Illuminate\Http\Request;

/**
 * Radar deadline pelunasan & paparan buy-out.
 *
 * Model konsinyasi: stok yang tak laku sampai deadline (1 tahun) di-buy-out — TM bayar lewat 420F
 * ke Diferd. Halaman ini menyorot batch yang mendekati deadline dengan sisa stok belum terjual,
 * beserta nilai paparannya (sisa × harga Diferd), supaya bisa didorong jual atau disiapkan dananya
 * SEBELUM jatuh tempo — bukan jadi kejutan.
 */
class RadarController extends Controller
{
    public function __construct(private SettlementService $settlement) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Batch::with('brand')->where('status', BatchStatus::Aktif->value);
        // Brand user hanya lihat batch brand-nya; buy-out adalah risiko finansial mereka.
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        $ambangMepetHari = 60;   // batch dianggap "mendekati" bila deadline ≤ 60 hari lagi

        $rows = $query->get()->map(function ($batch) use ($ambangMepetHari) {
            $sisa = $this->settlement->sisaStok($batch);
            $hariLagi = (int) round(now()->startOfDay()->diffInDays($batch->deadline, false));

            $status = $hariLagi < 0 ? 'lewat' : ($hariLagi <= $ambangMepetHari ? 'mepet' : 'aman');

            return [
                'batch' => $batch,
                'deadline' => $batch->deadline,
                'hari_lagi' => $hariLagi,
                'sisa_pcs' => $sisa['pcs'],
                'paparan' => $sisa['nilai'],
                'status' => $status,
            ];
        })
            // Hanya batch yang masih punya sisa stok (ada paparan) yang relevan.
            ->filter(fn ($r) => $r['sisa_pcs'] > 0)
            ->sortBy('hari_lagi')   // paling mendesak di atas
            ->values();

        return view('radar.index', [
            'rows' => $rows,
            'totalPaparan' => (int) $rows->sum('paparan'),
            'totalSisa' => (int) $rows->sum('sisa_pcs'),
            'mepetCount' => $rows->whereIn('status', ['mepet', 'lewat'])->count(),
            'ambang' => $ambangMepetHari,
        ]);
    }
}

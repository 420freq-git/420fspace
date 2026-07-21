<?php

namespace App\Http\Controllers;

use App\Enums\Marketplace;
use App\Enums\Role;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Analisis per channel marketplace — Shopee / TikTok / WhatsApp / Web.
 * Membantu memutuskan fokus channel: mana yang paling banyak jual, nilai terbesar, retur terbanyak.
 */
class ChannelController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $brandId = in_array($user->role, [Role::Tm420, Role::Voojah], true) ? $user->brand_id : null;

        // Penjualan lunas per channel (yang benar-benar jadi omzet).
        $agg = Sale::whereHas('order', fn ($o) => $o->where('status', 'lunas'))
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->selectRaw('marketplace, COUNT(DISTINCT nomor_pesanan) AS pesanan, SUM(qty) AS qty, SUM(qty * harga_tm420) AS omzet')
            ->groupBy('marketplace')->get()->keyBy('marketplace');

        // Retur rusak per channel.
        $retur = Sale::where('kondisi_retur', 'rusak')
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->selectRaw('marketplace, SUM(qty) AS qty')->groupBy('marketplace')->get()->keyBy('marketplace');

        $rows = [];
        $totQty = (int) $agg->sum('qty');
        foreach (Marketplace::cases() as $mp) {
            $a = $agg->get($mp->value);
            $qty = (int) ($a->qty ?? 0);
            if ($qty === 0 && ! $a) {
                // tetap tampilkan channel kosong agar terlihat mana yang belum dipakai
            }
            $rows[] = [
                'label' => $mp->label(),
                'pesanan' => (int) ($a->pesanan ?? 0),
                'qty' => $qty,
                'omzet' => (int) ($a->omzet ?? 0),
                'retur' => (int) ($retur->get($mp->value)->qty ?? 0),
                'porsi' => $totQty > 0 ? round($qty / $totQty * 100, 1) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        return view('channel.index', [
            'rows' => $rows,
            'totalQty' => $totQty,
            'totalOmzet' => (int) $agg->sum('omzet'),
            'totalPesanan' => (int) $agg->sum('pesanan'),
            'terbaik' => collect($rows)->firstWhere('qty', '>', 0)['label'] ?? null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\AlasanSelisih;
use App\Enums\Role;
use App\Enums\SizeTier;
use App\Enums\TahapProduksi;
use App\Enums\Ukuran;
use App\Models\Batch;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PengirimanController extends Controller
{
    public function __construct(private \App\Services\StockService $stock) {}

    public function index(Request $request)
    {
        $query = Pengiriman::with(['batch.brand', 'items'])->latest('tanggal_kirim')->latest('id');
        $this->scope($query, $request);

        return view('pengiriman.index', ['pengiriman' => $query->get()]);
    }

    public function create(Request $request)
    {
        $batchQuery = Batch::with('brand')->latest('tanggal_order');
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $batchQuery->where('brand_id', $user->brand_id);
        }
        $batches = $batchQuery->get();

        // Urutan ukuran S→XXL untuk tampilan.
        $urut = array_flip(array_map(fn ($u) => $u->value, Ukuran::cases()));

        // Peta artikel yang SIAP dikirim tiap batch (untuk prefill surat jalan).
        // Dua penyaring penting:
        //  1. hanya PO yang tahap produksinya siap_kirim — sebelumnya PO yang masih QC pun bisa dikirim;
        //  2. qty dikurangi yang sudah pernah dibuatkan surat jalan — sebelumnya artikel yang sudah
        //     dikirim tetap muncul penuh dan bisa dikirim ulang.
        $producedMap = [];
        foreach ($batches as $b) {
            $rows = DB::table('po_size_items as psi')
                ->join('purchase_orders as po', 'psi.purchase_order_id', '=', 'po.id')
                ->join('products as pr', 'po.product_id', '=', 'pr.id')
                ->leftJoin('categories as c', 'pr.category_id', '=', 'c.id')
                ->where('po.batch_id', $b->id)
                ->where('po.tahap', TahapProduksi::SiapKirim->value)
                ->groupBy('po.product_id', 'psi.ukuran', 'pr.nama_artikel', 'c.nama')
                ->selectRaw('po.product_id as product_id, pr.nama_artikel as nama, c.nama as kategori, psi.ukuran as ukuran, SUM(psi.qty) as qty')
                ->get();

            $producedMap[$b->id] = collect($rows)->groupBy('product_id')->map(function ($g) use ($b, $urut) {
                $sizes = $g->sortBy(fn ($r) => $urut[$r->ukuran] ?? 99)
                    ->map(fn ($r) => [
                        'ukuran' => $r->ukuran,
                        'qty' => max(0, (int) $r->qty - $this->stock->shippedInBatch($b->id, (int) $r->product_id, $r->ukuran)),
                    ])
                    ->filter(fn ($s) => $s['qty'] > 0)
                    ->values();

                return [
                    'product_id' => (int) $g->first()->product_id,
                    'nama' => $g->first()->nama,
                    'kategori' => $g->first()->kategori ?? '—',
                    'sizes' => $sizes,
                    'total' => (int) $sizes->sum('qty'),
                ];
            })
                ->filter(fn ($a) => $a['total'] > 0)   // artikel yang sudah terkirim penuh: sembunyikan
                ->sortBy('nama')->values();
        }

        return view('pengiriman.create', [
            'batches' => $batches,
            'producedMap' => $producedMap,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'tanggal_kirim' => ['required', 'date'],
            'ekspedisi' => ['nullable', 'string', 'max:255'],
            'resi' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.ukuran' => ['required', new Enum(Ukuran::class)],
            'items.*.qty' => ['required', 'integer', 'min:0'],
            'alasan_kurang_kirim' => ['nullable', Rule::in(array_column(AlasanSelisih::untukKirim(), 'value'))],
        ]);

        // Buang baris qty 0 (ukuran yang tidak ikut dikirim).
        $data['items'] = array_values(array_filter($data['items'], fn ($i) => (int) $i['qty'] > 0));
        if (empty($data['items'])) {
            return back()->withInput()->with('error', 'Isi minimal satu ukuran dengan qty lebih dari 0.');
        }

        $batch = Batch::findOrFail($data['batch_id']);

        // Penjaga sisi server — form sudah menyaring, tapi qty/produk bisa dikirim manual.
        $tahapSiap = PurchaseOrder::where('batch_id', $batch->id)
            ->where('tahap', TahapProduksi::SiapKirim->value)
            ->pluck('product_id')->all();

        foreach ($data['items'] as $item) {
            $pid = (int) $item['product_id'];
            $uk = $item['ukuran'];

            if (! in_array($pid, $tahapSiap, true)) {
                $nama = \App\Models\Product::find($pid)?->nama_artikel ?? "produk #{$pid}";

                return back()->withInput()->with('error', "{$nama} belum berstatus siap kirim — selesaikan tahap produksinya dulu.");
            }

            $sisa = $this->stock->producedInBatch($batch->id, $pid, $uk) - $this->stock->shippedInBatch($batch->id, $pid, $uk);
            if ((int) $item['qty'] > $sisa) {
                $nama = \App\Models\Product::find($pid)?->nama_artikel ?? "produk #{$pid}";

                return back()->withInput()->with('error', "{$nama} ukuran {$uk}: sisa yang belum dikirim hanya {$sisa} pcs.");
            }
        }

        // Kalau total kirim kurang dari sisa PO, alasannya wajib — vendor sudah ditanya di form,
        // tapi form bisa dilewati, jadi diperiksa ulang di sini.
        $sisaPo = $this->sisaPoBatch($batch->id);
        $totalKirim = array_sum(array_column($data['items'], 'qty'));

        if ($totalKirim < $sisaPo && empty($data['alasan_kurang_kirim'])) {
            return back()->withInput()->with('error',
                'Pengiriman kurang '.($sisaPo - $totalKirim).' pcs dari PO — pilih dulu alasannya (reject atau produksi kurang).');
        }

        DB::transaction(function () use ($data, $batch, $totalKirim, $sisaPo) {
            $sj = Pengiriman::create([
                'batch_id' => $batch->id,
                'nomor_sj' => $this->generateNomor($batch),
                'tanggal_kirim' => $data['tanggal_kirim'],
                'ekspedisi' => $data['ekspedisi'] ?? null,
                'resi' => $data['resi'] ?? null,
                'status' => 'dikirim',
                'catatan' => $data['catatan'] ?? null,
                'alasan_kurang_kirim' => $totalKirim < $sisaPo ? $data['alasan_kurang_kirim'] : null,
            ]);

            foreach ($data['items'] as $item) {
                $sj->items()->create([
                    'product_id' => $item['product_id'],
                    'ukuran' => $item['ukuran'],
                    'qty' => $item['qty'],
                ]);
            }
        });

        return redirect()->route('pengiriman.index')->with('success', 'Surat jalan dibuat.');
    }

    public function show(Request $request, Pengiriman $pengiriman)
    {
        $this->authorizeView($request, $pengiriman);

        $user = $request->user();
        $pengiriman->load(['batch.brand', 'items.product']);

        // Nilai kekurangan/cacat (ditanggung vendor) = Σ selisih × harga_diferd.
        $kerugianVendor = (int) $pengiriman->items->sum(function ($it) {
            $short = max(0, $it->qty - ($it->qty_diterima ?? $it->qty));

            return $short > 0 ? $short * ($it->product->effectiveDiferd(SizeTier::forUkuran($it->ukuran->value)) ?? 0) : 0;
        });

        return view('pengiriman.show', [
            'sj' => $pengiriman,
            'canManage' => $user->isAdmin() || $user->role === Role::Diferd,
            'canReceive' => $user->isAdmin() || in_array($user->role, [Role::Tm420, Role::Voojah], true),
            'kerugianVendor' => $kerugianVendor,
        ]);
    }

    /** Brand/420F konfirmasi barang diterima — dengan qty nyata per item. */
    public function terima(Request $request, Pengiriman $pengiriman)
    {
        $this->authorizeView($request, $pengiriman);

        if ($pengiriman->isDiterima()) {
            return back()->with('error', 'Surat jalan ini sudah ditandai diterima.');
        }

        $data = $request->validate([
            'diterima' => ['required', 'array'],
            'diterima.*' => ['required', 'integer', 'min:0'],
            'alasan_kurang_terima' => ['nullable', Rule::in(array_column(AlasanSelisih::untukTerima(), 'value'))],
            'catatan_selisih_terima' => ['nullable', 'string', 'max:255'],
        ]);

        $pengiriman->load('items');

        // Diterima TIDAK BOLEH melebihi yang dikirim. Tanpa penjaga ini, angka penerimaan
        // mengarang stok yang tak pernah diproduksi (stok jual = diterima − terjual), dan tiap
        // penjualannya menciptakan hak Diferd yang harus dibayar 420F. Jangan andalkan atribut
        // `max` di form — sisi server adalah penentu.
        $lebih = $pengiriman->items->filter(
            fn ($item) => array_key_exists($item->id, $data['diterima'])
                && (int) $data['diterima'][$item->id] > (int) $item->qty
        );
        if ($lebih->isNotEmpty()) {
            $contoh = $lebih->first();

            return back()->with('error',
                'Jumlah diterima tidak boleh melebihi yang dikirim (mis. '
                .($contoh->product->nama_artikel ?? 'artikel').' '.$contoh->ukuran->value
                .': dikirim '.$contoh->qty.' pcs). Perbaiki dulu angkanya.');
        }

        // Kurang dari yang dikirim → alasannya wajib, sama seperti di sisi vendor.
        $kurang = $pengiriman->items->sum(function ($item) use ($data) {
            $diterima = array_key_exists($item->id, $data['diterima'])
                ? (int) $data['diterima'][$item->id] : $item->qty;

            return max(0, $item->qty - $diterima);
        });

        if ($kurang > 0 && empty($data['alasan_kurang_terima'])) {
            return back()->with('error',
                "Penerimaan kurang {$kurang} pcs dari yang dikirim — pilih dulu alasannya (reject atau tidak ada di paket).");
        }

        DB::transaction(function () use ($pengiriman, $data, $kurang) {
            foreach ($pengiriman->items as $item) {
                // Klop = biarkan sesuai qty dikirim; kalau tidak diisi, anggap sama.
                $diterima = array_key_exists($item->id, $data['diterima'])
                    ? (int) $data['diterima'][$item->id] : $item->qty;
                $item->update(['qty_diterima' => $diterima]);
            }
            $pengiriman->update([
                'status' => 'diterima',
                'tgl_diterima' => now(),
                'alasan_kurang_terima' => $kurang > 0 ? $data['alasan_kurang_terima'] : null,
                'catatan_selisih_terima' => $kurang > 0 ? ($data['catatan_selisih_terima'] ?? null) : null,
            ]);

            $this->tandaiTerkirim($pengiriman);
        });

        $msg = $pengiriman->fresh('items')->adaSelisih()
            ? 'Penerimaan dikonfirmasi — ada selisih jumlah, tercatat.'
            : 'Penerimaan dikonfirmasi, semua sesuai (klop).';

        return back()->with('success', $msg);
    }

    public function pdf(Request $request, Pengiriman $pengiriman)
    {
        $this->authorizeView($request, $pengiriman);
        $pengiriman->load(['batch.brand', 'items.product.category']);

        $urut = array_flip(array_map(fn ($u) => $u->value, Ukuran::cases()));

        $byArtikel = $pengiriman->items->groupBy('product_id')->map(fn ($g) => [
            'product' => $g->first()->product,
            'kategori' => $g->first()->product->category->nama ?? 'Tanpa kategori',
            'sizes' => $g->sortBy(fn ($i) => $urut[$i->ukuran->value] ?? 99)->values(),
            'total' => (int) $g->sum('qty'),
            'total_diterima' => (int) $g->sum('qty_diterima'),
        ])->sortBy(fn ($a) => $a['product']->nama_artikel)->values();

        $byKategori = $byArtikel->groupBy('kategori')->map(fn ($g) => [
            'artikels' => $g->values(),
            'total' => (int) $g->sum('total'),
        ]);

        // Penerima: brand milik sendiri (VOOJAH) diterima 420F; brand eksternal diterima brand-nya.
        $brand = $pengiriman->batch->brand;
        $penerima = $brand->tipe === \App\Enums\BrandType::MilikSendiri ? '420Frequency' : $brand->nama;

        $pdf = Pdf::loadView('pengiriman.pdf', [
            'sj' => $pengiriman,
            'byArtikel' => $byArtikel,
            'byKategori' => $byKategori,
            'penerima' => $penerima,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('SURAT-JALAN-'.$pengiriman->nomor_sj.'.pdf');
    }

    public function destroy(Pengiriman $pengiriman)
    {
        if ($pengiriman->isDiterima()) {
            return back()->with('error', 'Surat jalan yang sudah diterima tidak bisa dihapus.');
        }

        $pengiriman->delete();

        return redirect()->route('pengiriman.index')->with('success', 'Surat jalan dihapus.');
    }

    // ----- helpers -----

    /** Total qty PO batch yang belum dibuatkan surat jalan (hanya PO bertahap siap kirim). */
    private function sisaPoBatch(int $batchId): int
    {
        $pos = PurchaseOrder::with('sizeItems')
            ->where('batch_id', $batchId)
            ->where('tahap', TahapProduksi::SiapKirim->value)
            ->get();

        // Dedupe produk+ukuran: producedInBatch sudah menjumlah seluruh PO produk itu di batch,
        // jadi kombinasi yang sama tidak boleh dihitung dua kali.
        $kombinasi = [];
        foreach ($pos as $po) {
            foreach ($po->sizeItems as $si) {
                $kombinasi[$po->product_id.'|'.$si->ukuran->value] = [$po->product_id, $si->ukuran->value];
            }
        }

        $total = 0;
        foreach ($kombinasi as [$productId, $uk]) {
            $total += max(0, $this->stock->producedInBatch($batchId, $productId, $uk)
                - $this->stock->shippedInBatch($batchId, $productId, $uk));
        }

        return $total;
    }

    /**
     * Barang sampai di brand → PO ditutup (tahap terkirim), tanpa syarat harus terkirim penuh.
     * Dalam alur produksi tidak ada sisa menganggur: qty yang tidak ikut dikirim berarti gagal QC,
     * dan sejak PO ditutup selisih itu terbaca sebagai reject produksi — bukan stok yang menyusul.
     */
    private function tandaiTerkirim(Pengiriman $pengiriman): void
    {
        $pos = PurchaseOrder::where('batch_id', $pengiriman->batch_id)
            ->whereIn('product_id', $pengiriman->items->pluck('product_id')->unique())
            ->where('tahap', '!=', TahapProduksi::Terkirim->value)
            ->get();

        // Update per-model (bukan mass update) supaya transisi tahap → terkirim TEREKAM di audit
        // log. Riwayat produksi dibangun dari audit log; mass update mem-bypass event Eloquent
        // sehingga dulu kolom tahap berubah tapi history mentok di "siap kirim".
        foreach ($pos as $po) {
            $po->update([
                'tahap' => TahapProduksi::Terkirim->value,
                'tahap_updated_at' => now(),
            ]);
        }
    }

    private function scope($query, Request $request): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->whereHas('batch', fn ($q) => $q->where('brand_id', $user->brand_id));
        }
    }

    private function authorizeView(Request $request, Pengiriman $pengiriman): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id && $pengiriman->batch->brand_id !== $user->brand_id) {
            abort(403, 'Surat jalan ini bukan milik brand Anda.');
        }
    }

    private function generateNomor(Batch $batch): string
    {
        $base = 'SJ.'.BatchController::brandKode($batch->brand).'.'.now()->format('m.y').'.';
        $n = Pengiriman::whereYear('created_at', now()->year)->count() + 1;
        do {
            $nomor = $base.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $n++;
        } while (Pengiriman::where('nomor_sj', $nomor)->exists());

        return $nomor;
    }
}

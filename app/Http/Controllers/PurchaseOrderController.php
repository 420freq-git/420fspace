<?php

namespace App\Http\Controllers;

use App\Enums\JenisProduksi;
use App\Enums\ProduksiStatus;
use App\Enums\Role;
use App\Enums\TahapProduksi;
use App\Enums\Ukuran;
use App\Models\Batch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PurchaseOrderController extends Controller
{
    private array $specStrings = [
        'patrun', 'ukuran_rib', 'ukuran_rib_lengan', 'warna_bahan', 'jenis_bahan', 'supp_bahan',
        'cat_sablon', 'finishing', 'desain_depan', 'desain_belakang', 'desain_lengan', 'note',
    ];

    private array $specBools = [
        'label_leher', 'label_bawah', 'slip_label', 'aksesoris', 'care_label', 'hangtag', 'plastik',
    ];

    /**
     * Siapa boleh menyusun daftar artikel batch ini.
     *
     * TM420 menyusunnya selama batch belum disetujui — itu justru isi yang direview 420F.
     * Setelah disetujui, daftar dibekukan untuk TM: kalau masih bisa diubah, persetujuan tadi
     * tidak menjamin apa pun dan vendor bisa mengerjakan artikel yang tak pernah disetujui.
     */
    private function pastikanBolehKelola(Request $request, Batch $batch): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        if (!in_array($user->role, [Role::Tm420, Role::Voojah], true)) {
            abort(403, 'Anda tidak berhak mengubah PO batch ini.');
        }
        if ($user->brand_id && $batch->brand_id !== $user->brand_id) {
            abort(403, 'Batch ini bukan milik brand Anda.');
        }
        if (! $batch->status->belumDisetujui()) {
            abort(403, 'Batch sudah disetujui — daftar artikel tidak bisa diubah lagi. Hubungi 420F.');
        }
    }

    public function create(Request $request, Batch $batch)
    {
        $this->pastikanBolehKelola($request, $batch);

        return view('purchase_orders.create', [
            'batch' => $batch,
            'po' => new PurchaseOrder(),
            'products' => $this->brandProducts($batch),
        ]);
    }

    public function store(Request $request, Batch $batch)
    {
        $this->pastikanBolehKelola($request, $batch);
        $data = $this->validated($request, $batch);

        $po = $batch->purchaseOrders()->create(array_merge(
            $this->specData($request),
            [
                'product_id' => $data['product_id'],
                'nomor_po' => $this->generatePoNumber($batch),
                'status_produksi' => ProduksiStatus::Antri->value,
                'tahap' => TahapProduksi::BelanjaBahan->value,
                'tahap_updated_at' => now(),
            ],
        ));

        $this->saveSizeItems($request, $po);

        return redirect()->route('batches.show', $batch)->with('success', 'PO ditambahkan.');
    }

    public function edit(Request $request, Batch $batch, PurchaseOrder $purchaseOrder)
    {
        $this->pastikanBolehKelola($request, $batch);
        $purchaseOrder->load('sizeItems');

        return view('purchase_orders.edit', [
            'batch' => $batch,
            'po' => $purchaseOrder,
            'products' => $this->brandProducts($batch),
        ]);
    }

    public function update(Request $request, Batch $batch, PurchaseOrder $purchaseOrder)
    {
        $this->pastikanBolehKelola($request, $batch);
        $data = $this->validated($request, $batch);

        $purchaseOrder->update(array_merge(
            $this->specData($request),
            ['product_id' => $data['product_id']],
        ));

        $purchaseOrder->sizeItems()->delete();
        $this->saveSizeItems($request, $purchaseOrder);

        return redirect()->route('batches.show', $batch)->with('success', 'PO diperbarui.');
    }

    public function destroy(Request $request, Batch $batch, PurchaseOrder $purchaseOrder)
    {
        $this->pastikanBolehKelola($request, $batch);
        $purchaseOrder->delete();

        return redirect()->route('batches.show', $batch)->with('success', 'PO dihapus.');
    }

    /** Update tahap produksi (Diferd / admin). */
    public function updateStatus(Request $request, Batch $batch, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'tahap' => ['required', new Enum(TahapProduksi::class)],
        ]);

        if ($purchaseOrder->tahap?->value !== $data['tahap']) {
            $purchaseOrder->update([
                'tahap' => $data['tahap'],
                'tahap_updated_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json($this->ringkasanTahap($request, $batch, $purchaseOrder));
        }

        return back()->with('success', 'Tahap produksi diperbarui.');
    }

    /**
     * Bahan untuk memperbarui halaman TANPA reload setelah tahap diubah (lihat resources/js/app.js).
     * Baris PO dirender ulang di server supaya aturan tahap (stepper, badge, "mandek", tuntas)
     * tetap satu sumber di PHP dan tidak diduplikasi di JavaScript.
     *
     * `selesai` dipakai klien untuk mendeteksi perubahan STRUKTURAL: batch yang jadi/batal
     * "selesai" harus pindah bagian di halaman monitoring — itu tak bisa ditambal per-baris,
     * jadi klien memuat ulang halaman hanya untuk kasus jarang ini.
     */
    private function ringkasanTahap(Request $request, Batch $batch, PurchaseOrder $purchaseOrder): array
    {
        $stock = app(\App\Services\StockService::class);

        $batch->load(['purchaseOrders.product', 'purchaseOrders.sizeItems']);
        $pos = $batch->purchaseOrders;

        $semuaReady = $pos->isNotEmpty() && $pos->every(fn ($p) => $p->tahap->isReady());

        $canUpdate = $request->user()->isAdmin() || $request->user()->role === Role::Diferd;

        return [
            'ok' => true,
            'message' => 'Tahap produksi diperbarui.',
            'po_id' => $purchaseOrder->id,
            'batch_id' => $batch->id,
            'progress' => $pos->isEmpty() ? 0 : (int) round($pos->avg(fn ($p) => $p->tahap->progress())),
            'selesai' => $semuaReady && $stock->menungguKirimBatch($batch) === 0,
            'row_html' => view('produksi._po_row', [
                'batch' => $batch,
                'po' => $purchaseOrder->fresh('product'),
                'canUpdate' => $canUpdate,
            ])->render(),
        ];
    }

    /** Catatan vendor → brand (kendala teknis, dsb). */
    public function updateCatatan(Request $request, Batch $batch, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'catatan_vendor' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchaseOrder->update(['catatan_vendor' => $data['catatan_vendor'] ?: null]);

        return back()->with('success', 'Catatan produksi disimpan.');
    }

    /** Riwayat durasi tiap tahap produksi — diolah dari audit log, tanpa tabel tambahan. */
    public function riwayat(Request $request, Batch $batch, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->batch_id === $batch->id, 404);

        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id && $batch->brand_id !== $user->brand_id) {
            abort(403, 'Batch ini bukan milik brand Anda.');
        }
        if ($user->role === Role::Diferd && $batch->status->belumDisetujui()) {
            abort(403, 'Batch ini belum disetujui 420F.');
        }

        $purchaseOrder->load('product');

        return view('purchase_orders.riwayat', [
            'batch' => $batch,
            'po' => $purchaseOrder,
            'timeline' => app(\App\Services\TahapTimelineService::class)->untuk($purchaseOrder),
        ]);
    }

    // ----- helpers -----

    private function brandProducts(Batch $batch)
    {
        return Product::with(['spec', 'category'])->where('brand_id', $batch->brand_id)
            ->where('aktif', true)->orderBy('nama_artikel')->get();
    }

    private function specData(Request $request): array
    {
        $data = [];
        foreach ($this->specStrings as $f) {
            $data[$f] = $request->input($f) ?: null;
        }
        foreach ($this->specBools as $f) {
            $data[$f] = $request->boolean($f);
        }

        return $data;
    }

    private function saveSizeItems(Request $request, PurchaseOrder $po): void
    {
        // Jenis produksi ditentukan otomatis dari kategori artikel (bukan diinput manual),
        // supaya tak salah isi (mis. "pendek" untuk longsleeve). Satu qty per ukuran.
        $jenis = $po->product?->category?->jenisProduksi() ?? JenisProduksi::Pendek;

        foreach (Ukuran::cases() as $u) {
            $val = $request->input("qty.{$u->value}", 0);
            // Toleran terhadap format lama (qty[ukuran][jenis]) — jumlahkan bila array.
            $qty = is_array($val) ? array_sum(array_map('intval', $val)) : (int) $val;
            if ($qty > 0) {
                $po->sizeItems()->create([
                    'ukuran' => $u->value,
                    'jenis' => $jenis->value,
                    'qty' => $qty,
                ]);
            }
        }
    }

    private function generatePoNumber(Batch $batch): string
    {
        $prefix = 'PO.'.BatchController::brandKode($batch->brand).'.'.$batch->tanggal_order->format('m.y').'.';
        $n = $batch->purchaseOrders()->count() + 1;
        do {
            $nomor = $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $n++;
        } while (PurchaseOrder::where('nomor_po', $nomor)->exists());

        return $nomor;
    }

    private function validated(Request $request, Batch $batch): array
    {
        return $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('brand_id', $batch->brand_id),
            ],
            'qty' => ['nullable', 'array'],
        ]);
    }
}

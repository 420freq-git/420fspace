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
        'patrun', 'ukuran_rib', 'warna_bahan', 'jenis_bahan', 'supp_bahan', 'warna_benang',
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

        return back()->with('success', 'Tahap produksi diperbarui.');
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
        foreach (Ukuran::cases() as $u) {
            foreach (JenisProduksi::cases() as $j) {
                $qty = (int) $request->input("qty.{$u->value}.{$j->value}", 0);
                if ($qty > 0) {
                    $po->sizeItems()->create([
                        'ukuran' => $u->value,
                        'jenis' => $j->value,
                        'qty' => $qty,
                    ]);
                }
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
            'qty.*.*' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}

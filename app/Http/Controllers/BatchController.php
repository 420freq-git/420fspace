<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\JenisOrder;
use App\Enums\Role;
use App\Enums\TypePayment;
use App\Models\Batch;
use App\Models\Brand;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        // Tandai batch tuntas (produksi selesai + Diferd lunas + stok habis) jadi Lunas otomatis.
        $user = $request->user();
        $brandId = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;
        app(\App\Services\SettlementService::class)->reconcileLunas($brandId);

        $query = Batch::with(['brand', 'purchaseOrders.sizeItems'])->latest('tanggal_order');
        $this->scope($query, $request);
        $batches = $query->get();

        // Progres produksi per batch (diterima/terkirim dari diproduksi) — data produksi, aman
        // dilihat semua peran termasuk TM (bukan angka uang Diferd).
        $stock = app(\App\Services\StockService::class);
        $progres = $batches->mapWithKeys(fn ($b) => [$b->id => [
            'diproduksi' => (int) $b->purchaseOrders->sum(fn ($po) => $po->sizeItems->sum('qty')),
            'terkirim' => $stock->pergerakanBatch($b)['diterima'],
        ]]);

        return view('batches.index', ['batches' => $batches, 'progres' => $progres]);
    }

    public function create()
    {
        return view('batches.create', [
            'batch' => new Batch(['tanggal_order' => now(), 'status' => \App\Enums\BatchStatus::Aktif]),
            'brands' => Brand::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $brand = Brand::findOrFail($data['brand_id']);
        $tanggal = Carbon::parse($data['tanggal_order']);
        $user = $request->user();

        // Batch dari TM420 masuk antrean persetujuan 420F dulu; 420F tidak menyetujui dirinya sendiri.
        $perluApproval = in_array($user->role, [Role::Tm420, Role::Voojah], true);

        $batch = Batch::create([
            'brand_id' => $brand->id,
            'nomor_batch' => $this->generateBatchNumber($brand, $tanggal),
            'tanggal_order' => $tanggal,
            'deadline' => $tanggal->copy()->addYear(),
            'deadline_produksi' => $data['deadline_produksi'] ?? null,
            'jenis_order' => $data['jenis_order'],
            'type_payment' => $data['type_payment'],
            // DP hanya berlaku untuk cash; mode lain diabaikan.
            'dp_nominal' => $data['type_payment'] === TypePayment::Cash->value ? ($data['dp_nominal'] ?? null) : null,
            'status' => $perluApproval ? BatchStatus::Menunggu : BatchStatus::Aktif,
            'diajukan_oleh' => $user->id,
            'disetujui_oleh' => $perluApproval ? null : $user->id,
            'tgl_approval' => $perluApproval ? null : now(),
        ]);

        return redirect()->route('batches.show', $batch)->with('success', $perluApproval
            ? 'Batch diajukan. Tambahkan PO per artikel, lalu tunggu persetujuan 420F sebelum diteruskan ke vendor.'
            : 'Batch dibuat. Tambahkan PO per artikel di dalamnya.');
    }

    /** 420F menyetujui batch ajuan TM420 — sejak ini vendor bisa melihat & mengerjakannya. */
    public function approve(Request $request, Batch $batch)
    {
        if ($batch->status !== BatchStatus::Menunggu) {
            return back()->with('error', 'Batch ini tidak sedang menunggu persetujuan.');
        }
        if ($batch->purchaseOrders()->count() === 0) {
            return back()->with('error', 'Batch belum punya PO artikel — tidak ada yang bisa diteruskan ke vendor.');
        }

        // DP nominal baru bisa divalidasi terhadap total di sini (total = setelah PO diisi).
        // DP ≥ total tak masuk akal (itu bukan "sebagian di muka") → tolak, minta perbaiki.
        if ($batch->isCashDP()) {
            $totalTm = app(\App\Services\SettlementService::class)->cashTotals($batch)['tm420'];
            if ((int) $batch->dp_nominal >= $totalTm) {
                $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');

                return back()->with('error', 'DP '.$fmt($batch->dp_nominal).' ≥ total tagihan '.$fmt($totalTm).
                    '. Kurangi nominal DP, atau kosongkan DP untuk cash penuh di muka.');
            }
        }

        $batch->update([
            'status' => BatchStatus::Aktif,
            'disetujui_oleh' => $request->user()->id,
            'tgl_approval' => now(),
            'catatan_approval' => null,
        ]);

        // Batch cash: terbitkan invoice TAGIHAN ke TM (DP% bila pakai DP, atau penuh). Uang masuk
        // hanya saat invoice ditandai lunas + bukti; 420F bayar Diferd lewat aksi terpisah.
        $inv = app(\App\Services\SettlementService::class)->prosesCashBatch($batch);

        return back()->with('success', $inv
            ? 'Batch cash disetujui. Invoice tagihan '.$inv->nomor.' Rp '.number_format($inv->total, 0, ',', '.').' terbit untuk TM (lihat menu Invoice / Settlement).'
            : 'Batch disetujui & diteruskan ke vendor.');
    }

    public function reject(Request $request, Batch $batch)
    {
        if ($batch->status !== BatchStatus::Menunggu) {
            return back()->with('error', 'Batch ini tidak sedang menunggu persetujuan.');
        }

        $data = $request->validate(['catatan_approval' => ['required', 'string', 'max:255']]);

        $batch->update([
            'status' => BatchStatus::Ditolak,
            'disetujui_oleh' => $request->user()->id,
            'tgl_approval' => now(),
            'catatan_approval' => $data['catatan_approval'],
        ]);

        return back()->with('success', 'Batch ditolak. TM420 bisa memperbaiki lalu mengajukan ulang.');
    }

    /** TM420 mengajukan ulang batch yang ditolak setelah diperbaiki. */
    public function reajukan(Request $request, Batch $batch)
    {
        $this->authorizeView($request, $batch);   // TM/VOOJAH hanya boleh batch brand-nya
        if ($batch->status !== BatchStatus::Ditolak) {
            return back()->with('error', 'Hanya batch yang ditolak yang bisa diajukan ulang.');
        }

        $batch->update([
            'status' => BatchStatus::Menunggu,
            'disetujui_oleh' => null,
            'tgl_approval' => null,
        ]);

        return back()->with('success', 'Batch diajukan ulang ke 420F.');
    }

    public function show(Request $request, Batch $batch)
    {
        $this->authorizeView($request, $batch);
        $batch->load([
            'brand',
            'purchaseOrders.product.category',
            'purchaseOrders.product.files',
            'purchaseOrders.product.spec',
            'purchaseOrders.sizeItems',
        ]);

        // Filter daftar PO berdasarkan tahapan produksi.
        $tahapFilter = $request->input('tahap');
        $pos = $tahapFilter
            ? $batch->purchaseOrders->filter(fn ($po) => $po->tahap->value === $tahapFilter)->values()
            : $batch->purchaseOrders;

        return view('batches.show', [
            'batch' => $batch,
            'pos' => $pos,
            'tahapFilter' => $tahapFilter,
            'adaFile' => $batch->purchaseOrders->contains(fn ($po) => $po->product->files->isNotEmpty()),
        ]);
    }

    /** Export PDF untuk SATU artikel/PO saja (memakai layout Master PO). */
    public function poPdf(Request $request, Batch $batch, \App\Models\PurchaseOrder $purchaseOrder)
    {
        $this->authorizeView($request, $batch);
        abort_unless($purchaseOrder->batch_id === $batch->id, 404);

        $batch->load('brand');
        $purchaseOrder->load(['product.files', 'product.sizes', 'product.category', 'sizeItems']);
        $batch->setRelation('purchaseOrders', collect([$purchaseOrder]));

        return Pdf::loadView('batches.pdf', compact('batch'))->setPaper('a4', 'portrait')
            ->stream('PO-'.$purchaseOrder->nomor_po.'.pdf');
    }

    /** Unduh semua file desain/mentahan seluruh artikel dalam batch sebagai ZIP. */
    public function downloadDesigns(Request $request, Batch $batch)
    {
        $this->authorizeView($request, $batch);
        $batch->load('purchaseOrders.product.files');

        $zipPath = storage_path('app/tmp-batch-'.$batch->id.'-'.uniqid().'.zip');
        @mkdir(dirname($zipPath), 0775, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Gagal membuat file ZIP.');
        }

        $jumlah = 0;
        foreach ($batch->purchaseOrders as $po) {
            $folder = preg_replace('/[^A-Za-z0-9 _-]/', '', $po->product->nama_artikel) ?: 'artikel';
            foreach ($po->product->files as $f) {
                if (\Storage::disk('public')->exists($f->path)) {
                    $zip->addFile(\Storage::disk('public')->path($f->path), $folder.'/'.$f->nama_asli);
                    $jumlah++;
                }
            }
        }
        $zip->close();

        if ($jumlah === 0) {
            @unlink($zipPath);

            return back()->with('error', 'Belum ada file desain pada artikel di batch ini.');
        }

        return response()->download($zipPath, 'DESAIN-'.$batch->nomor_batch.'.zip')->deleteFileAfterSend();
    }

    /** TM420 hanya boleh mengubah batchnya sendiri selama belum disetujui. */
    private function pastikanBolehUbah(Request $request, Batch $batch): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        if ($user->brand_id && $batch->brand_id !== $user->brand_id) {
            abort(403, 'Batch ini bukan milik brand Anda.');
        }
        if (! $batch->status->belumDisetujui()) {
            abort(403, 'Batch sudah disetujui — tidak bisa diubah lagi. Hubungi 420F.');
        }
    }

    public function edit(Request $request, Batch $batch)
    {
        $this->pastikanBolehUbah($request, $batch);

        return view('batches.edit', [
            'batch' => $batch,
            'brands' => Brand::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Batch $batch)
    {
        $this->pastikanBolehUbah($request, $batch);
        $data = $this->validated($request, $batch);
        $tanggal = Carbon::parse($data['tanggal_order']);

        $batch->update([
            'brand_id' => $data['brand_id'],
            'tanggal_order' => $tanggal,
            'deadline' => $tanggal->copy()->addYear(),
            'deadline_produksi' => $data['deadline_produksi'] ?? null,
            'jenis_order' => $data['jenis_order'],
            'type_payment' => $data['type_payment'],
            // Status hanya boleh disetel 420F — kalau tidak, TM bisa melompati persetujuan
            // dengan mengirim field status langsung ke endpoint ini.
            'status' => $request->user()->isAdmin()
                ? ($data['status'] ?? $batch->status->value)
                : $batch->status->value,
        ]);

        return redirect()->route('batches.show', $batch)->with('success', 'Batch diperbarui.');
    }

    public function destroy(Request $request, Batch $batch)
    {
        $this->pastikanBolehUbah($request, $batch);
        $batch->delete();

        return redirect()->route('batches.index')->with('success', 'Batch dihapus.');
    }

    public function pdf(Request $request, Batch $batch)
    {
        $this->authorizeView($request, $batch);
        $batch->load([
            'brand',
            'purchaseOrders.product.files',
            'purchaseOrders.product.sizes',
            'purchaseOrders.product.category',
            'purchaseOrders.sizeItems',
        ]);

        $pdf = Pdf::loadView('batches.pdf', compact('batch'))->setPaper('a4', 'portrait');

        return $pdf->stream('MASTER-PO-'.$batch->nomor_batch.'.pdf');
    }

    // ----- helpers -----

    private function scope($query, Request $request): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }
        // Vendor baru boleh melihat batch setelah 420F menyetujuinya.
        if ($user->role === Role::Diferd) {
            $query->whereNotIn('status', [BatchStatus::Menunggu->value, BatchStatus::Ditolak->value]);
        }
    }

    private function authorizeView(Request $request, Batch $batch): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id && $batch->brand_id !== $user->brand_id) {
            abort(403, 'Batch ini bukan milik brand Anda.');
        }
        if ($user->role === Role::Diferd && $batch->status->belumDisetujui()) {
            abort(403, 'Batch ini belum disetujui 420F.');
        }
    }

    public static function brandKode(Brand $brand): string
    {
        return $brand->kode ?: strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $brand->nama), 0, 2));
    }

    private function generateBatchNumber(Brand $brand, Carbon $tanggal): string
    {
        $base = 'MPO.'.self::brandKode($brand).'.'.$tanggal->format('m.y');
        $nomor = $base;
        $i = 2;
        while (Batch::where('nomor_batch', $nomor)->exists()) {
            $nomor = $base.'-'.$i++;
        }

        return $nomor;
    }

    private function validated(Request $request, ?Batch $batch = null): array
    {
        return $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'tanggal_order' => ['required', 'date'],
            'deadline_produksi' => ['nullable', 'date'],
            'jenis_order' => ['required', new Enum(JenisOrder::class)],
            'type_payment' => ['required', new Enum(TypePayment::class)],
            // DP hanya untuk cash; 1–99% (100 = cash penuh biasa, kosong = tanpa DP).
            'dp_nominal' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', new Enum(\App\Enums\BatchStatus::class)],
        ]);
    }
}

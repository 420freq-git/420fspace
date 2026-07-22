<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\BrandLedger;
use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $brandId = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;

        $invoices = Invoice::with(['brand', 'orders.items'])
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->latest('tanggal_terbit')->latest('id')->get();

        // Pesanan cair yang belum ditagihkan (untuk tombol buat invoice).
        $belumDitagih = Order::whereNull('invoice_id')
            ->where('status', 'lunas')
            ->whereHas('brand', fn ($q) => $q->where('tipe', 'eksternal'))
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->count();

        return view('invoices.index', [
            'invoices' => $invoices,
            'belumDitagih' => $belumDitagih,
            'isAdmin' => $user->isAdmin(),
            'totalDitagih' => $invoices->sum(fn ($i) => $i->total),
            'totalDibayar' => $invoices->where('status', 'lunas')->sum(fn ($i) => $i->total),
            'totalBelum' => $invoices->where('status', '!=', 'lunas')->sum(fn ($i) => $i->total),
        ]);
    }

    /** Buat invoice otomatis: kumpulkan pesanan cair belum-ditagih per brand eksternal. */
    public function generate()
    {
        $groups = Order::whereNull('invoice_id')
            ->where('status', 'lunas')
            ->whereHas('brand', fn ($q) => $q->where('tipe', 'eksternal'))
            ->with('items')
            ->get()
            ->groupBy('brand_id');

        if ($groups->isEmpty()) {
            return back()->with('error', 'Tidak ada pesanan cair yang belum ditagihkan.');
        }

        $dibuat = 0;
        foreach ($groups as $brandId => $group) {
            $brand = Brand::find($brandId);
            $invoice = Invoice::create([
                'brand_id' => $brandId,
                'nomor' => $this->generateNomor($brand),
                'tanggal_terbit' => now(),
                'status' => 'belum_bayar',
            ]);
            Order::whereIn('id', $group->pluck('id'))->update(['invoice_id' => $invoice->id]);
            $dibuat++;
        }

        return redirect()->route('invoices.index')->with('success', $dibuat.' invoice dibuat dari pesanan cair.');
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeView($request, $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load(['brand', 'orders.items.product']),
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        $this->authorizeView($request, $invoice);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice->load(['brand', 'orders.items.product']),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('INVOICE-'.$invoice->nomor.'.pdf');
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'lunas') {
            return back()->with('error', 'Invoice sudah lunas.');
        }

        $data = $request->validate(['tanggal_bayar' => ['required', 'date']]);
        $invoice->load('orders.items');
        $total = $invoice->total;

        $invoice->update(['status' => 'lunas', 'tanggal_bayar' => $data['tanggal_bayar']]);

        BrandLedger::create([
            'brand_id' => $invoice->brand_id,
            'tanggal' => $data['tanggal_bayar'],
            'jumlah' => $total,
            'keterangan' => 'Pembayaran invoice '.$invoice->nomor,
        ]);

        return back()->with('success', 'Invoice '.$invoice->nomor.' ditandai lunas & penerimaan dicatat di cashflow.');
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->status === 'lunas') {
            return back()->with('error', 'Invoice lunas tidak bisa dihapus.');
        }
        if ($invoice->isBuyout()) {
            return back()->with('error', 'Invoice buy-out dibatalkan dari halaman Settlement batch (agar hak Diferd & status stok ikut dilepas), bukan dari sini.');
        }

        $invoice->orders()->update(['invoice_id' => null]);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice dihapus, pesanan dilepas kembali.');
    }

    // ----- helpers -----

    private function authorizeView(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id && $invoice->brand_id !== $user->brand_id) {
            abort(403, 'Invoice ini bukan milik brand Anda.');
        }
    }

    private function generateNomor(Brand $brand): string
    {
        $base = 'INV.'.BatchController::brandKode($brand).'.'.now()->format('m.y').'.';
        $n = Invoice::where('brand_id', $brand->id)->whereYear('tanggal_terbit', now()->year)->count() + 1;
        do {
            $nomor = $base.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $n++;
        } while (Invoice::where('nomor', $nomor)->exists());

        return $nomor;
    }
}

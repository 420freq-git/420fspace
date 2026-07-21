<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Brand;
use App\Models\BrandLedger;
use App\Models\Penarikan;
use App\Models\Sale;
use App\Models\VendorLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashflowController extends Controller
{
    public function __construct(private \App\Services\SettlementService $settlement) {}

    public function index()
    {
        // Konsinyasi saja (batch cash sudah lunas di muka & dikecualikan).
        $lunas = fn () => Sale::consignment()->whereHas('order', fn ($o) => $o->where('status', 'lunas'))->whereNotNull('harga_tm420');

        $tagihanTM = (int) $lunas()->sum(DB::raw('qty * harga_tm420'));
        $feeKonsinyasi = (int) $lunas()->sum(DB::raw('qty * (harga_tm420 - harga_diferd)'));
        // Fee 420F dari batch cash (margin di muka) — dari BrandLedger cash − VendorLedger cash.
        $cashTm = (int) BrandLedger::where('keterangan', 'like', 'Cash batch%')->sum('jumlah');
        $cashDiferd = (int) VendorLedger::where('tipe', 'cash')->sum('jumlah');
        $fee = $feeKonsinyasi + ($cashTm - $cashDiferd);

        $penarikanCair = (int) Penarikan::where('status', 'disetujui')->sum('jumlah');
        $kewajibanDiferd = (int) Sale::sold()->consignment()->sum(DB::raw('qty * harga_diferd'));
        // whereNull penarikan_id: baris hasil pembekuan penarikan sudah terwakili $penarikanCair —
        // tanpa filter ini uang penarikan terhitung dua kali.
        $pembayaranDiferd = (int) VendorLedger::where('tipe', 'pembayaran')->whereNull('penarikan_id')->sum('jumlah') + $penarikanCair;
        $buyoutDiferd = (int) VendorLedger::where('tipe', 'buyout')->sum('jumlah');
        // Modal (deposit) yang masih mengendap di vendor — global, di luar kas 420F.
        $modalDiferd = $this->settlement->depositMengendap();
        // Uang keluar ke Diferd = pembayaran konsinyasi + buyout + cash di muka.
        $dibayarDiferd = $pembayaranDiferd + $buyoutDiferd + $cashDiferd;
        $ditransferTM = (int) BrandLedger::sum('jumlah');
        // Transfer buy-out & cash (TM bayar 420F di muka) dipisah dari transfer penjualan konsinyasi.
        $transferKhusus = (int) BrandLedger::where('keterangan', 'like', 'Buy-out sisa stok%')
            ->orWhere('keterangan', 'like', 'Cash batch%')->sum('jumlah');
        $ditransferTMPenjualan = $ditransferTM - $transferKhusus;

        return view('cashflow.index', [
            'tagihanTM' => $tagihanTM,
            'ditransferTM' => $ditransferTMPenjualan,
            'sisaTagihanTM' => $tagihanTM - $ditransferTMPenjualan,
            'kewajibanDiferd' => $kewajibanDiferd,
            'pembayaranDiferd' => $pembayaranDiferd,
            'modalDiferd' => $modalDiferd,
            'buyoutDiferd' => $buyoutDiferd,
            'dibayarDiferd' => $dibayarDiferd,
            'sisaBayarDiferd' => $kewajibanDiferd - $pembayaranDiferd,
            'fee' => $fee,
            'posisiKas' => $ditransferTM - $dibayarDiferd,
            'ledger' => BrandLedger::with('brand')->latest('tanggal')->latest('id')->get(),
        ]);
    }

    public function storeTransfer(Request $request)
    {
        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $tm = Brand::where('nama', 'TM420')->firstOrFail();

        BrandLedger::create([
            'brand_id' => $tm->id,
            'tanggal' => $data['tanggal'],
            'jumlah' => $data['jumlah'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return back()->with('success', 'Transfer dari TM420 dicatat.');
    }

    public function destroyTransfer(BrandLedger $brandLedger)
    {
        $brandLedger->delete();

        return back()->with('success', 'Entri transfer dihapus.');
    }
}

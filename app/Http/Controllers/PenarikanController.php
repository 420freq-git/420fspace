<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Penarikan;
use App\Models\Sale;
use App\Models\VendorLedger;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PenarikanController extends Controller
{
    public function __construct(private SettlementService $settlement) {}

    public function index(Request $request)
    {
        $saldo = $this->saldo();
        $riwayat = Penarikan::with('alokasi.batch')->latest('tanggal_ajuan')->latest('id')->get();

        // Pratinjau pembagian untuk yang masih menunggu persetujuan (belum dibekukan).
        $rencana = [];
        foreach ($riwayat->where('status', 'diajukan') as $p) {
            $r = $this->settlement->rencanaAlokasi((int) $p->jumlah);
            $rencana[$p->id] = [
                'baris' => Batch::whereIn('id', array_keys($r['alokasi']))->get()
                    ->mapWithKeys(fn ($b) => [$b->nomor_batch => $r['alokasi'][$b->id]]),
                'sisa' => $r['sisa'],
            ];
        }

        return view('penarikan.index', array_merge($saldo, [
            'riwayat' => $riwayat,
            'rencana' => $rencana,
            'isAdmin' => $request->user()->isAdmin(),
        ]));
    }

    /** Diferd (atau admin) mengajukan penarikan. */
    public function store(Request $request)
    {
        $tersedia = $this->saldo()['tersedia'];

        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1', 'max:'.max(0, $tersedia)],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'jumlah.max' => 'Jumlah melebihi saldo tersedia ('.number_format($tersedia, 0, ',', '.').').',
        ]);

        Penarikan::create([
            'jumlah' => $data['jumlah'],
            'status' => 'diajukan',
            'tanggal_ajuan' => now(),
            'catatan' => $data['catatan'] ?? null,
        ]);

        return back()->with('success', 'Permintaan penarikan diajukan. Menunggu persetujuan 420F.');
    }

    /**
     * 420F menyetujui & menandai cair. Sekaligus MEMBEKUKAN pembagian dana ke batch: jumlah yang
     * cair dipecah FIFO ke batch yang haknya belum tertutup, lalu ditulis sebagai baris ledger.
     * Setelah ini, sisa per batch jadi angka historis yang tidak bergeser lagi.
     */
    public function approve(Request $request, Penarikan $penarikan)
    {
        if ($penarikan->status !== 'diajukan') {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }

        if ($penarikan->jumlah > $this->hakGlobal() - $this->hakDibayar()) {
            return back()->with('error', 'Saldo tidak cukup untuk menyetujui penarikan ini.');
        }

        DB::transaction(function () use ($penarikan) {
            $rencana = $this->settlement->rencanaAlokasi((int) $penarikan->jumlah);
            $batches = Batch::whereIn('id', array_keys($rencana['alokasi']))->get()->keyBy('id');

            foreach ($rencana['alokasi'] as $batchId => $jumlah) {
                VendorLedger::create([
                    'brand_id' => $batches[$batchId]->brand_id,
                    'batch_id' => $batchId,
                    'penarikan_id' => $penarikan->id,
                    'tanggal' => now(),
                    'tipe' => 'pembayaran',
                    'jumlah' => $jumlah,
                    'keterangan' => 'Penarikan #'.$penarikan->id,
                ]);
            }

            $penarikan->update(['status' => 'disetujui', 'tanggal_cair' => now()]);
        });

        return back()->with('success', 'Penarikan disetujui & ditandai cair. Pembagian ke batch sudah dicatat.');
    }

    public function reject(Penarikan $penarikan)
    {
        if ($penarikan->status !== 'diajukan') {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }

        $penarikan->update(['status' => 'ditolak']);

        return back()->with('success', 'Permintaan penarikan ditolak.');
    }

    public function destroy(Penarikan $penarikan)
    {
        if ($penarikan->status === 'disetujui') {
            return back()->with('error', 'Penarikan yang sudah cair tidak bisa dihapus.');
        }

        $penarikan->delete();

        return back()->with('success', 'Permintaan penarikan dihapus.');
    }

    /** Unggah bukti transfer &/atau invoice untuk arsip. */
    public function uploadBukti(Request $request, Penarikan $penarikan)
    {
        $request->validate([
            'bukti_transfer' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'bukti_invoice' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $data = [];
        foreach (['bukti_transfer', 'bukti_invoice'] as $field) {
            if ($request->hasFile($field)) {
                if ($penarikan->$field) {
                    Storage::disk('public')->delete($penarikan->$field);
                }
                $data[$field] = $request->file($field)->store("penarikan/{$penarikan->id}", 'public');
            }
        }

        if ($data) {
            $penarikan->update($data);
        }

        return back()->with('success', 'Bukti diunggah.');
    }

    public function bukti(Penarikan $penarikan, string $jenis)
    {
        $path = $jenis === 'invoice' ? $penarikan->bukti_invoice : $penarikan->bukti_transfer;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'File tidak ditemukan.');

        return Storage::disk('public')->download($path);
    }

    // ----- saldo global Diferd -----

    private function hakGlobal(): int
    {
        // Hanya penjualan konsinyasi. Batch cash sudah dibayar penuh di muka (ledger tipe 'cash'),
        // jadi penjualannya tidak menambah hak yang bisa ditarik — mencegah dobel bayar.
        return (int) Sale::sold()->consignment()->sum(DB::raw('qty * harga_diferd'));
    }

    /**
     * Hak yang sudah dibayar: entri ledger manual 420F + seluruh penarikan yang cair.
     * Baris ledger hasil pembekuan penarikan (penarikan_id terisi) sengaja dikecualikan supaya
     * tidak terhitung dua kali — nilai penuh penarikan sudah dihitung dari tabel penarikan.
     */
    private function hakDibayar(): int
    {
        return (int) VendorLedger::whereIn('tipe', ['pembayaran', 'buyout'])
                ->whereNull('penarikan_id')->sum('jumlah')
            + (int) Penarikan::where('status', 'disetujui')->sum('jumlah');
    }

    private function saldo(): array
    {
        $hak = $this->hakGlobal();
        $dibayar = $this->hakDibayar();
        $pending = (int) Penarikan::where('status', 'diajukan')->sum('jumlah');

        return [
            'hak' => $hak,
            'dibayar' => $dibayar,
            'pending' => $pending,
            'tersedia' => $hak - $dibayar - $pending,
        ];
    }
}

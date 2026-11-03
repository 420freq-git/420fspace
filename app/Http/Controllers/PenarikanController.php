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

        return view('penarikan.index', array_merge($saldo, $this->rincianHak(), [
            'riwayat' => $riwayat,
            'rencana' => $rencana,
            'isAdmin' => $request->user()->isAdmin(),
        ]));
    }

    /**
     * Rincian hak Diferd per BRAND, dengan daftar ARTIKEL di dalamnya.
     *
     * Sumber sama persis dengan hakGlobal()/hakDibayar() supaya total cocok dengan kartu saldo:
     * - hak artikel  = Sale sold+consignment (qty × harga_diferd), dikelompokkan per produk;
     * - buy-out      = VendorLedger 'buyout' per brand (tingkat batch, tak punya artikel);
     * - dibayar      = VendorLedger 'pembayaran' per brand (manual + hasil pembekuan penarikan).
     *
     * "Dibayar/sisa" sengaja HANYA per brand, tidak per artikel: penarikan dibekukan FIFO sampai
     * tingkat batch saja, jadi pembagian per artikel tidak punya dasar data (akan jadi angka karangan).
     */
    private function rincianHak(): array
    {
        $artikel = Sale::sold()->consignment()
            ->select('brand_id', 'product_id')
            ->selectRaw('SUM(qty) as pcs, SUM(qty * harga_diferd) as hak')
            ->groupBy('brand_id', 'product_id')
            ->with(['brand:id,nama', 'product:id,nama_artikel,sku_induk'])
            ->get();

        $buyout = VendorLedger::where('tipe', 'buyout')
            ->selectRaw('brand_id, SUM(jumlah) as total')->groupBy('brand_id')->pluck('total', 'brand_id');
        $dibayar = VendorLedger::where('tipe', 'pembayaran')
            ->selectRaw('brand_id, SUM(jumlah) as total')->groupBy('brand_id')->pluck('total', 'brand_id');

        $brandIds = $artikel->pluck('brand_id')->merge($buyout->keys())->merge($dibayar->keys())->unique();
        $brands = \App\Models\Brand::whereIn('id', $brandIds)->orderBy('nama')->get(['id', 'nama']);

        $perBrand = $brands->map(function ($b) use ($artikel, $buyout, $dibayar) {
            $items = $artikel->where('brand_id', $b->id)->sortByDesc('hak')->values();
            $hakJual = (int) $items->sum('hak');
            $bo = (int) ($buyout[$b->id] ?? 0);
            $bayar = (int) ($dibayar[$b->id] ?? 0);

            return [
                'nama' => $b->nama,
                'artikel' => $items,
                'pcs' => (int) $items->sum('pcs'),
                'hak_jual' => $hakJual,
                'buyout' => $bo,
                'hak' => $hakJual + $bo,
                'dibayar' => $bayar,
                'sisa' => $hakJual + $bo - $bayar,
            ];
        })->filter(fn ($r) => $r['hak'] !== 0 || $r['dibayar'] !== 0)->values();

        return [
            'rincianBrand' => $perBrand,
            // Penarikan cair yang tak terserap batch mana pun → tak punya brand.
            'belumTeralokasi' => $this->settlement->penarikanBelumTeralokasi(),
        ];
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

    /**
     * PDF bukti pembayaran ke Diferd untuk satu penarikan yang sudah cair.
     *
     * Bagian "dibayar" berasal dari baris pembekuan (vendor_ledger dengan penarikan_id) — historis,
     * tidak bergeser. Bagian artikel adalah rincian HAK batch tersebut (asal hak), BUKAN klaim
     * artikel mana yang dibayar oleh transfer ini: pembekuan FIFO hanya sampai tingkat batch, jadi
     * bila satu penarikan menutup sebagian hak batch, tidak ada dasar data untuk membaginya per artikel.
     */
    public function pdf(Penarikan $penarikan)
    {
        abort_unless($penarikan->status === 'disetujui', 404, 'Bukti hanya untuk penarikan yang sudah cair.');

        $penarikan->load('alokasi.batch.brand');

        $batches = $penarikan->alokasi->filter(fn ($a) => $a->batch)->map(function ($a) {
            $b = $a->batch;
            $artikel = Sale::where('batch_id', $b->id)->sold()->consignment()
                ->select('product_id')
                ->selectRaw('SUM(qty) as pcs, SUM(qty * harga_diferd) as hak')
                ->groupBy('product_id')->with('product:id,nama_artikel,sku_induk')
                ->get()->sortByDesc('hak')->values();

            return [
                'batch' => $b,
                'dibayar' => (int) $a->jumlah,
                'artikel' => $artikel,
                'buyout' => $this->settlement->buyout($b->id),
                'hak_batch' => $this->settlement->kewajiban($b->id),
            ];
        })->values();

        $data = [
            'p' => $penarikan,
            'batches' => $batches,
            'perBrand' => $batches->groupBy(fn ($r) => $r['batch']->brand?->nama ?? '—')
                ->map(fn ($g) => $g->sum('dibayar')),
            'tanpaBatch' => max(0, (int) $penarikan->jumlah - (int) $penarikan->alokasi->sum('jumlah')),
        ];

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('penarikan.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->stream('bukti-bayar-diferd-'.$penarikan->id.'.pdf');
    }

    // ----- saldo global Diferd -----

    private function hakGlobal(): int
    {
        // Penjualan konsinyasi + buy-out sisa stok. Batch cash sudah dibayar penuh di muka (ledger
        // tipe 'cash'), jadi tidak menambah hak yang bisa ditarik — mencegah dobel bayar. Buy-out
        // kini menambah hak Diferd (420F beli sisa stok), ditutup lewat penarikan seperti hak jual.
        return (int) Sale::sold()->consignment()->sum(DB::raw('qty * harga_diferd'))
            + (int) VendorLedger::where('tipe', 'buyout')->sum('jumlah');
    }

    /**
     * Hak yang sudah dibayar: entri ledger pembayaran manual 420F + seluruh penarikan yang cair.
     * Baris ledger hasil pembekuan penarikan (penarikan_id terisi) sengaja dikecualikan supaya
     * tidak terhitung dua kali — nilai penuh penarikan sudah dihitung dari tabel penarikan.
     * Buy-out TIDAK dihitung di sini: ia hak yang belum dibayar (lihat hakGlobal), bukan pembayaran.
     */
    private function hakDibayar(): int
    {
        return (int) VendorLedger::where('tipe', 'pembayaran')
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

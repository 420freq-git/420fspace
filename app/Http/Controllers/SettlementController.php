<?php

namespace App\Http\Controllers;

use App\Enums\LedgerTipe;
use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\BrandLedger;
use App\Models\VendorLedger;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;

class SettlementController extends Controller
{
    public function __construct(
        private SettlementService $settlement,
        private \App\Services\StockService $stock,
    ) {}

    /** Rincian stok sebuah batch: diproduksi / terjual / sisa, dikelompokkan per kategori. */
    private function stokBatch(Batch $batch): array
    {
        $rows = [];
        $pos = \App\Models\PurchaseOrder::where('batch_id', $batch->id)
            ->with(['product.category', 'sizeItems'])->get();

        foreach ($pos as $po) {
            foreach ($po->sizeItems as $si) {
                $key = $po->product_id.'|'.$si->ukuran->value;
                $rows[$key] ??= [
                    'product' => $po->product,
                    'kategori' => $po->product->category->nama ?? 'Tanpa kategori',
                    'ukuran' => $si->ukuran->value,
                    'produced' => 0,
                ];
                $rows[$key]['produced'] += (int) $si->qty;
            }
        }

        foreach ($rows as &$r) {
            $r['sold'] = $this->stock->soldInBatch($batch->id, $r['product']->id, $r['ukuran']);
            $r['short'] = $this->stock->shortfallInBatch($batch->id, $r['product']->id, $r['ukuran']);
            $r['sisa'] = $r['produced'] - $r['sold'] - $r['short'];
        }
        unset($r);

        $col = collect($rows);

        return [
            'byKategori' => $col->groupBy('kategori')->map(fn ($g) => [
                'diproduksi' => (int) $g->sum('produced'),
                'terjual' => (int) $g->sum('sold'),
                'sisa' => (int) $g->sum('sisa'),
                'artikels' => $g->groupBy(fn ($r) => $r['product']->id)->map(fn ($a) => [
                    'nama' => $a->first()['product']->nama_artikel,
                    'diproduksi' => (int) $a->sum('produced'),
                    'terjual' => (int) $a->sum('sold'),
                    'sisa' => (int) $a->sum('sisa'),
                ])->values(),
            ])->sortKeys(),
            'diproduksi' => (int) $col->sum('produced'),
            'terjual' => (int) $col->sum('sold'),
            'sisa' => (int) $col->sum('sisa'),
        ];
    }

    public function index()
    {
        $rows = Batch::with('brand')->latest('tanggal_order')->latest('id')->get()
            ->map(fn ($b) => ['batch' => $b, 'sum' => $this->settlement->batchSummary($b)]);

        // Penarikan sudah dibekukan jadi baris ledger per batch, jadi ikut terhitung di 'terbayar'.
        // Sisanya (dari penjualan tanpa batch) tidak punya batch tujuan — ditambahkan terpisah.
        $penarikanCair = (int) \App\Models\Penarikan::where('status', 'disetujui')->sum('jumlah');
        $penarikanPending = (int) \App\Models\Penarikan::where('status', 'diajukan')->sum('jumlah');
        $penarikanSisa = $this->settlement->penarikanBelumTeralokasi();

        $kewajiban = (int) $rows->sum(fn ($r) => $r['sum']['kewajiban']);
        $terbayarBatch = (int) $rows->sum(fn ($r) => $r['sum']['terbayar']);

        $totals = [
            'kewajiban' => $kewajiban,
            'terbayar' => $terbayarBatch,
            'penarikan' => $penarikanCair,
            'penarikan_pending' => $penarikanPending,
            'penarikan_sisa' => $penarikanSisa,
            'dibayar_total' => $terbayarBatch + $penarikanSisa,
            'saldo' => max(0, $kewajiban - $terbayarBatch),
            // Deposit = modal sekali di awal kerja sama, mengendap di vendor — bukan milik batch.
            'modal' => $this->settlement->depositMengendap(),
            'fee' => $rows->sum(fn ($r) => $r['sum']['fee420f']),
        ];

        // Rekap stok seluruh batch (diproduksi / terjual / sisa) per kategori.
        $agg = [];
        $stokTotal = ['diproduksi' => 0, 'terjual' => 0, 'sisa' => 0];
        foreach ($rows as $r) {
            $s = $this->stokBatch($r['batch']);
            foreach ($s['byKategori'] as $kat => $k) {
                $agg[$kat] ??= ['diproduksi' => 0, 'terjual' => 0, 'sisa' => 0];
                foreach (['diproduksi', 'terjual', 'sisa'] as $f) {
                    $agg[$kat][$f] += $k[$f];
                }
            }
            foreach (['diproduksi', 'terjual', 'sisa'] as $f) {
                $stokTotal[$f] += $s[$f];
            }
        }
        $stokKategori = collect($agg)->sortKeys();

        return view('settlement.index', compact('rows', 'totals', 'stokKategori', 'stokTotal'));
    }

    public function show(Batch $batch)
    {
        return view('settlement.show', [
            'batch' => $batch->load('brand'),
            'summary' => $this->settlement->batchSummary($batch),
            'stok' => $this->stokBatch($batch),
            'ledger' => VendorLedger::where('batch_id', $batch->id)->latest('tanggal')->latest('id')->get(),
        ]);
    }

    public function storeLedger(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'tipe' => ['required', new Enum(LedgerTipe::class)],
            'jumlah' => ['required', 'integer', 'min:1'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $batch) {
            VendorLedger::create([
                'brand_id' => $batch->brand_id,
                'batch_id' => $batch->id,
                'tanggal' => $data['tanggal'],
                'tipe' => $data['tipe'],
                'jumlah' => $data['jumlah'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            // Buy-out: TM420 bayar ke 420F, lalu 420F teruskan ke Diferd (VendorLedger di atas).
            // Sisi TM dicatat sebagai BrandLedger supaya kas 420F NETRAL (masuk = keluar).
            if ($data['tipe'] === LedgerTipe::Buyout->value) {
                BrandLedger::create([
                    'brand_id' => $batch->brand_id,
                    'tanggal' => $data['tanggal'],
                    'jumlah' => $data['jumlah'],
                    'keterangan' => 'Buy-out sisa stok '.$batch->nomor_batch.' (TM bayar 420F)',
                ]);
            }
        });

        return back()->with('success', 'Entri pembayaran dicatat.');
    }

    public function destroyLedger(VendorLedger $ledger)
    {
        if ($ledger->penarikan_id) {
            return back()->with('error', 'Entri ini berasal dari penarikan #'.$ledger->penarikan_id.' — batalkan dari menu Penarikan, jangan dihapus di sini.');
        }

        $batch = $ledger->batch;

        DB::transaction(function () use ($ledger, $batch) {
            // Hapus juga sisi TM (BrandLedger) bila ini entri buy-out, supaya kas tetap seimbang.
            if ($ledger->tipe === LedgerTipe::Buyout && $batch) {
                BrandLedger::where('brand_id', $batch->brand_id)
                    ->where('jumlah', $ledger->jumlah)
                    ->where('keterangan', 'like', 'Buy-out sisa stok '.$batch->nomor_batch.'%')
                    ->latest('id')->limit(1)->delete();
            }
            $ledger->delete();
        });

        return redirect()->route('settlement.show', $batch)->with('success', 'Entri dihapus.');
    }

    public function markStatus(Request $request, Batch $batch)
    {
        $data = $request->validate(['status' => ['required', new Enum(BatchStatus::class)]]);
        $batch->update(['status' => $data['status']]);

        return back()->with('success', 'Status batch diperbarui.');
    }

    /**
     * Penyelesaian deposit global — SEKALI di akhir kerja sama.
     *
     * Dua cara, dipilih 420F sesuai kesepakatan bertiga:
     *  - offset  : deposit dianggap membayar sisa hak Diferd. Dibagi FIFO ke batch (mekanisme
     *              yang sama dengan penarikan) supaya saldo per batch ikut tertutup; sisi TM
     *              dicatat sebagai uang muka (kredit tagihan). Kas 420F nol (masuk = keluar).
     *  - kembali : Diferd mengembalikan dananya langsung — tidak menyentuh hak, tagihan,
     *              maupun kas 420F; hanya menutup catatan modal mengendap.
     */
    public function selesaikanDeposit(Request $request)
    {
        $data = $request->validate(['cara' => ['required', 'in:offset,kembali']]);

        $jumlah = $this->settlement->depositMengendap();
        if ($jumlah <= 0) {
            return back()->with('error', 'Tidak ada deposit yang mengendap.');
        }

        $brandId = VendorLedger::where('tipe', 'deposit')->whereNull('batch_id')->value('brand_id')
            ?? \App\Models\Brand::where('nama', 'TM420')->value('id');

        DB::transaction(function () use ($data, $jumlah, $brandId) {
            VendorLedger::create([
                'brand_id' => $brandId,
                'batch_id' => null,
                'tanggal' => now(),
                'tipe' => 'deposit_selesai',
                'jumlah' => $jumlah,
                'keterangan' => $data['cara'] === 'offset'
                    ? 'Deposit di-offset ke hak Diferd (penyelesaian kerja sama)'
                    : 'Deposit dikembalikan Diferd (penyelesaian kerja sama)',
            ]);

            if ($data['cara'] === 'kembali') {
                return;
            }

            // Sisi TM: deposit jadi uang muka — mengurangi sisa tagihan TM ke 420F.
            BrandLedger::create([
                'brand_id' => $brandId,
                'tanggal' => now(),
                'jumlah' => $jumlah,
                'keterangan' => 'Uang muka (deposit) — penyelesaian kerja sama',
            ]);

            // Sisi Diferd: dibagi FIFO ke batch yang haknya belum tertutup, sisa tak terserap
            // tetap dicatat global supaya total hak-dibayar tidak kehilangan sepeser pun.
            $rencana = $this->settlement->rencanaAlokasi($jumlah);
            $batches = Batch::whereIn('id', array_keys($rencana['alokasi']))->get()->keyBy('id');

            foreach ($rencana['alokasi'] as $batchId => $bagian) {
                VendorLedger::create([
                    'brand_id' => $batches[$batchId]->brand_id,
                    'batch_id' => $batchId,
                    'tanggal' => now(),
                    'tipe' => 'pembayaran',
                    'jumlah' => $bagian,
                    'keterangan' => 'Offset deposit (penyelesaian kerja sama)',
                ]);
            }

            if ($rencana['sisa'] > 0) {
                VendorLedger::create([
                    'brand_id' => $brandId,
                    'batch_id' => null,
                    'tanggal' => now(),
                    'tipe' => 'pembayaran',
                    'jumlah' => $rencana['sisa'],
                    'keterangan' => 'Offset deposit — tak terserap batch (penyelesaian kerja sama)',
                ]);
            }
        });

        return back()->with('success', $data['cara'] === 'offset'
            ? 'Deposit '.number_format($jumlah, 0, ',', '.').' di-offset ke hak Diferd & tagihan TM.'
            : 'Deposit ditandai dikembalikan oleh Diferd.');
    }

    /**
     * [LEGACY — deposit per batch] Rekonsiliasi deposit saat batch selesai. Dipertahankan hanya
     * untuk batch lama yang deposit_awal-nya belum dimigrasikan; batch baru tidak punya deposit.
     */
    public function rekonsiliasiDeposit(Batch $batch)
    {
        $deposit = (int) $batch->deposit_awal;

        if ($batch->deposit_rekonsiliasi) {
            return back()->with('error', 'Deposit batch ini sudah direkonsiliasi.');
        }
        if ($deposit <= 0) {
            return back()->with('error', 'Batch ini tidak punya deposit untuk direkonsiliasi.');
        }

        // Sisi TM: uang muka yang TM setor (kredit tagihan TM).
        BrandLedger::create([
            'brand_id' => $batch->brand_id,
            'tanggal' => now(),
            'jumlah' => $deposit,
            'keterangan' => 'Uang muka (deposit) batch '.$batch->nomor_batch,
        ]);

        // Sisi Diferd: deposit yang diteruskan ke Diferd — offset ke hak (dihitung sebagai pembayaran).
        VendorLedger::create([
            'brand_id' => $batch->brand_id,
            'batch_id' => $batch->id,
            'tanggal' => now(),
            'tipe' => 'pembayaran',
            'jumlah' => $deposit,
            'keterangan' => 'Deposit modal di-offset ke hak (rekonsiliasi)',
        ]);

        $batch->update(['deposit_rekonsiliasi' => true, 'tgl_rekonsiliasi' => now()]);

        return back()->with('success', 'Deposit direkonsiliasi (offset ke hak Diferd & tagihan TM).');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\LedgerTipe;
use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\BrandLedger;
use App\Models\Invoice;
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
        // Tandai batch tuntas jadi Lunas otomatis (420F/Diferd lihat semua batch).
        $this->settlement->reconcileLunas();

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

        // Buy-out punya aksi tersendiri (mengonsumsi stok) — jangan lewat form ledger generik.
        if ($data['tipe'] === LedgerTipe::Buyout->value) {
            return back()->with('error', 'Buy-out dilakukan lewat tombol "Buy-out sisa stok", bukan form ini.');
        }

        VendorLedger::create([
            'brand_id' => $batch->brand_id,
            'batch_id' => $batch->id,
            'tanggal' => $data['tanggal'],
            'tipe' => $data['tipe'],
            'jumlah' => $data['jumlah'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return back()->with('success', 'Entri pembayaran dicatat.');
    }

    /**
     * Pembayaran CASH batch (beli putus di muka). Dipanggil saat batch cash disetujui, atau manual.
     * TM bayar 420F penuh (PO × tm420), 420F bayar Diferd penuh (PO × diferd), 420F ambil margin.
     * Setelah ini batch lunas — penjualannya tak menambah hak Diferd (lihat Sale::consignment()).
     */
    public function bayarCash(Request $request, Batch $batch)
    {
        if (! $batch->isCash()) {
            return back()->with('error', 'Batch ini bukan tipe pembayaran cash.');
        }
        if ($batch->cash_dibayar) {
            return back()->with('error', 'Batch cash ini sudah dibayar di muka.');
        }

        $t = $this->settlement->prosesCashBatch($batch);
        if ($t === null) {
            return back()->with('error', 'Batch belum punya PO — tidak ada yang dibayar.');
        }

        return back()->with('success', 'Batch cash dibayar di muka: Diferd Rp '.number_format($t['diferd'], 0, ',', '.')
            .', TM ke 420F Rp '.number_format($t['tm420'], 0, ',', '.').', margin 420F Rp '.number_format($t['fee'], 0, ',', '.').'.');
    }

    /**
     * Penyelesaian kewajiban GANTI Diferd atas reject di batch cash (dibayar penuh di muka).
     * 420F memilih per kasus:
     *  - barang : Diferd re-produksi pcs yang reject. Tak ada uang bergerak (barang akhirnya lengkap);
     *             hanya menandai kewajiban terpenuhi.
     *  - refund : Diferd mengembalikan uang senilai reject × harga_diferd ke 420F, dan 420F
     *             meneruskan reject × harga_tm420 ke TM. Kas 420F netral (margin unit hantu ikut balik);
     *             kerugian riil (biaya produksi gagal) ditanggung Diferd.
     */
    public function gantiCash(Request $request, Batch $batch)
    {
        if (! $batch->isCash()) {
            return back()->with('error', 'Batch ini bukan tipe pembayaran cash.');
        }

        $sisa = $this->settlement->gantiCashSisa($batch);
        if ($sisa['pcs'] <= 0) {
            return back()->with('error', 'Tidak ada kewajiban ganti yang tertunda pada batch ini.');
        }

        $data = $request->validate([
            'metode' => ['required', 'in:barang,refund'],
            'pcs' => ['required', 'integer', 'min:1', 'max:'.$sisa['pcs']],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $pcs = (int) $data['pcs'];
        // Nilai diprorata dari sisa saat ini (ukuran bisa beda harga).
        $nilaiDiferd = $data['metode'] === 'refund' ? (int) round($sisa['diferd'] * $pcs / $sisa['pcs']) : 0;
        $nilaiTm420 = $data['metode'] === 'refund' ? (int) round($sisa['tm420'] * $pcs / $sisa['pcs']) : 0;

        DB::transaction(function () use ($batch, $data, $pcs, $nilaiDiferd, $nilaiTm420) {
            \App\Models\CashGanti::create([
                'batch_id' => $batch->id,
                'brand_id' => $batch->brand_id,
                'metode' => $data['metode'],
                'pcs' => $pcs,
                'nilai_diferd' => $nilaiDiferd,
                'nilai_tm420' => $nilaiTm420,
                'tanggal' => now(),
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            if ($data['metode'] === 'refund') {
                // Diferd kembalikan uang → kurangi cash yang sudah dibayar (nilai negatif).
                VendorLedger::create([
                    'brand_id' => $batch->brand_id,
                    'batch_id' => $batch->id,
                    'tanggal' => now(),
                    'tipe' => LedgerTipe::Cash->value,
                    'jumlah' => -$nilaiDiferd,
                    'keterangan' => 'Refund reject cash batch '.$batch->nomor_batch.' — Diferd kembalikan '.$pcs.' pcs',
                ]);
                // 420F teruskan refund ke TM → kurangi tagihan/uang masuk TM (nilai negatif).
                // Keterangan diawali "Cash batch" agar tertangkap filter cash di Cashflow (fee &
                // transfer khusus net turun sesuai refund — 420F mengembalikan margin unit hantu).
                BrandLedger::create([
                    'brand_id' => $batch->brand_id,
                    'tanggal' => now(),
                    'jumlah' => -$nilaiTm420,
                    'keterangan' => 'Cash batch '.$batch->nomor_batch.' — refund reject ke TM ('.$pcs.' pcs)',
                ]);
            }
        });

        if ($data['metode'] === 'refund') {
            return back()->with('success', 'Refund reject dicatat: Diferd kembalikan Rp '.number_format($nilaiDiferd, 0, ',', '.')
                .', diteruskan ke TM Rp '.number_format($nilaiTm420, 0, ',', '.').' ('.$pcs.' pcs).');
        }

        return back()->with('success', $pcs.' pcs reject ditandai sudah diganti barang oleh Diferd.');
    }

    /**
     * Buy-out sisa stok batch di deadline. 420F membeli seluruh stok yang belum terjual; barangnya
     * JADI MILIK TM420 (keluar dari stok jual). Alurnya seperti TAGIHAN, bukan settle seketika:
     *   - Terbit INVOICE ke TM di harga tm420 → uang masuk 420F saat invoice ditandai lunas.
     *   - HAK DIFERD bertambah di harga diferd → ditutup belakangan lewat penarikan (bukan langsung).
     *   - 420F menyimpan margin (tm420 − diferd) begitu invoice dibayar.
     */
    public function buyout(Request $request, Batch $batch)
    {
        if ($batch->dibuyout) {
            return back()->with('error', 'Sisa stok batch ini sudah di-buy-out.');
        }

        $sisa = $this->settlement->sisaStok($batch);
        if ($sisa['pcs'] <= 0) {
            return back()->with('error', 'Tidak ada sisa stok untuk di-buy-out.');
        }

        $invoice = DB::transaction(function () use ($batch, $sisa) {
            // Hak Diferd bertambah (belum dibayar — masuk saldo settlement, ditutup via penarikan).
            VendorLedger::create([
                'brand_id' => $batch->brand_id,
                'batch_id' => $batch->id,
                'tanggal' => now(),
                'tipe' => LedgerTipe::Buyout->value,
                'jumlah' => $sisa['nilai'],
                'keterangan' => 'Buy-out '.$sisa['pcs'].' pcs sisa stok (hak Diferd)',
            ]);

            // Invoice resmi ke TM (harga tm420). Uang masuk 420F saat ditandai lunas (BrandLedger).
            $invoice = Invoice::create([
                'brand_id' => $batch->brand_id,
                'batch_id' => $batch->id,
                'nomor' => $this->invoiceNomor($batch->brand),
                'tanggal_terbit' => now(),
                'status' => 'belum_bayar',
                'jumlah_manual' => $sisa['nilai_tm'],
                'pcs_manual' => $sisa['pcs'],
                'catatan' => 'Buy-out sisa stok batch '.$batch->nomor_batch.' ('.$sisa['pcs'].' pcs)',
            ]);

            $batch->update(['dibuyout' => true, 'tgl_buyout' => now()]);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('success',
            'Buy-out '.$sisa['pcs'].' pcs — invoice '.$invoice->nomor.' Rp '.number_format($sisa['nilai_tm'], 0, ',', '.')
            .' terbit untuk TM; hak Diferd +Rp '.number_format($sisa['nilai'], 0, ',', '.').'. Stok jadi milik TM420.');
    }

    /** Nomor invoice (format sama dgn InvoiceController). */
    private function invoiceNomor(\App\Models\Brand $brand): string
    {
        $base = 'INV.'.BatchController::brandKode($brand).'.'.now()->format('m.y').'.';
        $n = Invoice::where('brand_id', $brand->id)->whereYear('tanggal_terbit', now()->year)->count() + 1;
        do {
            $nomor = $base.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $n++;
        } while (Invoice::where('nomor', $nomor)->exists());

        return $nomor;
    }

    public function destroyLedger(VendorLedger $ledger)
    {
        if ($ledger->penarikan_id) {
            return back()->with('error', 'Entri ini berasal dari penarikan #'.$ledger->penarikan_id.' — batalkan dari menu Penarikan, jangan dihapus di sini.');
        }

        $batch = $ledger->batch;

        // Buy-out dibatalkan → hapus juga invoice tagihannya (kecuali sudah lunas) & lepas flag.
        $invoiceBuyout = null;
        if ($ledger->tipe === LedgerTipe::Buyout && $batch) {
            $invoiceBuyout = Invoice::where('batch_id', $batch->id)->where('jumlah_manual', '>', 0)
                ->latest('id')->first();
            if ($invoiceBuyout && $invoiceBuyout->isLunas()) {
                return back()->with('error', 'Invoice buy-out '.$invoiceBuyout->nomor.' sudah lunas — batalkan pelunasannya dulu sebelum menghapus hak buy-out.');
            }
        }

        DB::transaction(function () use ($ledger, $batch, $invoiceBuyout) {
            if ($ledger->tipe === LedgerTipe::Buyout && $batch) {
                $invoiceBuyout?->delete();
                $batch->update(['dibuyout' => false, 'tgl_buyout' => null]);
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

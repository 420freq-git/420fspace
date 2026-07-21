<?php

use App\Models\Batch;
use App\Models\Penarikan;
use App\Models\VendorLedger;
use App\Services\SettlementService;
use Illuminate\Database\Migrations\Migration;

/**
 * Penarikan yang sudah cair sebelum fitur pembekuan alokasi ada belum punya baris ledger, jadi
 * kolom "dibayar" per batch masih 0. Migrasi ini membagikannya sekali secara FIFO — urutan sama
 * dengan yang dipakai saat persetujuan, jadi hasilnya identik dengan perhitungan sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settlement = app(SettlementService::class);

        $belum = Penarikan::where('status', 'disetujui')
            ->whereDoesntHave('alokasi')
            ->orderBy('tanggal_cair')->orderBy('id')->get();

        foreach ($belum as $penarikan) {
            $rencana = $settlement->rencanaAlokasi((int) $penarikan->jumlah);
            $batches = Batch::whereIn('id', array_keys($rencana['alokasi']))->get()->keyBy('id');

            foreach ($rencana['alokasi'] as $batchId => $jumlah) {
                VendorLedger::create([
                    'brand_id' => $batches[$batchId]->brand_id,
                    'batch_id' => $batchId,
                    'penarikan_id' => $penarikan->id,
                    'tanggal' => $penarikan->tanggal_cair ?? $penarikan->tanggal_ajuan,
                    'tipe' => 'pembayaran',
                    'jumlah' => $jumlah,
                    'keterangan' => 'Penarikan #'.$penarikan->id.' (alokasi awal)',
                ]);
            }
        }
    }

    public function down(): void
    {
        VendorLedger::whereNotNull('penarikan_id')->delete();
    }
};

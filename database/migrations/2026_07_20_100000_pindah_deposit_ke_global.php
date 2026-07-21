<?php

use App\Models\Batch;
use App\Models\VendorLedger;
use Illuminate\Database\Migrations\Migration;

/**
 * Deposit hanya terjadi sekali di awal kerja sama (keputusan user, 20 Jul 2026) — ia bukan
 * atribut batch. Migrasi ini melepas deposit dari batch: tiap deposit_awal yang belum
 * direkonsiliasi dipindah jadi baris vendor_ledger tipe deposit TANPA batch_id (modal mengendap
 * di vendor), lalu deposit_awal di-nol-kan. Deposit yang sudah direkonsiliasi dibiarkan —
 * itu sejarah yang sudah selesai.
 */
return new class extends Migration
{
    public function up(): void
    {
        $batches = Batch::where('deposit_awal', '>', 0)
            ->where('deposit_rekonsiliasi', false)->get();

        foreach ($batches as $batch) {
            VendorLedger::create([
                'brand_id' => $batch->brand_id,
                'batch_id' => null,
                'tanggal' => $batch->tanggal_order,
                'tipe' => 'deposit',
                'jumlah' => (int) $batch->deposit_awal,
                'keterangan' => 'Modal produksi awal kerja sama (dipindah dari '.$batch->nomor_batch.')',
            ]);

            $batch->update(['deposit_awal' => 0]);
        }
    }

    public function down(): void
    {
        // Tidak dibalik otomatis: baris pindahan dikenali dari keterangannya bila perlu manual.
    }
};

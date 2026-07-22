<?php

use App\Models\AuditLog;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill sebelumnya memakai tgl_diterima (kolom DATE → jam 00:00), sehingga riwayat produksi
     * menampilkan "tuntas 00:00" dan durasi tahap sebelumnya jadi janggal (mundur ke midnight).
     * Perbaiki: pakai waktu NYATA saat surat jalan ditandai diterima (pengiriman.updated_at).
     */
    public function up(): void
    {
        $entries = AuditLog::where('auditable_type', 'PurchaseOrder')
            ->where('user_name', 'sistem (backfill)')
            ->get();

        foreach ($entries as $log) {
            $po = PurchaseOrder::find($log->auditable_id);
            if (! $po) {
                continue;
            }

            $peng = Pengiriman::where('batch_id', $po->batch_id)
                ->where('status', 'diterima')
                ->whereHas('items', fn ($q) => $q->where('product_id', $po->product_id))
                ->latest('updated_at')->first();

            if ($peng && $peng->updated_at) {
                $log->forceFill(['created_at' => $peng->updated_at])->save();
            }
        }
    }

    public function down(): void
    {
        // Tak bisa dikembalikan ke midnight dengan akurat; dibiarkan (perbaikan data satu arah).
    }
};

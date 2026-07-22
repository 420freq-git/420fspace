<?php

use App\Models\AuditLog;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Dulu penerimaan menandai PO terkirim lewat MASS UPDATE (bypass event Eloquent) sehingga
     * transisi siap_kirim → terkirim TIDAK terekam di audit log. Riwayat produksi (dibangun dari
     * audit log) jadi mentok di "siap kirim" walau kolom tahap sudah "terkirim". Backfill di sini
     * membuatkan entri audit yang hilang, memakai tanggal terima surat jalan sebagai waktunya.
     */
    public function up(): void
    {
        $terkirim = PurchaseOrder::where('tahap', 'terkirim')->get();

        foreach ($terkirim as $po) {
            $sudah = AuditLog::where('auditable_type', 'PurchaseOrder')
                ->where('auditable_id', $po->id)
                ->get()
                ->contains(fn ($l) => ($l->changes['tahap'][1] ?? null) === 'terkirim');

            if ($sudah) {
                continue;
            }

            // Waktu = tanggal terima surat jalan batch ini yang memuat produk PO tsb.
            $tglTerima = Pengiriman::where('batch_id', $po->batch_id)
                ->where('status', 'diterima')
                ->whereHas('items', fn ($q) => $q->where('product_id', $po->product_id))
                ->max('tgl_diterima');

            $waktu = $tglTerima ?? $po->tahap_updated_at ?? $po->updated_at ?? now();

            AuditLog::create([
                'user_id' => null,
                'user_name' => 'sistem (backfill)',
                'user_role' => null,
                'event' => 'updated',
                'auditable_type' => 'PurchaseOrder',
                'auditable_id' => $po->id,
                'label' => $po->auditLabel(),
                'changes' => ['tahap' => ['siap_kirim', 'terkirim']],
            ])->forceFill(['created_at' => $waktu])->save();
        }
    }

    public function down(): void
    {
        AuditLog::where('auditable_type', 'PurchaseOrder')
            ->where('user_name', 'sistem (backfill)')
            ->delete();
    }
};

<?php

namespace App\Services;

use App\Enums\TahapProduksi;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use Illuminate\Support\Carbon;

/**
 * Menyusun riwayat durasi tiap tahap produksi sebuah PO dari audit log.
 *
 * Tidak ada tabel khusus: tiap perpindahan tahap sudah terekam sebagai entri audit
 * {"tahap": [lama, baru]} lengkap dengan waktu & pelakunya. Lama tinggal dihitung dari jarak
 * antar-perpindahan; tahap awal dihitung sejak PO dibuat.
 */
class TahapTimelineService
{
    /**
     * @return array{baris: array<int, array>, total_detik: int, selesai: bool}
     */
    public function untuk(PurchaseOrder $po): array
    {
        $logs = AuditLog::with('user')
            ->where('auditable_type', 'PurchaseOrder')
            ->where('auditable_id', $po->id)
            ->where('event', 'updated')
            ->orderBy('id')
            ->get()
            ->filter(fn ($l) => isset($l->changes['tahap']))
            ->values();

        $mulai = $po->created_at ?? now();
        // Tahap pertama = nilai "lama" pada perpindahan pertama; kalau belum pernah pindah,
        // berarti PO masih di tahap sekarang sejak dibuat.
        $tahapKini = $logs->isNotEmpty()
            ? TahapProduksi::tryFrom($logs->first()->changes['tahap'][0])
            : $po->tahap;

        $baris = [];

        foreach ($logs as $log) {
            [$lama, $baru] = $log->changes['tahap'];
            $selesai = $log->created_at;

            $baris[] = [
                'tahap' => TahapProduksi::tryFrom($lama) ?? $tahapKini,
                'mulai' => $mulai,
                'selesai' => $selesai,
                // abs(): di Carbon 3 diffInSeconds bertanda, arah terbalik menghasilkan negatif.
                'detik' => (int) abs($mulai->diffInSeconds($selesai)),
                'oleh' => $log->user_name ?: $log->user?->name,
                'berjalan' => false,
                'akhir' => false,
            ];

            $mulai = $selesai;
            $tahapKini = TahapProduksi::tryFrom($baru) ?? $tahapKini;
        }

        // Tahap terakhir: masih berjalan kecuali sudah terkirim.
        $sudahSelesai = $tahapKini === TahapProduksi::Terkirim;
        if (! $sudahSelesai) {
            $baris[] = [
                'tahap' => $tahapKini,
                'mulai' => $mulai,
                'selesai' => null,
                'detik' => (int) abs($mulai->diffInSeconds(now())),
                'oleh' => null,
                'berjalan' => true,
                'akhir' => false,
            ];
        } else {
            // Baris penutup: barang sudah TERKIRIM (diterima brand) — status akhir, tanpa durasi.
            // Tanpa ini timeline berhenti di "Siap Kirim" walau PO sudah selesai.
            $tuntas = $logs->last()?->created_at ?? $mulai;
            $baris[] = [
                'tahap' => TahapProduksi::Terkirim,
                'mulai' => $tuntas,
                'selesai' => $tuntas,
                'detik' => 0,
                'oleh' => $logs->last()?->user_name ?: $logs->last()?->user?->name,
                'berjalan' => false,
                'akhir' => true,
            ];
        }

        return [
            'baris' => $baris,
            'total_detik' => array_sum(array_column($baris, 'detik')),
            'selesai' => $sudahSelesai,
            'mulai' => $po->created_at,
            'tuntas_pada' => $sudahSelesai ? ($logs->last()?->created_at) : null,
        ];
    }

    /** "3 hari 4 jam" / "5 jam 12 menit" / "8 menit" — dibulatkan ke dua satuan terbesar. */
    public static function durasi(int $detik): string
    {
        if ($detik < 60) {
            return $detik.' detik';
        }

        $hari = intdiv($detik, 86400);
        $jam = intdiv($detik % 86400, 3600);
        $menit = intdiv($detik % 3600, 60);

        if ($hari > 0) {
            return $hari.' hari'.($jam > 0 ? ' '.$jam.' jam' : '');
        }
        if ($jam > 0) {
            return $jam.' jam'.($menit > 0 ? ' '.$menit.' menit' : '');
        }

        return $menit.' menit';
    }

    /** Tanggal mulai suatu tahap dipakai untuk menandai keterlambatan terhadap deadline. */
    public static function lewatDeadline(array $timeline, ?Carbon $deadline): bool
    {
        if (! $deadline || $timeline['selesai']) {
            return false;
        }

        return now()->greaterThan($deadline);
    }
}

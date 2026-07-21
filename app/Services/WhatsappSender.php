<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim WhatsApp via Fonnte (fonnte.com) — layanan WA API populer & murah di Indonesia.
 *
 * Aktif hanya bila FONNTE_TOKEN diset di .env. Tanpa token, pesan ditulis ke log (bisa diuji
 * tanpa kredensial). Ganti provider? cukup ubah kirim() di sini; pemanggilnya tak berubah.
 */
class WhatsappSender
{
    public function terkonfigurasi(): bool
    {
        return ! empty(config('services.fonnte.token'));
    }

    /**
     * @return array{ok:bool, status:string}
     */
    public function kirim(string $noHp, string $pesan): array
    {
        $target = $this->normalkan($noHp);
        if (! $target) {
            return ['ok' => false, 'status' => 'nomor kosong/invalid'];
        }

        if (! $this->terkonfigurasi()) {
            Log::info("[WA dry] ke {$target}:\n{$pesan}");

            return ['ok' => false, 'status' => 'FONNTE_TOKEN belum diset (ditulis ke log)'];
        }

        try {
            $res = Http::withHeaders(['Authorization' => config('services.fonnte.token')])
                ->asForm()->timeout(20)
                ->post('https://api.fonnte.com/send', ['target' => $target, 'message' => $pesan]);

            $ok = $res->successful() && ($res->json('status') ?? false) !== false;

            return ['ok' => $ok, 'status' => $ok ? 'terkirim' : 'gagal: '.$res->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error: '.$e->getMessage()];
        }
    }

    /** 08xxxx / +62xxxx / 62xxxx → 62xxxx. */
    private function normalkan(?string $no): ?string
    {
        $no = preg_replace('/[^0-9]/', '', (string) $no);
        if (! $no) {
            return null;
        }
        if (str_starts_with($no, '0')) {
            $no = '62'.substr($no, 1);
        } elseif (! str_starts_with($no, '62')) {
            $no = '62'.$no;
        }

        return $no;
    }
}

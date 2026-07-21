<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\WhatsappSender;
use Illuminate\Console\Command;

/**
 * Kirim reminder harian ke tiap pengguna atas hal yang perlu ditindak (deadline mepet, pesanan
 * perlu dicek, retur menunggu, penarikan, dll). Isi reminder diambil dari NotificationService
 * yang sudah sadar-peran, jadi tiap pihak hanya dapat urusannya sendiri.
 *
 * Jadwalkan harian; jalankan `--dry-run` untuk melihat isi tanpa mengirim.
 */
class KirimReminder extends Command
{
    protected $signature = 'app:kirim-reminder {--dry-run : Tampilkan pesan tanpa mengirim}';

    protected $description = 'Kirim reminder WhatsApp harian ke pengguna atas tugas yang tertunda';

    public function handle(NotificationService $notif, WhatsappSender $wa): int
    {
        $dry = (bool) $this->option('dry-run');
        if (! $dry && ! $wa->terkonfigurasi()) {
            $this->warn('FONNTE_TOKEN belum diset — pesan hanya ditulis ke log. Isi token di .env untuk mengirim nyata.');
        }

        $terkirim = 0; $dilewati = 0;

        foreach (User::all() as $user) {
            $items = collect($notif->for($user))->filter(fn ($i) => ($i['count'] ?? 0) > 0);
            if ($items->isEmpty()) {
                continue;   // tak ada yang perlu diingatkan
            }

            if (empty($user->no_hp)) {
                $this->line("  (lewati {$user->name}: belum ada no HP)");
                $dilewati++;

                continue;
            }

            $pesan = $this->susunPesan($user, $items);

            if ($dry) {
                $this->info("── {$user->name} ({$user->no_hp}) ──");
                $this->line($pesan);
                $terkirim++;

                continue;
            }

            $hasil = $wa->kirim($user->no_hp, $pesan);
            $hasil['ok'] ? $terkirim++ : $dilewati++;
            $this->line("  {$user->name}: {$hasil['status']}");
        }

        $this->newLine();
        $this->info($dry ? "{$terkirim} pengguna akan dapat reminder (dry-run)." : "Selesai: {$terkirim} terkirim, {$dilewati} dilewati.");

        return self::SUCCESS;
    }

    private function susunPesan(User $user, $items): string
    {
        $baris = $items->map(fn ($i) => '• '.$i['label'].': '.$i['count'])->implode("\n");

        return "*420Frequency — Pengingat*\n"
            ."Halo {$user->name}, ada yang perlu ditindak:\n\n"
            .$baris."\n\n"
            .'Buka aplikasi untuk detailnya.';
    }
}

<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProductController;
use App\Models\ProductFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Memperkecil gambar produk yang dimensinya berlebihan.
 *
 * Dipakai untuk file yang sudah terlanjur diunggah sebelum pembatasan dimensi ada. Gambar
 * beresolusi sangat tinggi membuat export PDF gagal (dompdf memuat bitmap utuh ke memori).
 */
class KecilkanGambarProduk extends Command
{
    protected $signature = 'produk:kecilkan-gambar {--maks=2000 : Batas sisi terpanjang (px)} {--dry-run : Hanya tampilkan, tidak mengubah}';

    protected $description = 'Perkecil gambar produk yang dimensinya di atas batas';

    public function handle(): int
    {
        $maks = (int) $this->option('maks');
        $dry = (bool) $this->option('dry-run');
        $diubah = 0;

        foreach (ProductFile::with('product')->get() as $file) {
            $abs = Storage::disk('public')->path($file->path);
            if (! is_file($abs)) {
                $this->warn("hilang: {$file->path}");

                continue;
            }

            $info = @getimagesize($abs);
            if (! $info || ($info[0] <= $maks && $info[1] <= $maks)) {
                continue;
            }

            $mbSebelum = round($info[0] * $info[1] * 4 / 1048576, 1);
            $this->line(sprintf(
                '%s · %s — %dx%d px (±%s MB di memori)',
                $file->product?->nama_artikel ?? '?', $file->nama_asli, $info[0], $info[1], $mbSebelum
            ));

            if ($dry) {
                $diubah++;

                continue;
            }

            if (ProductController::kecilkanGambar($abs, $maks)) {
                $baru = @getimagesize($abs);
                $this->info(sprintf('  → %dx%d px', $baru[0], $baru[1]));
                $diubah++;
            }
        }

        $this->newLine();
        $this->info($dry ? "{$diubah} gambar perlu diperkecil." : "{$diubah} gambar diperkecil.");

        return self::SUCCESS;
    }
}

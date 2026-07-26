<?php

namespace Tests\Feature\Erp;

use App\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ErpTestCase;

/**
 * Radar deadline menampilkan pergerakan batch (terjual dari diterima), bukan hanya sisa.
 * Diuji untuk TM & VOOJAH — POV brand identik kecuali harga.
 */
class RadarPergerakanTest extends ErpTestCase
{
    public static function brandProvider(): array
    {
        return ['TM420' => ['produkTm', 'tm'], 'VOOJAH' => ['produkVoojah', 'voojah']];
    }

    #[DataProvider('brandProvider')]
    public function test_pergerakan_batch_akurat_di_radar(string $produkProp, string $userProp): void
    {
        /** @var Product $produk */
        $produk = $this->{$produkProp};
        $user = $this->{$userProp};

        // Terima 10, jual 4 → diterima 10, terjual 4, sisa 6.
        $batch = $this->batchAktif($produk, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($produk, 'M', 4);

        $g = $this->stock()->pergerakanBatch($batch->fresh());
        $this->assertSame(10, $g['diterima']);
        $this->assertSame(4, $g['terjual']);
        $this->assertSame(6, $g['sisa']);

        // Halaman radar brand ybs menampilkan kolom & angka pergerakan.
        $this->actingAs($user)->get(route('radar.index'))
            ->assertOk()
            ->assertSee('Terjual / Diterima')
            ->assertSee('4 / 10');
    }

    #[DataProvider('brandProvider')]
    public function test_sisa_konsisten_diterima_minus_terjual(string $produkProp, string $userProp): void
    {
        $produk = $this->{$produkProp};
        $batch = $this->batchAktif($produk, ['M' => 10, 'L' => 5]);
        $this->produksiTerima($batch);
        $this->jual($produk, 'M', 3);

        $g = $this->stock()->pergerakanBatch($batch->fresh());
        // diterima 15, terjual 3, sisa 12 — dan sisa = diterima − terjual.
        $this->assertSame($g['diterima'] - $g['terjual'], $g['sisa']);
        $this->assertSame(15, $g['diterima']);
        $this->assertSame(3, $g['terjual']);
    }
}

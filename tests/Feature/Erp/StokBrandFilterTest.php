<?php

namespace Tests\Feature\Erp;

use Tests\ErpTestCase;

/**
 * Filter brand di halaman Stok: 420F & Diferd bisa memantau stok per brand + total,
 * tanpa menu sidebar baru. TM/VOOJAH tetap terkunci ke brand-nya (tak ada tab).
 */
class StokBrandFilterTest extends ErpTestCase
{
    private function stokBerisi(): void
    {
        // Sedikit stok untuk kedua brand agar tab muncul.
        $this->produksiTerima($this->batchAktif($this->produkTm, ['M' => 10]));
        $this->produksiTerima($this->batchAktif($this->produkVoojah, ['M' => 10]));
    }

    public function test_diferd_melihat_tab_brand(): void
    {
        $this->stokBerisi();
        $this->actingAs($this->diferd)->get(route('stok.index'))
            ->assertOk()->assertSee('Semua brand')->assertSee('TM420')->assertSee('VOOJAH');
    }

    public function test_filter_brand_membatasi_produk(): void
    {
        $this->stokBerisi();

        $html = $this->actingAs($this->diferd)
            ->get(route('stok.index', ['brand' => $this->brandVoojah->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString($this->produkVoojah->nama_artikel, $html);
        $this->assertStringNotContainsString($this->produkTm->nama_artikel, $html);
    }

    public function test_voojah_tidak_dapat_tab_dan_terkunci_brand(): void
    {
        $this->stokBerisi();

        $html = $this->actingAs($this->voojah)->get(route('stok.index'))
            ->assertOk()->assertDontSee('Semua brand')->getContent();

        // Hanya produk brand sendiri; tak bisa mengintip brand lain lewat query param.
        $htmlIntip = $this->actingAs($this->voojah)
            ->get(route('stok.index', ['brand' => $this->brandTm->id]))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString($this->produkTm->nama_artikel, $htmlIntip);
    }
}

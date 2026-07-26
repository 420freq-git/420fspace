<?php

namespace Tests\Feature\Erp;

use Tests\ErpTestCase;

/**
 * Visibilitas harga per POV di halaman produk.
 *
 * - TM420 : hanya harga retail (TM420) — tak pernah lihat modal (diferd).
 * - VOOJAH: hanya harga modal (diferd) — retail tak relevan karena ditagih modal, fee 420F = 0.
 * - 420F  : keduanya + markup.
 */
class HargaVisibilitasTest extends ErpTestCase
{
    public function test_voojah_tidak_melihat_kolom_tm420_di_detail_produk(): void
    {
        $html = $this->actingAs($this->voojah)
            ->get(route('products.show', $this->produkVoojah))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('>TM420<', $html, 'VOOJAH tak boleh melihat kolom TM420.');
        $this->assertStringNotContainsString('>Markup<', $html);
        $this->assertStringContainsString('>Diferd<', $html, 'VOOJAH tetap melihat harga modal (yang ia bayar).');
    }

    public function test_tm420_melihat_tm420_bukan_diferd(): void
    {
        $html = $this->actingAs($this->tm)
            ->get(route('products.show', $this->produkTm))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>TM420<', $html);
        $this->assertStringNotContainsString('>Diferd<', $html, 'TM420 tak boleh melihat harga modal.');
    }

    public function test_admin_melihat_keduanya(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('products.show', $this->produkVoojah))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Diferd<', $html);
        $this->assertStringContainsString('>TM420<', $html);
        $this->assertStringContainsString('>Markup<', $html);
    }

    public function test_daftar_produk_voojah_tanpa_kolom_tm420(): void
    {
        $html = $this->actingAs($this->voojah)
            ->get(route('products.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Diferd → TM420', $html);
    }

    public function test_voojah_simpan_produk_tidak_menghapus_override_tm420_yang_ada(): void
    {
        // 420F set override tm420, lalu VOOJAH menyimpan produk (tanpa mengirim field tm420).
        // Nilai tm420 lama harus dipertahankan, bukan jadi null.
        $this->produkVoojah->update(['harga_tm420_sxl_override' => 99000]);

        $this->actingAs($this->voojah)
            ->put(route('products.update', $this->produkVoojah), [
                'brand_id' => $this->produkVoojah->brand_id,
                'category_id' => $this->produkVoojah->category_id,
                'nama_artikel' => $this->produkVoojah->nama_artikel,
                'sku_induk' => $this->produkVoojah->sku_induk,
                'aktif' => 1,
                'harga_khusus' => 1,
                'harga_diferd_sxl_override' => 55000,
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->produkVoojah->refresh();
        $this->assertSame(99000, (int) $this->produkVoojah->harga_tm420_sxl_override, 'Override tm420 lama tak boleh terhapus saat VOOJAH menyimpan.');
        $this->assertSame(55000, (int) $this->produkVoojah->harga_diferd_sxl_override);
    }
}

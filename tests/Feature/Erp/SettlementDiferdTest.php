<?php

namespace Tests\Feature\Erp;

use Tests\ErpTestCase;

/**
 * Halaman Settlement untuk Diferd: bisa memantau pergerakan tiap batch (terjual/sisa qty +
 * nilai/dibayar), TAPI tidak boleh melihat Fee/margin 420F (bukan urusan vendor — CLAUDE.md §9).
 */
class SettlementDiferdTest extends ErpTestCase
{
    private function batchTerjualSebagian(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 4);   // 4 terjual, 6 sisa
    }

    public function test_diferd_lihat_pergerakan_tapi_tidak_lihat_fee(): void
    {
        $this->batchTerjualSebagian();

        $html = $this->actingAs($this->diferd)->get(route('settlement.index'))
            ->assertOk()
            ->assertSee('Terjual / Terkirim')      // kolom pergerakan ada
            ->getContent();

        $this->assertStringNotContainsString('Fee 420F', $html, 'Diferd tak boleh melihat fee/margin 420F.');
    }

    public function test_admin_tetap_lihat_fee(): void
    {
        $this->batchTerjualSebagian();

        $this->actingAs($this->admin)->get(route('settlement.index'))
            ->assertOk()
            ->assertSee('Fee 420F')
            ->assertSee('Terjual / Terkirim');
    }

    public function test_teks_ikut_pov_diferd(): void
    {
        $this->batchTerjualSebagian();

        // Diferd melihat teks dari sudut pandangnya sendiri, bukan bahasa 420F.
        $htmlDiferd = $this->actingAs($this->diferd)->get(route('settlement.index'))
            ->assertOk()
            ->assertSee('Hak Anda')
            ->assertSee('Sudah Anda terima')
            ->assertSee('Sisa hak Anda')
            ->getContent();
        $this->assertStringNotContainsString('Saldo ke Diferd', $htmlDiferd);
        $this->assertStringNotContainsString('perlu dibayar', $htmlDiferd);

        // 420F tetap memakai bahasa kewajiban ke Diferd.
        $this->actingAs($this->admin)->get(route('settlement.index'))
            ->assertOk()
            ->assertSee('Saldo ke Diferd')
            ->assertSee('sisa hak yang perlu dibayar');
    }

    public function test_detail_batch_diferd_tanpa_fee(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);
        $this->jual($this->produkTm, 'M', 4);

        $html = $this->actingAs($this->diferd)->get(route('settlement.show', $batch))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('Fee 420F', $html);
        $this->assertStringNotContainsString('Margin 420F', $html);
    }
}

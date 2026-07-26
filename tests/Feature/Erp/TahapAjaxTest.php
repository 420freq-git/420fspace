<?php

namespace Tests\Feature\Erp;

use App\Enums\TahapProduksi;
use App\Models\PurchaseOrder;
use Tests\ErpTestCase;

/**
 * Ganti tahap produksi tanpa reload halaman.
 *
 * Dropdown tahap dulu men-submit form biasa sehingga seluruh halaman dimuat ulang tiap kali
 * (mengganggu saat memajukan banyak PO). Sekarang klien mem-PATCH lewat fetch dan server
 * mengembalikan baris PO yang sudah dirender ulang. Test ini mengunci kontrak respons itu —
 * kalau berubah, JavaScript di resources/js/app.js ikut rusak diam-diam.
 */
class TahapAjaxTest extends ErpTestCase
{
    private function poPertama(): array
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10, 'L' => 5]);
        $po = PurchaseOrder::where('batch_id', $batch->id)->firstOrFail();

        return [$batch, $po];
    }

    public function test_patch_json_mengembalikan_baris_yang_dirender_server(): void
    {
        [$batch, $po] = $this->poPertama();

        $res = $this->actingAs($this->admin)
            ->patchJson(route('purchase-orders.status', [$batch, $po]), ['tahap' => 'sablon_massal']);

        $res->assertOk()
            ->assertJson([
                'ok' => true,
                'po_id' => $po->id,
                'batch_id' => $batch->id,
            ])
            ->assertJsonStructure(['ok', 'message', 'po_id', 'batch_id', 'progress', 'selesai', 'row_html']);

        // Tahap benar-benar tersimpan.
        $this->assertSame('sablon_massal', $po->fresh()->tahap->value);

        // Baris yang dikembalikan memuat penanda yang dipakai JS untuk menukar baris,
        // dan sudah menampilkan label tahap yang BARU (bukan yang lama).
        $html = $res->json('row_html');
        $this->assertStringContainsString('data-po-row="'.$po->id.'"', $html);
        $this->assertStringContainsString('data-tahap-select', $html);
        $this->assertStringContainsString(TahapProduksi::from('sablon_massal')->label(), $html);
    }

    public function test_progress_batch_ikut_dihitung_ulang(): void
    {
        [$batch, $po] = $this->poPertama();

        $awal = $this->actingAs($this->admin)
            ->patchJson(route('purchase-orders.status', [$batch, $po]), ['tahap' => 'belanja_bahan'])
            ->json('progress');

        $maju = $this->actingAs($this->admin)
            ->patchJson(route('purchase-orders.status', [$batch, $po]), ['tahap' => 'siap_kirim'])
            ->json('progress');

        $this->assertGreaterThan($awal, $maju, 'Progress batch harus naik saat tahap PO dimajukan.');
    }

    public function test_selesai_false_selama_masih_ada_yang_menunggu_surat_jalan(): void
    {
        [$batch, $po] = $this->poPertama();

        $res = $this->actingAs($this->admin)
            ->patchJson(route('purchase-orders.status', [$batch, $po]), ['tahap' => 'siap_kirim']);

        // Semua PO siap_kirim, tapi belum ada surat jalan → batch BELUM selesai.
        $res->assertJson(['selesai' => false]);
    }

    public function test_permintaan_biasa_tetap_redirect_seperti_semula(): void
    {
        [$batch, $po] = $this->poPertama();

        $this->actingAs($this->admin)
            ->from(route('monitoring-produksi.index'))
            ->patch(route('purchase-orders.status', [$batch, $po]), ['tahap' => 'proofing'])
            ->assertRedirect(route('monitoring-produksi.index'));

        $this->assertSame('proofing', $po->fresh()->tahap->value);
    }

    public function test_tm_tidak_boleh_mengubah_tahap(): void
    {
        [$batch, $po] = $this->poPertama();

        $this->actingAs($this->tm)
            ->patchJson(route('purchase-orders.status', [$batch, $po]), ['tahap' => 'siap_kirim'])
            ->assertForbidden();

        $this->assertNotSame('siap_kirim', $po->fresh()->tahap->value);
    }

    public function test_halaman_monitoring_memuat_kaitan_untuk_javascript(): void
    {
        [$batch, $po] = $this->poPertama();

        $this->actingAs($this->admin)
            ->get(route('monitoring-produksi.index'))
            ->assertOk()
            ->assertSee('data-tahap-select', false)
            ->assertSee('data-po-row="'.$po->id.'"', false)
            ->assertSee('data-batch-progress="'.$batch->id.'"', false);
    }
}

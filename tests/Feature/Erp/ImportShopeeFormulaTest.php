<?php

namespace Tests\Feature\Erp;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\ErpTestCase;

/**
 * File income Shopee menaruh baris ringkasan berisi rumus `==SUM(INDIRECT(...))` — dobel '='
 * dan tidak valid. Kalau importer menghitung rumus saat membaca, PhpSpreadsheet melempar
 * "Unexpected operator '='" dan SELURUH impor settlement gagal (di produksi: layar 500 kosong).
 *
 * Importer hanya butuh nilai mentah, jadi rumus tak boleh dihitung sama sekali.
 */
class ImportShopeeFormulaTest extends ErpTestCase
{
    private function fileIncomeShopee(string $nomorPesanan): UploadedFile
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Income');

        // Blok metadata seperti export asli Shopee.
        $sheet->fromArray([['Username (Penjual)', 'Dari', 'ke']], null, 'A1');
        $sheet->fromArray([['tm420.id', '2026-04-15', '2026-05-15']], null, 'A2');

        // Baris ringkasan bermasalah — inilah pemicu bug aslinya.
        $sheet->setCellValue('A5', 'total(Rp)');
        $sheet->setCellValue('H5', '==SUM(INDIRECT(ADDRESS(6,8)):INDIRECT(ADDRESS(1048576,8)))');

        $sheet->fromArray([['No.', 'No. Pesanan', 'Tanggal Dana Dilepaskan']], null, 'A6');
        $sheet->fromArray([[1, $nomorPesanan, '2026-05-15']], null, 'A7');

        $path = tempnam(sys_get_temp_dir(), 'inc').'.xlsx';
        $writer = new XlsxWriter($ss);
        // Rumusnya memang sengaja tak valid — jangan dihitung saat menulis, sama seperti file
        // asli Shopee yang menyimpan rumus apa adanya tanpa nilai ter-cache yang benar.
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);

        return new UploadedFile($path, 'income.xlsx', null, null, true);
    }

    public function test_settlement_shopee_berumus_cacat_tetap_terbaca(): void
    {
        $batch = $this->batchAktif($this->produkTm, ['M' => 10]);
        $this->produksiTerima($batch);

        // Pesanan marketplace yang belum cair.
        $order = $this->jual($this->produkTm, 'M', 2, 'shopee');
        $order->update(['status' => 'dipesan']);
        $this->assertSame('dipesan', $order->fresh()->status->value);

        $hasil = app(\App\Services\MarketplaceImportService::class)
            ->importSettlement($this->fileIncomeShopee($order->nomor_pesanan));

        $this->assertArrayNotHasKey('error', $hasil, 'Rumus cacat tak boleh menggagalkan impor.');
        $this->assertSame('Shopee', $hasil['marketplace']);
        $this->assertSame(1, $hasil['cair'], 'Pesanan yang dananya dilepas harus jadi lunas.');
        $this->assertSame('lunas', $order->fresh()->status->value);
    }

    public function test_file_rusak_memberi_pesan_bukan_error_500(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        file_put_contents($path, 'ini bukan excel sama sekali');
        $file = new UploadedFile($path, 'rusak.xlsx', null, null, true);

        $svc = app(\App\Services\MarketplaceImportService::class);

        // Tak boleh melempar exception — harus mengembalikan pesan yang bisa ditindaklanjuti.
        $this->assertArrayHasKey('error', $svc->importSettlement($file));
        $this->assertArrayHasKey('error', $svc->import($file));
    }
}

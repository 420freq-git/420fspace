<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien BACA ke API ERP TM420 (`erp.tm420.id/api/420f`).
 *
 * Bedakan dari {@see ErpPesananClient}, yang menarik dari ERP 420F. Dua sistem
 * berbeda, dua token berbeda, dan isinya pun beda: yang ini membawa penjualan
 * TM atas barang yang DIPRODUKSI di sistem ini — dasar tagihan ongkos produksi
 * dari 420F ke TM.
 *
 * ## Kenapa harus dari sana
 *
 * Pemicu TM wajib bayar adalah settlement yang CAIR, bukan barang yang terkirim.
 * Hanya ERP TM yang tahu kapan uang marketplace turun, dan itu baru diketahui
 * setelah berkas settlement diunggah di sana. Menebaknya dari tanggal kirim
 * berarti menagih penjualan yang uangnya belum tentu jadi.
 *
 * Baca-saja. Tidak ada rute yang mengubah data di ERP TM — pemasok tidak boleh
 * bisa mengubah catatan penjualan yang jadi dasar tagihannya sendiri.
 */
class ErpTmPenjualanClient
{
    private function req(): PendingRequest
    {
        $token = (string) config('integrasi.tm_api_token');

        if ($token === '') {
            throw new RuntimeException(
                'Token API ERP TM420 belum diisi (ERP_TM_API_TOKEN). Tarikan dihentikan — '
                .'daftar kosong karena tak berizin tidak bisa dibedakan dari "memang belum ada penjualan".'
            );
        }

        return Http::baseUrl((string) config('integrasi.tm_api_base_url'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(30);
    }

    /** @return array<string, mixed> */
    public function ping(): array
    {
        return $this->get('/ping');
    }

    /**
     * Penjualan TM dalam satu periode.
     *
     * Bawaannya `cair`: hanya yang uangnya sudah turun yang boleh ditagih.
     * Periodenya disaring ERP menurut TANGGAL CAIR, bukan tanggal pesanan —
     * itulah yang menentukan kapan sebuah baris masuk periode tagihan.
     *
     * @return array<string, mixed>  seluruh badan balasan: baris + ringkas
     */
    public function penjualan(string $dari, string $sampai, string $status = 'cair'): array
    {
        return $this->get('/penjualan', ['dari' => $dari, 'sampai' => $sampai, 'status' => $status], utuh: true);
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query = [], bool $utuh = false): array
    {
        try {
            $resp = $this->req()->get($path, $query);
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal menghubungi ERP TM420: '.$e->getMessage());
        }

        if ($resp->status() === 401) {
            throw new RuntimeException('Token ditolak ERP TM420 (401).');
        }

        if ($resp->status() === 422) {
            throw new RuntimeException('ERP TM420 menolak parameter: '.($resp->json('message') ?? 'tidak sah').'.');
        }

        if ($resp->failed()) {
            throw new RuntimeException('ERP TM420 membalas error HTTP '.$resp->status().'.');
        }

        $json = $resp->json();

        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            throw new RuntimeException('Respons ERP TM420 tidak valid: '.($json['message'] ?? 'tak dikenal'));
        }

        return $utuh ? $json : ($json['data'] ?? []);
    }
}

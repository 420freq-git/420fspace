<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien BACA ke API ERP 420F. Menarik pesanan marketplace (VOOJAH/420F) hasil import file
 * di ERP, untuk dibuat Order+Sale & potong stok di produksi. Read-only ke ERP.
 */
class ErpPesananClient
{
    private function req(): PendingRequest
    {
        return Http::baseUrl(config('integrasi.erp_base_url'))
            ->withToken((string) config('integrasi.erp_api_token'))
            ->acceptJson()
            ->timeout(20);
    }

    public function ping(): array
    {
        return $this->get('/ping');
    }

    /** Daftar pesanan marketplace dari ERP. */
    public function pesananMarketplace(?string $dari = null, ?string $sampai = null, bool $cairSaja = false): array
    {
        return $this->get('/pesanan-marketplace', array_filter([
            'dari' => $dari, 'sampai' => $sampai, 'cair' => $cairSaja ? 1 : null,
        ]));
    }

    private function get(string $path, array $query = []): array
    {
        try {
            $resp = $this->req()->get($path, $query);
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal menghubungi ERP 420F: '.$e->getMessage());
        }

        if ($resp->status() === 401) {
            throw new RuntimeException('Token integrasi ditolak ERP (401).');
        }
        if ($resp->failed()) {
            throw new RuntimeException('ERP membalas error HTTP '.$resp->status().'.');
        }

        $json = $resp->json();
        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            throw new RuntimeException('Respons ERP tidak valid: '.($json['message'] ?? 'tak dikenal'));
        }

        return $json['data'] ?? [];
    }
}

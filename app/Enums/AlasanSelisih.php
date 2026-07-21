<?php

namespace App\Enums;

/**
 * Alasan barang yang sampai lebih sedikit dari yang seharusnya.
 *
 * Dua titik selisih yang berbeda, masing-masing punya pilihan sendiri:
 *  - saat Diferd mengirim kurang dari PO  → Reject / ProduksiKurang
 *  - saat brand menerima kurang dari yang dikirim → Reject / TidakAda
 *
 * Semua jenis sama-sama ditanggung vendor; pembedaan ini untuk pelacakan sebabnya.
 */
enum AlasanSelisih: string
{
    case Reject = 'reject';                     // barang jadi tapi gagal QC
    case ProduksiKurang = 'produksi_kurang';    // memang tidak sempat/tidak jadi diproduksi
    case TidakAda = 'tidak_ada';                // tidak ada di paket saat diterima

    /** Pilihan sah saat vendor membuat surat jalan kurang dari PO. */
    public static function untukKirim(): array
    {
        return [self::Reject, self::ProduksiKurang];
    }

    /** Pilihan sah saat brand menerima kurang dari yang dikirim. */
    public static function untukTerima(): array
    {
        return [self::Reject, self::TidakAda];
    }

    public function label(): string
    {
        return match ($this) {
            self::Reject => 'Reject (gagal QC)',
            self::ProduksiKurang => 'Produksi kurang',
            self::TidakAda => 'Tidak ada di paket',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Reject => 'Barang jadi tapi tidak lolos kontrol kualitas.',
            self::ProduksiKurang => 'Jumlah yang diproduksi memang tidak mencapai PO.',
            self::TidakAda => 'Tercatat di surat jalan tapi barangnya tidak ada saat dibuka.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Reject => 'bg-red-100 text-red-700',
            self::ProduksiKurang => 'bg-amber-100 text-amber-800',
            self::TidakAda => 'bg-sand-200 text-sand-800',
        };
    }
}

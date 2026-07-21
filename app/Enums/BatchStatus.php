<?php

namespace App\Enums;

/**
 * Alur batch: TM420 mengajukan → 420F menyetujui → vendor mengerjakan → lunas.
 * Batch yang dibuat langsung oleh 420F lahir Aktif (tidak perlu menyetujui diri sendiri).
 */
enum BatchStatus: string
{
    case Menunggu = 'menunggu';   // diajukan TM420, vendor belum melihatnya
    case Aktif = 'aktif';         // disetujui & diteruskan ke vendor
    case Lunas = 'lunas';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu persetujuan',
            self::Aktif => 'Aktif',
            self::Lunas => 'Lunas',
            self::Ditolak => 'Ditolak',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-blue-100 text-blue-800',
            self::Aktif => 'bg-amber-100 text-amber-800',
            self::Lunas => 'bg-emerald-100 text-emerald-800',
            self::Ditolak => 'bg-red-100 text-red-700',
        };
    }

    /** Belum jadi pekerjaan vendor — tidak boleh terlihat oleh Diferd. */
    public function belumDisetujui(): bool
    {
        return $this === self::Menunggu || $this === self::Ditolak;
    }
}

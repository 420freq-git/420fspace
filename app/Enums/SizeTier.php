<?php

namespace App\Enums;

enum SizeTier: string
{
    case SXL = 's_xl';  // S sampai XL
    case XXL = 'xxl';   // XXL ke atas

    public function label(): string
    {
        return match ($this) {
            self::SXL => 'S–XL',
            self::XXL => 'XXL',
        };
    }

    /** Tier harga untuk ukuran tertentu. */
    public static function forUkuran(string $ukuran): self
    {
        return strtoupper($ukuran) === 'XXL' ? self::XXL : self::SXL;
    }
}

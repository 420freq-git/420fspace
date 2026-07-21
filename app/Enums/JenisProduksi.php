<?php

namespace App\Enums;

enum JenisProduksi: string
{
    case Pendek = 'pendek';
    case Panjang = 'panjang';
    case Raglan34 = 'raglan_34';
    case Lekbong = 'lekbong';

    public function label(): string
    {
        return match ($this) {
            self::Pendek => 'Pendek',
            self::Panjang => 'Panjang',
            self::Raglan34 => 'Raglan ¾',
            self::Lekbong => 'Lekbong',
        };
    }
}

<?php

namespace App\Enums;

enum ProduksiStatus: string
{
    case Antri = 'antri';
    case Produksi = 'produksi';
    case Selesai = 'selesai';
    case Dikirim = 'dikirim';

    public function label(): string
    {
        return match ($this) {
            self::Antri => 'Antri',
            self::Produksi => 'Produksi',
            self::Selesai => 'Selesai produksi',
            self::Dikirim => 'Dikirim ke gudang',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Antri => 'bg-sand-100 text-sand-700',
            self::Produksi => 'bg-blue-100 text-blue-800',
            self::Selesai => 'bg-emerald-100 text-emerald-800',
            self::Dikirim => 'bg-brand-100 text-brand-800',
        };
    }
}

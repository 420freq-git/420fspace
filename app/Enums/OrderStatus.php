<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Dipesan = 'dipesan';   // baru masuk (upload/ input manual)
    case Dikirim = 'dikirim';   // sudah dikirim — stok "di jalan"
    case Lunas = 'lunas';       // settlement cair — terjual final
    case Retur = 'retur';       // paket ditolak — barang retur belum sampai
    case Batal = 'batal';       // retur sudah diterima kembali

    public function label(): string
    {
        return match ($this) {
            self::Dipesan => 'Dipesan',
            self::Dikirim => 'Dikirim',
            self::Lunas => 'Lunas',
            self::Retur => 'Dibatalkan · retur di jalan',
            self::Batal => 'Batal · retur diterima',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Dipesan => 'bg-sand-200 text-sand-800',
            self::Dikirim => 'bg-blue-100 text-blue-800',
            self::Lunas => 'bg-emerald-100 text-emerald-800',
            self::Retur => 'bg-amber-100 text-amber-800',
            self::Batal => 'bg-red-100 text-red-800',
        };
    }
}

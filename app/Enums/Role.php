<?php

namespace App\Enums;

enum Role: string
{
    case Admin = '420f';   // 420Frequency — pemilik sistem, akses penuh
    case Tm420 = 'tm420';  // Brand eksternal TM420
    case Voojah = 'voojah'; // Brand internal VOOJAH
    case Diferd = 'difred'; // Vendor tunggal

    public function label(): string
    {
        return match ($this) {
            self::Admin => '420F · Admin',
            self::Tm420 => 'TM420 · Brand',
            self::Voojah => 'VOOJAH · Brand',
            self::Diferd => 'Diferd · Vendor',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-emerald-100 text-emerald-800',
            self::Tm420 => 'bg-blue-100 text-blue-800',
            self::Voojah => 'bg-indigo-100 text-indigo-800',
            self::Diferd => 'bg-amber-100 text-amber-800',
        };
    }
}

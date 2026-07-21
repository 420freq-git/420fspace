<?php

namespace App\Enums;

enum BrandType: string
{
    case Eksternal = 'eksternal';       // brand mitra (TM420) — 420F penengah, ambil fee markup
    case MilikSendiri = 'milik_sendiri'; // brand milik 420F (VOOJAH) — ambil margin penuh

    public function label(): string
    {
        return match ($this) {
            self::Eksternal => 'Eksternal (mitra)',
            self::MilikSendiri => 'Milik sendiri',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Eksternal => '420F sebagai penengah; brand membayar harga TM420, 420F ambil markup.',
            self::MilikSendiri => 'Brand milik 420F; hanya harga Diferd yang dilacak, margin penuh.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Eksternal => 'bg-blue-100 text-blue-800',
            self::MilikSendiri => 'bg-emerald-100 text-emerald-800',
        };
    }
}

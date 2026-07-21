<?php

namespace App\Enums;

enum ProductFileType: string
{
    case Mockup = 'mockup';    // tampak depan & belakang
    case Desain = 'desain';    // artwork/detail desain
    case Mentahan = 'mentahan'; // file produksi mentah (AI/PSD/PDF/dll) untuk vendor

    public function label(): string
    {
        return match ($this) {
            self::Mockup => 'Mockup',
            self::Desain => 'Desain',
            self::Mentahan => 'File mentahan',
        };
    }
}

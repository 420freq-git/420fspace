<?php

namespace App\Enums;

enum TypePayment: string
{
    case Termin = 'termin';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Termin => 'Termin',
            self::Cash => 'Cash',
        };
    }
}

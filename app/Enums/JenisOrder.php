<?php

namespace App\Enums;

enum JenisOrder: string
{
    case FullOrder = 'full_order';
    case Restock = 'restock';
    case Sample = 'sample';

    public function label(): string
    {
        return match ($this) {
            self::FullOrder => 'Full Order',
            self::Restock => 'Restock',
            self::Sample => 'Sample',
        };
    }
}

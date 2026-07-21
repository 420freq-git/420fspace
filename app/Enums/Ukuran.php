<?php

namespace App\Enums;

enum Ukuran: string
{
    case S = 'S';
    case M = 'M';
    case L = 'L';
    case XL = 'XL';
    case XXL = 'XXL';

    public function tier(): SizeTier
    {
        return SizeTier::forUkuran($this->value);
    }
}

<?php

namespace App\Models;

use App\Enums\SizeTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'aktif'])]
class Category extends Model
{
    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CategoryPrice::class);
    }

    /** Ambil baris harga untuk tier tertentu. */
    public function priceFor(SizeTier $tier): ?CategoryPrice
    {
        return $this->prices->firstWhere('size_tier', $tier->value);
    }
}

<?php

namespace App\Models;

use App\Enums\BrandType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'tipe', 'kode', 'aktif'])]
class Brand extends Model
{
    protected function casts(): array
    {
        return [
            'tipe' => BrandType::class,
            'aktif' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

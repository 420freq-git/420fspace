<?php

namespace App\Models;

use App\Enums\SizeTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'size_tier', 'harga_diferd', 'harga_tm420'])]
class CategoryPrice extends Model
{
    protected function casts(): array
    {
        return [
            'size_tier' => SizeTier::class,
            'harga_diferd' => 'integer',
            'harga_tm420' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Markup 420F = harga TM420 − harga Diferd. */
    public function getMarkupAttribute(): ?int
    {
        return $this->harga_tm420 === null ? null : $this->harga_tm420 - $this->harga_diferd;
    }
}

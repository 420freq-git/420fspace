<?php

namespace App\Models;

use App\Enums\Ukuran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'ukuran', 'sku_turunan'])]
class ProductSize extends Model
{
    protected function casts(): array
    {
        return ['ukuran' => Ukuran::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

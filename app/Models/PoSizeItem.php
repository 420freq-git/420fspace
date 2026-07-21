<?php

namespace App\Models;

use App\Enums\JenisProduksi;
use App\Enums\Ukuran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_id', 'ukuran', 'jenis', 'qty'])]
class PoSizeItem extends Model
{
    protected function casts(): array
    {
        return [
            'ukuran' => Ukuran::class,
            'jenis' => JenisProduksi::class,
            'qty' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}

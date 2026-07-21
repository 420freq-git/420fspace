<?php

namespace App\Models;

use App\Enums\Ukuran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pengiriman_id', 'product_id', 'ukuran', 'qty', 'qty_diterima'])]
class PengirimanItem extends Model
{
    protected function casts(): array
    {
        return ['ukuran' => Ukuran::class, 'qty' => 'integer', 'qty_diterima' => 'integer'];
    }

    /** Selisih diterima − dikirim (negatif = kurang). Null bila belum dikonfirmasi. */
    public function getSelisihAttribute(): ?int
    {
        return $this->qty_diterima === null ? null : $this->qty_diterima - $this->qty;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pengiriman(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class);
    }
}

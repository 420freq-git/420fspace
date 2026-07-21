<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand_id', 'nomor', 'tanggal_terbit', 'status', 'tanggal_bayar', 'catatan'])]
class Invoice extends Model
{
    use Auditable;

    public function auditLabel(): string
    {
        return 'Invoice '.$this->nomor;
    }

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
            'tanggal_bayar' => 'date',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Total tagihan (Σ baris × harga_tm420). Butuh eager-load orders.items. */
    public function getTotalAttribute(): int
    {
        return (int) $this->orders->sum(
            fn ($o) => $o->items->sum(fn ($s) => $s->qty * ($s->harga_tm420 ?? 0))
        );
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->orders->sum(fn ($o) => $o->items->sum('qty'));
    }

    public function isLunas(): bool
    {
        return $this->status === 'lunas';
    }
}

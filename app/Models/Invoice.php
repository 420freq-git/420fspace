<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand_id', 'batch_id', 'jenis', 'nomor', 'tanggal_terbit', 'status', 'jumlah_manual', 'pcs_manual', 'tanggal_bayar', 'bukti_transfer', 'catatan'])]
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
            'jumlah_manual' => 'integer',
            'pcs_manual' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Invoice buy-out sisa stok (tagihan manual, bukan dari pesanan marketplace). */
    public function isBuyout(): bool
    {
        return $this->jenis === 'buyout';
    }

    /** Invoice tagihan batch cash (DP atau pelunasan) — tagihan manual berbasis batch. */
    public function isCash(): bool
    {
        return $this->jenis === 'cash';
    }

    /** Tagihan manual (bukan dari pesanan): buy-out atau cash. Keduanya pakai jumlah_manual. */
    public function isManual(): bool
    {
        return $this->jumlah_manual > 0;
    }

    /**
     * Label ringkas jenis tagihan untuk tampilan. Cash: ambil bagian awal catatan
     * ("DP 50%", "Pelunasan sisa", "Tagihan cash penuh").
     */
    public function labelJenis(): string
    {
        return match ($this->jenis) {
            'buyout' => 'Buy-out sisa stok',
            'cash' => $this->catatan ? \Illuminate\Support\Str::before($this->catatan, ' —') : 'Tagihan cash',
            default => $this->orders->count().' pesanan',
        };
    }

    /** Total tagihan = Σ pesanan (× harga_tm420) + baris manual (buy-out). Butuh eager-load orders.items. */
    public function getTotalAttribute(): int
    {
        return (int) $this->orders->sum(
            fn ($o) => $o->items->sum(fn ($s) => $s->qty * ($s->harga_tm420 ?? 0))
        ) + (int) $this->jumlah_manual;
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->orders->sum(fn ($o) => $o->items->sum('qty')) + (int) $this->pcs_manual;
    }

    public function isLunas(): bool
    {
        return $this->status === 'lunas';
    }
}

<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['batch_id', 'nomor_sj', 'tanggal_kirim', 'ekspedisi', 'resi', 'status', 'tgl_diterima', 'catatan',
    'alasan_kurang_kirim', 'alasan_kurang_terima', 'catatan_selisih_terima'])]
class Pengiriman extends Model
{
    use Auditable;

    protected $table = 'pengiriman';

    protected function casts(): array
    {
        return [
            'tanggal_kirim' => 'date',
            'tgl_diterima' => 'date',
            'alasan_kurang_kirim' => \App\Enums\AlasanSelisih::class,
            'alasan_kurang_terima' => \App\Enums\AlasanSelisih::class,
        ];
    }

    public function auditLabel(): string
    {
        return 'Surat jalan '.$this->nomor_sj;
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengirimanItem::class);
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->items->sum('qty');
    }

    public function getTotalDiterimaAttribute(): int
    {
        return (int) $this->items->sum('qty_diterima');
    }

    public function isDiterima(): bool
    {
        return $this->status === 'diterima';
    }

    /** Ada item yang jumlah diterimanya beda dari yang dikirim. */
    public function adaSelisih(): bool
    {
        return $this->items->contains(fn ($i) => $i->qty_diterima !== null && $i->qty_diterima !== $i->qty);
    }

    public function statusBadge(): string
    {
        return $this->isDiterima() ? 'bg-brand-100 text-brand-800' : 'bg-amber-100 text-amber-800';
    }
}

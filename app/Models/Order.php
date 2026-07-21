<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\Marketplace;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'brand_id', 'invoice_id', 'nomor_pesanan', 'resi', 'marketplace', 'tanggal_pesanan', 'status',
    'tgl_kirim', 'tgl_cair', 'tgl_retur', 'tgl_retur_diterima', 'jumlah_cek',
    'tgl_cek_terakhir', 'alasan_batal', 'alasan_rusak', 'sumber', 'keterangan',
])]
class Order extends Model
{
    use Auditable;

    public function auditLabel(): string
    {
        return 'Pesanan '.$this->nomor_pesanan;
    }

    protected function casts(): array
    {
        return [
            'marketplace' => Marketplace::class,
            'status' => OrderStatus::class,
            'tanggal_pesanan' => 'date',
            'tgl_kirim' => 'date',
            'tgl_cair' => 'date',
            'tgl_retur' => 'date',
            'tgl_retur_diterima' => 'date',
            'tgl_cek_terakhir' => 'date',
            'jumlah_cek' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Nilai pesanan ke TM420 (Σ baris × harga_tm420). */
    public function getNilaiTmAttribute(): int
    {
        return (int) $this->items->sum(fn ($s) => $s->qty * ($s->harga_tm420 ?? 0));
    }

    /** Baris item (memakai tabel sales sebagai order-line). */
    public function items(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->items->sum('qty');
    }

    /** Umur pesanan dalam hari. */
    public function getUmurHariAttribute(): int
    {
        return (int) $this->tanggal_pesanan->startOfDay()->diffInDays(now()->startOfDay());
    }
}

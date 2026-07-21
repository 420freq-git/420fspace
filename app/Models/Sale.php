<?php

namespace App\Models;

use App\Enums\Marketplace;
use App\Enums\Ukuran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'brand_id', 'product_id', 'batch_id', 'ukuran', 'qty', 'tanggal_terjual',
    'marketplace', 'nomor_pesanan', 'harga_diferd', 'harga_tm420', 'keterangan', 'kondisi_retur',
])]
class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'ukuran' => Ukuran::class,
            'marketplace' => Marketplace::class,
            'tanggal_terjual' => 'date',
            'qty' => 'integer',
            'harga_diferd' => 'integer',
            'harga_tm420' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Baris yang MENGURANGI stok (barang keluar/hilang):
     * semua kecuali order batal, dan kecuali retur yang diterima kembali dalam kondisi layak.
     */
    public function scopeConsuming($query)
    {
        // Rusak/hilang tetap mengurangi stok (walau order sudah batal/retur-diterima).
        // Aktif (kondisi null) mengurangi stok kecuali order batal (dibatalkan sebelum/tanpa retur).
        return $query->where(function ($w) {
            $w->where('kondisi_retur', 'rusak')
                ->orWhere(function ($q) {
                    $q->whereNull('kondisi_retur')
                        ->whereHas('order', fn ($o) => $o->where('status', '!=', 'batal'));
                });
        });
    }

    /**
     * Baris yang sudah jadi KEWAJIBAN bayar ke vendor:
     * order lunas (terjual) atau barang retur rusak (brand tetap bayar produksi).
     */
    public function scopeSold($query)
    {
        return $query->where(function ($w) {
            $w->whereHas('order', fn ($o) => $o->where('status', 'lunas'))
                ->orWhere('kondisi_retur', 'rusak');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Kewajiban ke vendor untuk baris ini. */
    public function getNilaiDiferdAttribute(): int
    {
        return $this->qty * $this->harga_diferd;
    }

    /** Fee 420F baris ini (null bila brand tanpa harga TM420). */
    public function getFee420fAttribute(): ?int
    {
        return $this->harga_tm420 === null ? null : $this->qty * ($this->harga_tm420 - $this->harga_diferd);
    }
}

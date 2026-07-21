<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\BatchStatus;
use App\Enums\JenisOrder;
use App\Enums\TypePayment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'brand_id', 'nomor_batch', 'vendor', 'tanggal_order', 'deadline', 'deadline_produksi',
    'jenis_order', 'type_payment', 'deposit_awal', 'deposit_rekonsiliasi', 'tgl_rekonsiliasi', 'status',
    'diajukan_oleh', 'disetujui_oleh', 'tgl_approval', 'catatan_approval', 'dibuyout', 'tgl_buyout',
])]
class Batch extends Model
{
    use Auditable;

    public function auditLabel(): string
    {
        return 'Batch '.$this->nomor_batch;
    }

    protected function casts(): array
    {
        return [
            'tanggal_order' => 'date',
            'deadline' => 'date',
            'deadline_produksi' => 'date',
            'jenis_order' => JenisOrder::class,
            'type_payment' => TypePayment::class,
            'status' => BatchStatus::class,
            'deposit_awal' => 'integer',
            'deposit_rekonsiliasi' => 'boolean',
            'tgl_rekonsiliasi' => 'date',
            'tgl_approval' => 'datetime',
            'dibuyout' => 'boolean',
            'tgl_buyout' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->purchaseOrders->sum(fn ($po) => $po->total_qty);
    }

    /** Sisa hari menuju deadline pelunasan (negatif = lewat). */
    public function getSisaHariAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->deadline, false);
    }

    /** Sisa hari menuju deadline produksi (null bila belum diset). */
    public function getSisaProduksiAttribute(): ?int
    {
        return $this->deadline_produksi
            ? (int) now()->startOfDay()->diffInDays($this->deadline_produksi, false)
            : null;
    }
}

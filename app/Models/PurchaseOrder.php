<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\JenisProduksi;
use App\Enums\ProduksiStatus;
use App\Enums\TahapProduksi;
use App\Enums\Ukuran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'batch_id', 'product_id', 'nomor_po',
    'patrun', 'ukuran_rib', 'ukuran_rib_lengan', 'warna_bahan', 'jenis_bahan', 'supp_bahan',
    'cat_sablon', 'finishing', 'desain_depan', 'desain_belakang', 'desain_lengan',
    'label_leher', 'label_bawah', 'slip_label', 'aksesoris', 'care_label', 'hangtag', 'plastik',
    'note', 'status_produksi', 'tahap', 'tahap_updated_at', 'catatan_vendor',
])]
class PurchaseOrder extends Model
{
    use Auditable;

    protected array $auditExclude = ['tahap_updated_at'];

    public function auditLabel(): string
    {
        return 'PO '.$this->nomor_po;
    }

    protected function casts(): array
    {
        return [
            'label_leher' => 'boolean',
            'label_bawah' => 'boolean',
            'slip_label' => 'boolean',
            'aksesoris' => 'boolean',
            'care_label' => 'boolean',
            'hangtag' => 'boolean',
            'plastik' => 'boolean',
            'status_produksi' => ProduksiStatus::class,
            'tahap' => TahapProduksi::class,
            'tahap_updated_at' => 'datetime',
        ];
    }

    /** Berapa hari PO ini diam di tahap sekarang. */
    public function getHariDiTahapAttribute(): ?int
    {
        return $this->tahap_updated_at?->startOfDay()->diffInDays(Carbon::now()->startOfDay());
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sizeItems(): HasMany
    {
        return $this->hasMany(PoSizeItem::class);
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->sizeItems->sum('qty');
    }

    /** Qty untuk kombinasi ukuran + jenis. */
    public function qtyFor(Ukuran $ukuran, JenisProduksi $jenis): int
    {
        return (int) ($this->sizeItems
            ->firstWhere(fn ($i) => $i->ukuran === $ukuran && $i->jenis === $jenis)?->qty ?? 0);
    }

    /** Total qty per ukuran (semua jenis). */
    public function totalPerUkuran(Ukuran $ukuran): int
    {
        return (int) $this->sizeItems->where('ukuran', $ukuran->value)->sum('qty');
    }
}

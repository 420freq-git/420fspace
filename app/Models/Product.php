<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ProductFileType;
use App\Enums\SizeTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'brand_id', 'category_id', 'sku_induk', 'nama_artikel',
    'harga_diferd_sxl_override', 'harga_diferd_xxl_override',
    'harga_tm420_sxl_override', 'harga_tm420_xxl_override', 'aktif',
])]
class Product extends Model
{
    use Auditable;

    public function auditLabel(): string
    {
        return 'Produk '.$this->nama_artikel;
    }

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'harga_diferd_sxl_override' => 'integer',
            'harga_diferd_xxl_override' => 'integer',
            'harga_tm420_sxl_override' => 'integer',
            'harga_tm420_xxl_override' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function spec(): HasOne
    {
        return $this->hasOne(ProductSpec::class);
    }

    public function filesOfType(ProductFileType $type)
    {
        return $this->files->where('tipe', $type->value);
    }

    public function hasOverride(): bool
    {
        return $this->harga_diferd_sxl_override !== null
            || $this->harga_diferd_xxl_override !== null
            || $this->harga_tm420_sxl_override !== null
            || $this->harga_tm420_xxl_override !== null;
    }

    /** Harga Diferd efektif (override bila ada, jika tidak ikut kategori). */
    public function effectiveDiferd(SizeTier $tier): ?int
    {
        $ov = $tier === SizeTier::SXL ? $this->harga_diferd_sxl_override : $this->harga_diferd_xxl_override;

        return $ov ?? $this->category?->priceFor($tier)?->harga_diferd;
    }

    /** Harga TM420 efektif (override bila ada, jika tidak ikut kategori). */
    public function effectiveTm420(SizeTier $tier): ?int
    {
        $ov = $tier === SizeTier::SXL ? $this->harga_tm420_sxl_override : $this->harga_tm420_xxl_override;

        return $ov ?? $this->category?->priceFor($tier)?->harga_tm420;
    }
}

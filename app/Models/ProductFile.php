<?php

namespace App\Models;

use App\Enums\ProductFileType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'tipe', 'path', 'nama_asli'])]
class ProductFile extends Model
{
    protected function casts(): array
    {
        return ['tipe' => ProductFileType::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getIsImageAttribute(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function getExtAttribute(): string
    {
        return strtoupper(pathinfo($this->nama_asli ?: $this->path, PATHINFO_EXTENSION)) ?: 'FILE';
    }
}

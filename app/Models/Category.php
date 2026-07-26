<?php

namespace App\Models;

use App\Enums\JenisProduksi;
use App\Enums\SizeTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'aktif'])]
class Category extends Model
{
    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /**
     * Jenis produksi ditentukan OTOMATIS dari kategori — bukan lagi diinput manual.
     * (Reguler/Oversized = pendek; Longsleeve/Double Layer/Hoodie = panjang.)
     * Ini mencegah salah input (mis. mengisi "pendek" untuk artikel longsleeve).
     */
    public function jenisProduksi(): JenisProduksi
    {
        $n = strtolower($this->nama);

        return match (true) {
            str_contains($n, 'lekbong') => JenisProduksi::Lekbong,
            str_contains($n, 'raglan') => JenisProduksi::Raglan34,
            str_contains($n, 'longsleeve') => JenisProduksi::Panjang,
            str_contains($n, 'double layer') => JenisProduksi::Panjang,
            str_contains($n, 'hoodie') => JenisProduksi::Panjang,
            default => JenisProduksi::Pendek, // Reguler, Oversized, dll.
        };
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CategoryPrice::class);
    }

    /** Ambil baris harga untuk tier tertentu. */
    public function priceFor(SizeTier $tier): ?CategoryPrice
    {
        return $this->prices->firstWhere('size_tier', $tier->value);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'patrun', 'ukuran_rib', 'ukuran_rib_lengan', 'warna_bahan', 'jenis_bahan', 'supp_bahan',
    'cat_sablon', 'finishing', 'desain_depan', 'desain_belakang', 'desain_lengan',
    'label_leher', 'label_bawah', 'slip_label', 'aksesoris', 'care_label', 'hangtag', 'plastik', 'note',
])]
class ProductSpec extends Model
{
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
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

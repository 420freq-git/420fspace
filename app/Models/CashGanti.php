<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_id', 'brand_id', 'metode', 'pcs', 'nilai_diferd', 'nilai_tm420', 'tanggal', 'keterangan'])]
class CashGanti extends Model
{
    use Auditable;

    protected $table = 'cash_ganti';

    public function auditLabel(): string
    {
        return 'Ganti reject cash #'.$this->id.' ('.$this->metode.', '.$this->pcs.' pcs)';
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'pcs' => 'integer',
            'nilai_diferd' => 'integer',
            'nilai_tm420' => 'integer',
        ];
    }

    public function isBarang(): bool
    {
        return $this->metode === 'barang';
    }

    public function isRefund(): bool
    {
        return $this->metode === 'refund';
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}

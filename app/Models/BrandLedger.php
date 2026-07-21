<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'tanggal', 'jumlah', 'keterangan'])]
class BrandLedger extends Model
{
    use Auditable;

    protected $table = 'brand_ledger';

    public function auditLabel(): string
    {
        return 'Transfer/penerimaan TM #'.$this->id;
    }

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'jumlah' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}

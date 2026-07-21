<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\LedgerTipe;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'batch_id', 'penarikan_id', 'tanggal', 'tipe', 'jumlah', 'keterangan'])]
class VendorLedger extends Model
{
    use Auditable;

    protected $table = 'vendor_ledger';

    public function auditLabel(): string
    {
        return 'Ledger vendor #'.$this->id.' ('.($this->tipe?->value ?? '').')';
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tipe' => LedgerTipe::class,
            'jumlah' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Terisi bila baris ini hasil pembekuan alokasi penarikan (bukan input manual 420F). */
    public function penarikan(): BelongsTo
    {
        return $this->belongsTo(Penarikan::class);
    }
}

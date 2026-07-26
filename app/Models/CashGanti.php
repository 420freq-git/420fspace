<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_id', 'brand_id', 'metode', 'pcs', 'nilai_diferd', 'nilai_tm420', 'tanggal', 'keterangan',
    'bukti_diferd', 'tgl_diferd', 'bukti_tm', 'tgl_tm'])]
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
            'tgl_diferd' => 'datetime',
            'tgl_tm' => 'datetime',
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

    /** Langkah 1 refund: Diferd sudah kembalikan uang ke 420F (ada bukti). */
    public function diferdSudahKembalikan(): bool
    {
        return $this->tgl_diferd !== null;
    }

    /** Langkah 2 refund: 420F sudah teruskan refund ke TM (ada bukti). */
    public function sudahDiteruskanTm(): bool
    {
        return $this->tgl_tm !== null;
    }

    /** Refund tuntas bila kedua langkah selesai (barang: langsung tuntas). */
    public function refundTuntas(): bool
    {
        return $this->isBarang() || ($this->diferdSudahKembalikan() && $this->sudahDiteruskanTm());
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

<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['jumlah', 'status', 'tanggal_ajuan', 'tanggal_cair', 'catatan', 'bukti_transfer', 'bukti_invoice'])]
class Penarikan extends Model
{
    use Auditable;

    protected $table = 'penarikan';

    /** Pembagian dana ini ke batch, dibekukan saat penarikan disetujui. */
    public function alokasi(): HasMany
    {
        return $this->hasMany(VendorLedger::class);
    }

    public function auditLabel(): string
    {
        return 'Penarikan #'.$this->id.' (Rp '.number_format((int) $this->jumlah, 0, ',', '.').')';
    }

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'tanggal_ajuan' => 'date',
            'tanggal_cair' => 'date',
        ];
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            'disetujui' => 'bg-brand-100 text-brand-800',
            'ditolak' => 'bg-red-100 text-red-700',
            default => 'bg-amber-100 text-amber-800',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'disetujui' => 'Disetujui / cair',
            'ditolak' => 'Ditolak',
            default => 'Menunggu persetujuan',
        };
    }
}

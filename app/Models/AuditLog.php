<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'user_name', 'user_role', 'event', 'auditable_type', 'auditable_id', 'label', 'changes'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null; // hanya created_at

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Dibuat',
            'updated' => 'Diubah',
            'deleted' => 'Dihapus',
            default => $this->event,
        };
    }

    public function eventClasses(): string
    {
        return match ($this->event) {
            'created' => 'bg-brand-100 text-brand-800',
            'deleted' => 'bg-red-100 text-red-700',
            default => 'bg-blue-100 text-blue-800',
        };
    }
}

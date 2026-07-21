<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'jenis', 'marketplace', 'nama_file', 'hash', 'ringkasan'])]
class ImportLog extends Model
{
    protected function casts(): array
    {
        return ['ringkasan' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

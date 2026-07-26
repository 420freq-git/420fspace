<?php

namespace App\Models;

use App\Enums\Role;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'no_hp', 'password', 'role', 'brand_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Boleh melihat harga Diferd (biaya 420F ke vendor)? Disembunyikan dari brand eksternal
     * (TM420) supaya margin 420F tidak terungkap. Admin, vendor, & brand internal (VOOJAH) boleh.
     */
    public function bolehLihatHargaDiferd(): bool
    {
        return $this->role !== Role::Tm420;
    }

    /**
     * Harga TM420 (retail) hanya relevan bagi brand eksternal yang ditagih retail.
     * VOOJAH (brand milik sendiri) ditagih harga modal (diferd) dengan fee 420F = 0, jadi kolom
     * retail tak berarti apa-apa baginya — sembunyikan. VOOJAH tetap melihat kolom Diferd, yang
     * memang harga yang ia bayar (hargaTagihan untuk brand milik-sendiri = effectiveDiferd).
     */
    public function bolehLihatHargaTm420(): bool
    {
        return $this->role !== Role::Voojah;
    }
}

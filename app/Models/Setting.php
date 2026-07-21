<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** @var array<string,string>|null */
    protected static ?array $cache = null;

    /** Ambil nilai integer sebuah setting (dengan default). */
    public static function intVal(string $key, int $default): int
    {
        if (static::$cache === null) {
            static::$cache = static::pluck('value', 'key')->all();
        }

        $v = static::$cache[$key] ?? null;

        return $v === null || $v === '' ? $default : (int) $v;
    }

    public static function put(string $key, int|string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        static::$cache = null;
    }
}

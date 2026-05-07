<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected $casts = ['is_encrypted' => 'boolean'];

    private const CACHE_KEY = 'app_settings_all';
    private const CACHE_TTL = 300; // 5 minutes

    // -----------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allCached();

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        ['value' => $value, 'is_encrypted' => $encrypted] = $all[$key];

        if ($encrypted && $value !== null) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $value;
    }

    /** Returns true if the key exists in DB (even with null value). */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::allCached());
    }

    // -----------------------------------------------------------------
    // Write
    // -----------------------------------------------------------------

    public static function set(string $key, mixed $value, bool $encrypted = false): void
    {
        $stored = ($encrypted && $value !== null && $value !== '')
            ? Crypt::encryptString((string) $value)
            : ($value === '' ? null : $value);

        self::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'is_encrypted' => $encrypted]
        );

        self::clearCache();
    }

    public static function forget(string $key): void
    {
        self::where('key', $key)->delete();
        self::clearCache();
    }

    // -----------------------------------------------------------------
    // Cache helpers
    // -----------------------------------------------------------------

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function allCached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return self::all()
                    ->keyBy('key')
                    ->map(fn ($s) => ['value' => $s->value, 'is_encrypted' => $s->is_encrypted])
                    ->toArray();
            });
        } catch (\Throwable) {
            // Table not yet migrated — return empty so callers fall back to defaults
            return [];
        }
    }
}

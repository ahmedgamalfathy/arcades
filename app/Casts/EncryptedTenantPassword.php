<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EncryptedTenantPassword implements CastsAttributes
{
    public const PREFIX = 'encrypted:v1:';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! self::isEncrypted($value)) {
            // Transitional support for rows that have not reached the data migration yet.
            return $value;
        }

        return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::encryptForStorage((string) $value);
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public static function encryptForStorage(string $value): string
    {
        return self::PREFIX.Crypt::encryptString($value);
    }

    public static function decryptStored(string $value): string
    {
        return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
    }
}

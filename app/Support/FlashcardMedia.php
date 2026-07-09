<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FlashcardMedia
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);

        if (is_string($path)) {
            $path = ltrim($path, '/');

            if (Str::startsWith($path, 'storage/')) {
                return Str::after($path, 'storage/');
            }
        }

        if (Str::startsWith($value, 'storage/')) {
            return Str::after($value, 'storage/');
        }

        return $value;
    }

    public static function url(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (Str::startsWith($value, ['/storage/', '/uploads/'])) {
            return url($value);
        }

        if (Str::startsWith($value, ['storage/', 'uploads/'])) {
            return url('/'.$value);
        }

        return Storage::disk('public')->url($value);
    }

    /**
     * @param  array<int, string>|null  $values
     * @return array<int, string>
     */
    public static function normalizeMany(?array $values): array
    {
        return collect($values ?: [])
            ->map(fn ($value) => self::normalize($value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $values
     * @return array<int, string>
     */
    public static function urls(?array $values): array
    {
        return collect($values ?: [])
            ->map(fn ($value) => self::url($value))
            ->filter()
            ->values()
            ->all();
    }
}

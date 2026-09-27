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

    /**
     * Return only paths managed by this application. Remote URLs and arbitrary
     * filesystem-looking values must never be passed to the storage delete API.
     *
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    public static function managedPaths(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => is_string($value) && ! filter_var($value, FILTER_VALIDATE_URL))
            ->map(fn (string $value) => self::normalize($value))
            ->filter(fn ($value) => is_string($value) && Str::startsWith($value, 'flashcards/'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string|null>  $values
     */
    public static function deleteManaged(array $values): void
    {
        $paths = self::managedPaths($values);

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }
}

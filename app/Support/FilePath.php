<?php

namespace App\Support;

final class FilePath
{
    /**
     * The part of $path below $directory with forward slashes, or null when
     * $path lies outside it. Windows paths compare case-insensitively.
     */
    public static function relativeTo(string $path, string $directory): ?string
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        $inside = PHP_OS_FAMILY === 'Windows'
            ? strncasecmp($path, $root, strlen($root)) === 0
            : str_starts_with($path, $root);

        return $inside ? substr($path, strlen($root)) : null;
    }

    public static function isWithin(string $path, string $directory): bool
    {
        return self::relativeTo($path, $directory) !== null;
    }
}

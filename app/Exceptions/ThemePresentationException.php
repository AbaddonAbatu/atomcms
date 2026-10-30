<?php

namespace App\Exceptions;

use App\Data\ThemeDefinition;
use RuntimeException;
use Throwable;

class ThemePresentationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 503, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }

    public static function unknownTheme(string $id): self
    {
        return new self("Theme [{$id}] is not installed.");
    }

    /** @param list<string> $problems */
    public static function invalidTheme(string $id, array $problems): self
    {
        return new self("Theme [{$id}] is invalid: " . implode('; ', $problems));
    }

    public static function missingView(ThemeDefinition $theme, string $key): self
    {
        return new self("Theme [{$theme->id}] does not provide view [{$key}]; expected {$theme->viewFile($key)}.", statusCode: 404);
    }

    public static function foreignView(ThemeDefinition $theme, string $key, string $path): self
    {
        return new self("Theme [{$theme->id}] resolved view [{$key}] outside its own view root [{$theme->viewPath()}]: {$path}.");
    }
}

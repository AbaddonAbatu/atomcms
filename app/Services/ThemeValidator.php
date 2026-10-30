<?php

namespace App\Services;

use App\Data\ThemeDefinition;
use App\Exceptions\ThemePresentationException;
use App\Support\FilePath;
use DirectoryIterator;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class ThemeValidator
{
    /** Core output and npm script names (`build:core`, `build:all`, …) a theme ID would collide with. */
    public const RESERVED_IDS = ['all', 'core', 'housekeeping', 'vendor'];

    public const REQUIRED_VIEWS = [
        // Guest/auth
        'index', 'auth.login', 'auth.register', 'auth.passwords.forget', 'auth.passwords.reset', 'auth.passwords.confirm', 'auth.two-factor-challenge',
        // Account/state
        'user.me', 'user.settings.account', 'user.settings.password', 'user.settings.two-factor', 'banned', 'maintenance',
        // Hotel client
        'client.nitro',
    ];

    /** @var array<string, ThemeDefinition> */
    private array $definitions = [];

    /** @var array<string, list<string>> */
    private array $rejected = [];

    private bool $discovered = false;

    public function __construct(private Filesystem $files) {}

    /** @return array<string, ThemeDefinition> */
    public function definitions(): array
    {
        $this->discover();

        return $this->definitions;
    }

    /**
     * IDs the `theme` website setting may be written with: discovered themes
     * whose folder ID and path are valid. The required views and built assets are
     * deliberately not required here.
     *
     * @return list<string>
     */
    public function selectableIds(): array
    {
        return array_keys($this->definitions());
    }

    /** @return array<string, list<string>> */
    public function rejected(): array
    {
        $this->discover();

        return $this->rejected;
    }

    public function definition(string $id): ThemeDefinition
    {
        $this->discover();

        if (isset($this->rejected[$id])) {
            throw ThemePresentationException::invalidTheme($id, $this->rejected[$id]);
        }

        return $this->definitions[$id] ?? throw ThemePresentationException::unknownTheme($id);
    }

    public function isRelativeThemePath(string $path): bool
    {
        return ! str_contains($path, '\\')
            && ! preg_match('/^[a-z]:/i', $path)
            && ! str_contains($path, "\0")
            && array_intersect(explode('/', $path), ['', '.', '..']) === [];
    }

    private function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;
        $basePath = config('theme.base_path');

        if (! is_string($basePath) || ! $this->files->isDirectory($basePath)) {
            return;
        }

        $base = realpath($basePath);

        if ($base === false) {
            return;
        }

        try {
            $directories = [];

            foreach (new DirectoryIterator($base) as $directory) {
                if (! $directory->isDot() && $directory->isDir()) {
                    $directories[] = $directory->getPathname();
                }
            }
        } catch (Throwable) {
            return;
        }

        foreach ($directories as $directory) {
            $id = basename($directory);

            try {
                $result = $this->inspect($directory, $base);
            } catch (Throwable $exception) {
                $result = ['Cannot read theme: ' . $exception->getMessage()];
            }

            if ($result instanceof ThemeDefinition) {
                $this->definitions[$id] = $result;
            } else {
                $this->rejected[$id] = $result;
            }
        }

        ksort($this->definitions);
        ksort($this->rejected);
    }

    /** @return ThemeDefinition|list<string> */
    private function inspect(string $directory, string $base): ThemeDefinition|array
    {
        $id = basename($directory);
        $problems = [];

        if (! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $id)) {
            $problems[] = "Invalid theme ID [{$id}]; use lowercase letters, digits and single hyphens, starting with a letter.";
        }

        if (in_array($id, self::RESERVED_IDS, true)) {
            $problems[] = "Theme ID [{$id}] is reserved.";
        }

        $path = realpath($directory);

        if ($path === false || ! $this->samePath(dirname($path), $base)) {
            $problems[] = "Theme [{$id}] must resolve to a direct child of the theme base path.";

            return $problems;
        }

        $theme = new ThemeDefinition($id, $path);

        foreach ($theme->entrypoints() as $entrypoint) {
            $realEntryPath = realpath($path . DIRECTORY_SEPARATOR . $entrypoint);

            if ($realEntryPath !== false && ! FilePath::isWithin($realEntryPath, $path)) {
                $problems[] = "Entrypoint [{$entrypoint}] resolves outside the theme.";
            }
        }

        if ($problems !== []) {
            return $problems;
        }

        return $theme;
    }

    private function samePath(string $first, string $second): bool
    {
        return PHP_OS_FAMILY === 'Windows' ? strcasecmp($first, $second) === 0 : $first === $second;
    }
}

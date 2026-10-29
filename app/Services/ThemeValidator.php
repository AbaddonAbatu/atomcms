<?php

namespace App\Services;

use App\Data\ThemeDefinition;
use App\Exceptions\ThemePresentationException;
use App\Support\FilePath;
use DirectoryIterator;
use Illuminate\Filesystem\Filesystem;
use stdClass;
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

    /**
     * Basic checks before activating a theme: a valid definition, required
     * views and a usable build manifest.
     * The `theme` setting is only written when this is empty.
     *
     * @return list<string>
     */
    public function activationProblems(string $id, bool $production): array
    {
        $this->discover();

        if (isset($this->rejected[$id])) {
            return $this->rejected[$id];
        }

        if (! isset($this->definitions[$id])) {
            return [ThemePresentationException::unknownTheme($id)->getMessage()];
        }

        $theme = $this->definitions[$id];

        return array_merge($this->viewProblems($theme), $this->assetProblems($theme, $production));
    }

    /** @return list<string> */
    public function viewProblems(ThemeDefinition $theme): array
    {
        $problems = [];

        foreach ($this->missingViews($theme) as $key => $file) {
            $problems[] = "Missing view [{$key}]: {$file}";
        }

        return $problems;
    }

    /** @return array<string, string> */
    public function missingViews(ThemeDefinition $theme): array
    {
        $missing = [];

        foreach (self::REQUIRED_VIEWS as $key) {
            if (! $this->files->isFile($theme->viewPath() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $key) . '.blade.php')) {
                $missing[$key] = $theme->viewFile($key);
            }
        }

        return $missing;
    }

    /** @return list<string> */
    public function assetProblems(ThemeDefinition $theme, bool $production): array
    {
        $problems = [];

        if ($this->files->exists($theme->hotFile())) {
            if (! $production) {
                return [];
            }

            $problems[] = 'Remove the development hot file ' . $this->displayPath($theme->hotFile()) . ' before deploying.';
        }

        $path = public_path($theme->buildDirectory() . '/manifest.json');
        $displayPath = $this->displayPath($path);

        if (! $this->files->isFile($path)) {
            $problems[] = "Missing build manifest {$displayPath}.";

            return $problems;
        }

        try {
            $manifest = json_decode($this->files->get($path), flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $problems[] = "Cannot read build manifest {$displayPath}: {$exception->getMessage()}.";

            return $problems;
        }

        if (! $manifest instanceof stdClass) {
            $problems[] = "Build manifest {$displayPath} must be a JSON object.";

            return $problems;
        }

        foreach ($theme->viteEntrypoints() as $entrypoint) {
            if (! property_exists($manifest, $entrypoint)) {
                $problems[] = "Build manifest {$displayPath} has no entry for {$entrypoint}.";

                continue;
            }

            $entry = $manifest->{$entrypoint};

            if (! $entry instanceof stdClass || ! is_string($entry->file ?? null)) {
                $problems[] = "Build manifest {$displayPath} entry {$entrypoint} has no emitted file.";
            }
        }

        return $problems;
    }

    /** @return list<string> */
    public function buildProblems(ThemeDefinition $theme): array
    {
        $problems = [];

        foreach ($theme->entrypoints() as $source) {
            if (! $this->files->isFile($theme->path . DIRECTORY_SEPARATOR . $source)) {
                $problems[] = "Missing entrypoint file [{$source}].";
            }
        }

        return $problems;
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

    private function displayPath(string $path): string
    {
        return FilePath::relativeTo($path, base_path()) ?? str_replace('\\', '/', $path);
    }
}

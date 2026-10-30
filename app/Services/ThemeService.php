<?php

namespace App\Services;

use App\Data\ThemeDefinition;
use App\Exceptions\ThemePresentationException;
use App\Support\FilePath;
use Closure;
use ErrorException;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\View\Factory;
use InvalidArgumentException;
use TypeError;

class ThemeService
{
    private ?ThemeDefinition $selected = null;

    public function __construct(
        private ThemeValidator $validator,
        private SettingsService $settings,
        private Factory $views,
    ) {}

    public function selectedId(): string
    {
        $value = $this->settings->getOrDefault('theme');

        return empty($value) || $value === '1' ? config('theme.default') : $value;
    }

    public function selected(): ThemeDefinition
    {
        if ($this->selected === null) {
            $this->selected = $this->validator->definition($this->selectedId());
        }

        return $this->selected;
    }

    /** @param array<string, mixed> $data */
    public function view(string $key, array $data = []): View
    {
        $theme = $this->selected();
        $name = "{$theme->namespace()}::{$key}";

        try {
            $path = $this->views->getFinder()->find($name);
        } catch (InvalidArgumentException) {
            throw ThemePresentationException::missingView($theme, $key);
        }

        if (! is_file($path)) {
            throw ThemePresentationException::missingView($theme, $key);
        }

        if (! FilePath::isWithin(realpath($path) ?: $path, $theme->viewPath())) {
            throw ThemePresentationException::foreignView($theme, $key, $path);
        }

        return $this->views->make($name, $data);
    }

    /**
     * A closure for $data runs only when the theme provides the view, so
     * callers can skip loading data for an omitted one.
     *
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $data
     */
    public function optionalView(string $key, array|Closure $data = []): ?View
    {
        $theme = $this->selected();

        if (! in_array($key, ThemeValidator::REQUIRED_VIEWS, true) && ! $this->views->exists("{$theme->namespace()}::{$key}")) {
            return null;
        }

        return $this->view($key, value($data));
    }

    public function vite(): HtmlString
    {
        $theme = $this->selected();

        try {
            return ($this->themeVite())($theme->viteEntrypoints());
        } catch (ViteException $exception) {
            throw ThemePresentationException::unavailableAssets($theme, $exception);
        }
    }

    public function asset(string $path): string
    {
        $theme = $this->selected();

        if (! $this->validator->isRelativeThemePath($path)) {
            throw ThemePresentationException::invalidAssetPath($theme, $path);
        }

        try {
            return $this->themeVite()->asset($theme->sourceDirectory() . '/' . $path);
        } catch (ViteException|ErrorException|TypeError $exception) {
            Log::warning('Theme media asset is unavailable.', ['theme' => $theme->id, 'path' => $path, 'reason' => $exception->getMessage()]);

            $urlPath = implode('/', array_map('rawurlencode', explode('/', $path)));

            return asset($theme->buildDirectory() . '/' . $urlPath);
        }
    }

    private function themeVite(): Vite
    {
        $theme = $this->selected();

        return (clone app(Vite::class))
            ->useHotFile($theme->hotFile())
            ->useBuildDirectory($theme->buildDirectory());
    }
}

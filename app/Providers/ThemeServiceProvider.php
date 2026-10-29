<?php

namespace App\Providers;

use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeValidator::class);
        $this->app->scoped(ThemeService::class);
    }

    public function boot(): void
    {
        // Installer, mail and diagnostics belong to Core and render without
        // any public theme; `core` is a reserved theme ID.
        View::addNamespace('core', resource_path('views/core'));
        Blade::anonymousComponentNamespace('core::components', 'core');

        foreach ($this->app->make(ThemeValidator::class)->definitions() as $definition) {
            View::addNamespace($definition->namespace(), $definition->viewPath());

            // A namespace, not anonymousComponentPath(): Blade also searches every
            // registered component path for unprefixed tags, which would let a
            // tag missing from one theme resolve from another.
            Blade::anonymousComponentNamespace($definition->namespace() . '::components', $definition->namespace());
            Blaze::optimize()->in($definition->componentPath());
        }
    }
}

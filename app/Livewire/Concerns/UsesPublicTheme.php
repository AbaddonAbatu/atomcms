<?php

namespace App\Livewire\Concerns;

use App\Services\ThemeService;
use Livewire\Attributes\Locked;

/**
 * For public components only. Livewire's shared update endpoint skips the
 * public theme middleware so Filament keeps working with a broken public theme;
 * this check runs before any property update or action instead.
 */
trait UsesPublicTheme
{
    /** The theme this component was first rendered with, kept in the checksummed snapshot. */
    #[Locked]
    public string $renderedTheme = '';

    protected function themeView(): string
    {
        return 'livewire.' . $this->getName();
    }

    public function mountUsesPublicTheme(): void
    {
        $themes = app(ThemeService::class);
        $this->renderedTheme = $themes->selected()->id;
        $themes->view($this->themeView());
    }

    /**
     * An unusable theme answers with the Core diagnostic. A page rendered with a
     * theme that is no longer selected gets 419, which Livewire turns into its
     * refresh prompt, instead of new-theme markup inside the old page.
     */
    public function hydrateUsesPublicTheme(): void
    {
        $themes = app(ThemeService::class);
        abort_unless($themes->selected()->id === $this->renderedTheme, 419);
        $themes->view($this->themeView());
    }
}

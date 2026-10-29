<?php

use App\Models\Miscellaneous\WebsiteSetting;
use App\Services\InstallationSetup;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->setupThemeOriginalPublicPath = public_path();
    $this->setupThemeOriginalStoragePath = storage_path();
    $this->setupThemeTemporaryPath = sys_get_temp_dir() . '/atom-setup-theme-' . bin2hex(random_bytes(12));
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath($this->setupThemeTemporaryPath);
    File::ensureDirectoryExists(storage_path('vite'));
});

afterEach(function (): void {
    app()->usePublicPath($this->setupThemeOriginalPublicPath);
    app()->useStoragePath($this->setupThemeOriginalStoragePath);
    File::deleteDirectory($this->setupThemeTemporaryPath);
});

test('atom:setup offers the installed themes for the theme setting', function () {
    installHotel();
    setSetting('theme', 'dusk');

    $command = $this->artisan('atom:setup');

    foreach (app(InstallationSetup::class)->rules() as $key => $rules) {
        $label = ucwords(str_replace('_', ' ', $key));
        $choices = collect($rules)->first(fn (mixed $rule): bool => is_string($rule) && str_starts_with($rule, 'in:'));

        if ($choices === null) {
            $command->expectsQuestion($label, (string) setting($key, ''));

            continue;
        }

        $options = explode(',', substr($choices, 3));

        $key === 'theme'
            ? $command->expectsChoice($label, 'atom', ['atom', 'dusk'])
            : $command->expectsChoice($label, $options[0], $options);
    }

    $command->assertSuccessful()->run();

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('atom');
});

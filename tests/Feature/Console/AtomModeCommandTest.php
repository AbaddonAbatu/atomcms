<?php

use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('mode status reports each owner manifest using the canonical theme target', function (string $theme): void {
    installHotel();
    setSetting('theme', $theme);
    $originalPublicPath = public_path();
    $originalStoragePath = storage_path();
    $temporaryStoragePath = sys_get_temp_dir() . '/atom-mode-status-' . bin2hex(random_bytes(12));

    try {
        app()->useStoragePath($temporaryStoragePath);
        app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
        expect(Artisan::call('atom:mode', ['--status' => true]))->toBe(0);
        expect(Artisan::output())->toContain('Theme assets', 'Core assets', 'Housekeeping assets')->not->toContain('missing:', 'not ready:');

        app()->usePublicPath(base_path('tests/Fixtures/vite/missing'));
        expect(Artisan::call('atom:mode', ['--status' => true]))->toBe(0);
        expect(Artisan::output())->toContain('missing: npm run build:atom', 'npm run build:atom', 'missing: npm run build:core', 'missing: npm run build:housekeeping')->not->toContain('build:1');
    } finally {
        app()->usePublicPath($originalPublicPath);
        app()->useStoragePath($originalStoragePath);
        File::deleteDirectory($temporaryStoragePath);
    }
})->with(['atom', '1', '']);

test('headless mode status works without public theme files', function (): void {
    installHotel();
    setSetting('theme', 'unknown');
    config([
        'atom.mode' => 'headless',
        'theme.base_path' => sys_get_temp_dir() . '/absent-themes-' . bin2hex(random_bytes(12)),
    ]);
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetInstance(ThemeService::class);

    expect(Artisan::call('atom:mode', ['--status' => true]))->toBe(0);
    expect(Artisan::output())->toContain('headless', 'not required (headless)', 'Housekeeping assets')
        ->not->toContain('Theme [unknown]');
});

test('full mode status reports an invalid selected theme without throwing', function (): void {
    installHotel();
    setSetting('theme', 'unknown');
    config(['atom.mode' => 'full']);

    expect(Artisan::call('atom:mode', ['--status' => true]))->toBe(0);
    expect(Artisan::output())->toContain('unavailable: Theme [unknown] is not installed.', 'Core assets', 'Housekeeping assets');
});

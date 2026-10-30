<?php

use App\Mail\ResetPasswordMail;
use App\Models\Miscellaneous\WebsiteInstallation;
use App\Services\InstallationService;
use App\Services\SettingsService;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/*
 * Core presentation renders through the fixed `core::` namespace. Every case
 * here hides both public themes: the theme base path is an empty directory and
 * the view finder keeps only the root view path, so any lookup that still
 * depended on Atom or Dusk would fail.
 */
beforeEach(function (): void {
    $this->emptyThemesPath = sys_get_temp_dir() . '/atom-no-themes-' . bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->emptyThemesPath);
    config(['theme.base_path' => $this->emptyThemesPath]);
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetInstance(ThemeService::class);
    View::getFinder()->setPaths([resource_path('views')]);
    View::getFinder()->flush();
});

afterEach(function (): void {
    File::deleteDirectory($this->emptyThemesPath);
});

test('Core SEO metadata and Fancybox assets render without a public theme', function (): void {
    setSetting('hotel_name', 'Core SEO Hotel');

    $html = Blade::render('<x-core::seo-meta /><x-core::fancybox-assets /><x-core::fancybox-assets />');

    expect($html)->toContain('<meta property="og:site_name" content="Core SEO Hotel">')
        ->toContain('integrity="sha384-mfITPNdkUxVtTV4zhg4SDrxOL/aZBoz/p9adHFmaCoLDa43yghU46m5oEkJzLsav"')
        ->toContain('integrity="sha384-kPPlFUTv5pDfb8JmcIDGDPhxzM4ZG0aIQ5/7vANkrD1Z7GR9e3k459LTa7fDCOX/"');
    expect(substr_count($html, 'fancybox.umd.js'))->toBe(1);
    expect(substr_count($html, 'fancybox.css'))->toBe(1);
});

function rewindCoreInstallation(int $step): void
{
    WebsiteInstallation::query()->update([
        'step' => $step,
        'completed' => false,
        'installation_key' => 'core-install-key',
        'user_ip' => $step === 0 ? null : '127.0.0.1',
    ]);

    Cache::flush();
    app()->forgetInstance(InstallationService::class);
    app()->forgetInstance(SettingsService::class);
}

test('the installer renders every wizard page with no public theme available', function () {
    expect(app(ThemeValidator::class)->definitions())->toBe([]);

    rewindCoreInstallation(0);

    $this->get(route('installation.index'))->assertOk()
        ->assertSee('Start the setup')
        ->assertSee('bg-green-600! hover:bg-green-700!', escape: false)
        ->assertDontSee('/build/themes/', escape: false)
        ->assertDontSee('resources/themes/', escape: false);

    foreach (range(1, WebsiteInstallation::LAST_STEP) as $step) {
        rewindCoreInstallation($step);

        $response = $this->get(route('installation.show-step', $step))->assertOk()
            ->assertSee('Restart installation')
            ->assertSee('bg-red-500 hover:bg-red-600', escape: false)
            ->assertDontSee('resources/themes/', escape: false);

        if ($step > 1) {
            $response->assertSee('Previous step')->assertSee('bg-[#eeb425]! text-white', escape: false);
        }
    }
});

test('the theme-unavailable diagnostic renders without a public theme or any asset', function () {
    $html = view('core::errors.theme-unavailable')->render();

    expect($html)->toContain('<title>Service Unavailable</title>')
        ->toContain('The site is temporarily unavailable. Please try again later.')
        ->not->toContain('<link')
        ->not->toContain('<script')
        ->not->toContain('/build');
});

test('reset mail renders the Core body and reset URL with no public theme available', function (string $mode, string $resetUrl) {
    config([
        'atom.mode' => $mode,
        'app.url' => 'https://hotel.example',
        'atom.frontend_url' => 'https://frontend.example.com',
    ]);

    $mail = new ResetPasswordMail('core-token');

    $mail->assertSeeInOrderInHtml([
        'Reset your password',
        'Click the link to reset your password:',
        'href="' . $resetUrl . '"',
        'Reset Password',
    ], escape: false);
})->with([
    'full mode' => ['full', 'https://hotel.example/reset-password/core-token'],
    'headless mode' => ['headless', 'https://frontend.example.com/reset-password/core-token'],
]);

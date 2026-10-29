<?php

use App\Models\Miscellaneous\WebsiteInstallation;
use App\Models\User;
use App\Models\User\Ban;
use App\Services\InstallationService;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Filament\Facades\Filament;
use Filament\Support\Assets\Theme;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Js;

beforeEach(function (): void {
    $this->assetsTemporaryPath = sys_get_temp_dir() . '/atom-assets-' . bin2hex(random_bytes(12));
    $this->assetsOriginalPublicPath = public_path();
    $this->assetsOriginalStoragePath = storage_path();
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath($this->assetsTemporaryPath . '/storage');
    File::ensureDirectoryExists(storage_path('vite'));
    $this->withVite();
    app()->forgetInstance(Vite::class);
});

afterEach(function (): void {
    app()->usePublicPath($this->assetsOriginalPublicPath);
    app()->useStoragePath($this->assetsOriginalStoragePath);
    File::deleteDirectory($this->assetsTemporaryPath);
});

test('the installer uses only Core production assets', function (): void {
    WebsiteInstallation::query()->update(['completed' => false, 'step' => 0]);
    Cache::flush();
    app()->forgetInstance(InstallationService::class);
    app()->forgetInstance(SettingsService::class);

    $this->get(route('installation.index'))->assertOk()
        ->assertSee('http://localhost/build-core/assets/global-fixture.css', escape: false)
        ->assertSee('http://localhost/build-core/assets/global-fixture.js', escape: false)
        ->assertDontSee('/build/themes/', escape: false)
        ->assertDontSee('/build-housekeeping/', escape: false);
});

test('the installer uses only its Core development server', function (): void {
    WebsiteInstallation::query()->update(['completed' => false, 'step' => 0]);
    Cache::flush();
    app()->forgetInstance(InstallationService::class);
    app()->forgetInstance(SettingsService::class);
    File::put(storage_path('vite/core.hot'), 'http://core-vite.test:5175');
    File::put(storage_path('vite/atom.hot'), 'http://atom-vite.test:5173');
    File::put(storage_path('vite/housekeeping.hot'), 'http://admin-vite.test:5176');

    $this->get(route('installation.index'))->assertOk()
        ->assertSee('http://core-vite.test:5175/resources/css/global.css', escape: false)
        ->assertSee('http://core-vite.test:5175/resources/js/global.js', escape: false)
        ->assertDontSee('http://atom-vite.test:', escape: false)
        ->assertDontSee('http://admin-vite.test:', escape: false)
        ->assertDontSee('/build-core/', escape: false);
});

test('housekeeping uses its production manifest despite other owners hot files', function (): void {
    installHotel();
    File::put(storage_path('vite/core.hot'), 'http://core-vite.test:5175');
    File::put(storage_path('vite/atom.hot'), 'http://atom-vite.test:5173');

    $this->get('/housekeeping/login')->assertOk()
        ->assertSee('http://localhost/build-housekeeping/assets/housekeeping-fixture.css', escape: false)
        ->assertDontSee('http://core-vite.test:', escape: false)
        ->assertDontSee('http://atom-vite.test:', escape: false)
        ->assertDontSee('/build-core/', escape: false);
});

test('housekeeping uses only its own development server', function (): void {
    installHotel();
    File::put(storage_path('vite/core.hot'), 'http://core-vite.test:5175');
    File::put(storage_path('vite/atom.hot'), 'http://atom-vite.test:5173');
    File::put(storage_path('vite/housekeeping.hot'), 'http://admin-vite.test:5176');

    $this->get('/housekeeping/login')->assertOk()
        ->assertSee('http://admin-vite.test:5176/resources/css/filament/housekeeping/theme.css', escape: false)
        ->assertDontSee('http://core-vite.test:', escape: false)
        ->assertDontSee('http://atom-vite.test:', escape: false)
        ->assertDontSee('/build-housekeeping/', escape: false);
});

test('housekeeping theme registration remains lazy on public requests', function (): void {
    installHotel();
    setSetting('theme', 'atom');
    app()->usePublicPath($this->assetsTemporaryPath . '/no-manifests');
    File::put(storage_path('vite/atom.hot'), 'http://atom-vite.test:5173');

    expect(Filament::getPanel('housekeeping')->getTheme())->toBeInstanceOf(Theme::class);
    $this->get(route('welcome'))->assertOk()->assertSee('http://atom-vite.test:5173/@vite/client', escape: false);
});

test('a theme hot file does not affect the other theme or Core', function (string $theme, string $other): void {
    installHotel();
    setSetting('theme', $theme);
    File::put(storage_path("vite/{$other}.hot"), 'http://other-vite.test:5173');

    $this->get(route('welcome'))->assertOk()
        ->assertSee("http://localhost/build/themes/{$theme}/assets/{$theme}-app-fixture.css", escape: false)
        ->assertDontSee('http://other-vite.test:', escape: false);
    expect((string) app(Vite::class)(['resources/css/global.css', 'resources/js/global.js']))
        ->toContain('/build-core/assets/global-fixture.css', '/build-core/assets/global-fixture.js')
        ->not->toContain('http://other-vite.test:');
    expect(app(Vite::class))->not->toBe(app(Vite::class));
})->with(['atom' => ['atom', 'dusk'], 'dusk' => ['dusk', 'atom']]);

test('orphaned public hot and build files cannot select a development server or legacy assets', function (): void {
    installHotel();
    setSetting('theme', 'atom');
    $public = $this->assetsTemporaryPath . '/public';
    File::copyDirectory(public_path(), $public);
    app()->usePublicPath($public);
    File::put(public_path('hot'), 'http://orphan-vite.test:5199');
    File::put(public_path('build/manifest.json'), '{}');

    expect((string) app(Vite::class)(['resources/css/global.css', 'resources/js/global.js']))
        ->toContain('/build-core/assets/global-fixture.css')->not->toContain('orphan-vite.test', '/build/assets/');
    $this->get(route('welcome'))->assertOk()
        ->assertSee('/build/themes/atom/assets/atom-app-fixture.css', escape: false)
        ->assertDontSee('orphan-vite.test', escape: false)
        ->assertDontSee('/build/assets/', escape: false);
});

test('legacy selection renders the configured default theme through Vite', function (string $setting, string $default): void {
    installHotel();
    config(['theme.default' => $default]);
    setSetting('theme', $setting);

    $this->get(route('welcome'))->assertOk()->assertViewIs("theme-{$default}::index")
        ->assertSee("/build/themes/{$default}/assets/{$default}-app-fixture.css", escape: false);

    expect(realpath(theme_view('index')->getPath()))->toBe(realpath(resource_path("themes/{$default}/views/index.blade.php")));
})->with(['legacy' => '1', 'empty' => ''])->with(['atom', 'dusk']);

test('the legacy zero selection normalizes to the default and renders its page', function (): void {
    installHotel();
    setSetting('theme', '0');
    config(['theme.default' => 'atom']);

    $this->get(route('welcome'))->assertOk()->assertViewIs('theme-atom::index');

    expect(app(ThemeService::class)->selectedId())->toBe('atom');
});

test('an unknown selection returns the Core service-unavailable page', function (): void {
    installHotel();
    setSetting('theme', 'unknown');

    $this->get(route('welcome'))
        ->assertServiceUnavailable()
        ->assertSee('Service Unavailable')
        ->assertSee('The site is temporarily unavailable. Please try again later.');
});

test('missing Blade media produces a warning while validation and the page succeed', function (): void {
    File::copyDirectory(public_path(), $this->assetsTemporaryPath . '/public');
    app()->usePublicPath($this->assetsTemporaryPath . '/public');
    $path = public_path('build/themes/dusk/manifest.json');
    $manifest = File::json($path);
    unset($manifest['resources/themes/dusk/images/ghost.png']);
    File::put($path, json_encode($manifest, JSON_THROW_ON_ERROR));

    $this->artisan('atom:themes:validate', ['theme' => 'dusk', '--production' => true])
        ->expectsOutput('dusk: valid')
        ->assertSuccessful();

    installHotel();
    setSetting('theme', 'dusk');
    Log::spy();
    $this->get(route('welcome'))->assertOk()
        ->assertSee('http://localhost/build/themes/dusk/images/ghost.png', escape: false);

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context): bool => $message === 'Theme media asset is unavailable.'
            && $context['theme'] === 'dusk' && $context['path'] === 'images/ghost.png',
    )->atLeast()->once();
});

test('unavailable media lookups return an isolated URL and log a diagnostic', function (string $path, string $defect, ?string $urlPath = null): void {
    installHotel();
    setSetting('theme', 'dusk');
    File::copyDirectory(public_path(), $this->assetsTemporaryPath . '/public');
    app()->usePublicPath($this->assetsTemporaryPath . '/public');
    $manifestPath = public_path('build/themes/dusk/manifest.json');
    $manifest = File::json($manifestPath);
    if ($defect === 'malformed entry') {
        $manifest['resources/themes/dusk/' . $path] = [];
    } elseif ($defect === 'string entry') {
        $manifest['resources/themes/dusk/' . $path] = 'malformed';
    } else {
        unset($manifest['resources/themes/dusk/' . $path]);
    }
    File::put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
    Log::spy();

    expect(theme_asset($path))->toBe('http://localhost/build/themes/dusk/' . ($urlPath ?? $path));
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'Theme media asset is unavailable.'
            && $context['theme'] === 'dusk' && $context['path'] === $path,
    );
})->with([
    'missing image' => ['images/ghost.png', 'missing entry'],
    'missing font' => ['fonts/optional.woff2', 'missing entry'],
    'malformed image' => ['images/ghost.png', 'malformed entry'],
    'string image entry' => ['images/ghost.png', 'string entry'],
    'URL control characters' => ['images/%2e%2e/#logo?.svg', 'missing entry', 'images/%252e%252e/%23logo%3F.svg'],
]);

test('theme design images in guest pages resolve through the selected production manifest', function (string $theme, string $route, string $image): void {
    installHotel();
    setSetting('theme', $theme);

    $this->get(route($route))->assertOk()
        ->assertSee("http://localhost/build/themes/{$theme}/assets/{$theme}-{$image}-fixture.png", escape: false)
        ->assertDontSee("/assets/images/{$image}.png", escape: false);
})->with([
    'Atom registration hotel' => ['atom', 'register', 'hotel'],
    'Dusk welcome avatar' => ['dusk', 'welcome', 'ghost'],
]);

test('Dusk member design images and dynamic navigation resolve through its production manifest', function (string $route, string $image): void {
    installHotel();
    setSetting('theme', 'dusk');

    $this->actingAs(User::factory()->create())->get(route($route))->assertOk()
        ->assertSee("http://localhost/build/themes/dusk/assets/dusk-{$image}-fixture.png", escape: false)
        ->assertSee('http://localhost/build/themes/dusk/assets/dusk-community-fixture.png', escape: false)
        ->assertSee('http://localhost/build/themes/dusk/assets/dusk-home-fixture.png', escape: false)
        ->assertDontSee('/assets/images/dusk/', escape: false);
})->with([
    'dashboard circle' => ['me.show', 'me_circle_image'],
    'rules heading' => ['help-center.rules.index', 'exclamation-mark_icon'],
    'leaderboard heading' => ['leaderboard.index', 'leaderboards'],
    'news heading' => ['article.index', 'news'],
    'photos heading' => ['photos.index', 'camera_icon'],
]);

test('converted design images use only the selected theme development server', function (string $theme, string $route, string $image): void {
    installHotel();
    setSetting('theme', $theme);
    File::put(storage_path("vite/{$theme}.hot"), 'http://selected-vite.test:5187');

    $this->get(route($route))->assertOk()
        ->assertSee("http://selected-vite.test:5187/resources/themes/{$theme}/images/{$image}.png", escape: false)
        ->assertDontSee('/build/themes/', escape: false);
})->with([
    'Atom hotel' => ['atom', 'register', 'hotel'],
    'Dusk avatar' => ['dusk', 'welcome', 'ghost'],
    'Dusk navigation' => ['dusk', 'article.index', 'icons/navigation/community'],
]);

test('Dusk\'s banned page resolves its illustration through its Vite manifest', function (): void {
    installHotel();
    setSetting('theme', 'dusk');
    $member = User::factory()->create();
    Ban::create(['user_id' => $member->id, 'ip' => '', 'machine_id' => '', 'user_staff_id' => $member->id, 'timestamp' => time(), 'ban_expire' => time() + 3600, 'ban_reason' => 'Asset fallback proof', 'type' => 'account']);

    $this->actingAs($member)->get(route('banned.show'))->assertOk()
        ->assertSee('Asset fallback proof')
        ->assertSee('http://localhost/build/themes/dusk/assets/dusk-angry_frank-fixture.png', escape: false)
        ->assertDontSee('/assets/images/angry_frank.png', escape: false)
        ->assertSee('/build/themes/dusk/assets/dusk-app-fixture.css', escape: false)
        ->assertDontSee('/build/themes/atom/', escape: false);
    expect(realpath(theme_view('banned')->getPath()))->toBe(realpath(resource_path('themes/dusk/views/banned.blade.php')));
});

test('Atom\'s banned page resolves its illustration through its Vite manifest', function (): void {
    installHotel();
    setSetting('theme', 'atom');
    $member = User::factory()->create();
    Ban::create(['user_id' => $member->id, 'ip' => '', 'machine_id' => '', 'user_staff_id' => $member->id, 'timestamp' => time(), 'ban_expire' => time() + 3600, 'ban_reason' => 'Atom asset proof', 'type' => 'account']);

    $this->actingAs($member)->get(route('banned.show'))->assertOk()
        ->assertSee('Atom asset proof')
        ->assertSee('http://localhost/build/themes/atom/assets/atom-angry_frank-fixture.png', escape: false)
        ->assertDontSee('/assets/images/angry_frank.png', escape: false)
        ->assertSee('/build/themes/atom/assets/atom-app-fixture.css', escape: false);

    expect(realpath(theme_view('banned')->getPath()))->toBe(realpath(resource_path('themes/atom/views/banned.blade.php')));
});

test('Dusk registration serializes its manifest avatar URL safely into the classic inline script', function (): void {
    installHotel();
    setSetting('theme', 'dusk');

    $this->get(route('register'))->assertOk()
        ->assertSee('avatar.src = ' . Js::from('http://localhost/build/themes/dusk/assets/dusk-ghost-fixture.png')->toHtml() . ';', escape: false)
        ->assertDontSee('/assets/images/dusk/ghost.png', escape: false);
});

test('Dusk guest photo credits resolve through its production manifest', function (string $route): void {
    installHotel();
    setSetting('theme', 'dusk');
    $photographer = User::factory()->create();
    DB::table('camera_web')->insert(['user_id' => $photographer->id, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/asset-proof.png', 'visible' => true]);

    $this->get(route($route))->assertOk()
        ->assertSee('/camera/asset-proof.png', escape: false)
        ->assertSee('http://localhost/build/themes/dusk/assets/dusk-author_camera_icon-fixture.png', escape: false)
        ->assertDontSee('/assets/images/dusk/', escape: false);
})->with(['welcome', 'register']);

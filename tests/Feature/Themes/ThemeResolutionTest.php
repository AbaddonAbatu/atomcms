<?php

use App\Exceptions\ThemePresentationException;
use App\Models\User;
use App\Providers\ThemeServiceProvider;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Livewire\Blaze\Blaze;

/**
 * @param  list<string>  $views
 */
function themeResolutionWriteFixture(string $root, string $id, array $views = []): string
{
    $path = $root . '/' . $id;
    File::ensureDirectoryExists($path . '/css');
    File::ensureDirectoryExists($path . '/js');
    File::put($path . '/css/app.css', '');
    File::put($path . '/js/app.js', '');

    foreach ($views as $key) {
        $file = $path . '/views/' . str_replace('.', '/', $key) . '.blade.php';
        File::ensureDirectoryExists(dirname($file));
        File::put($file, '');
    }

    return $path;
}

beforeEach(function () {
    $this->resolutionTemporaryPath = sys_get_temp_dir() . '/atom-theme-resolution-' . bin2hex(random_bytes(12));
    $this->originalPublicPath = public_path();
    $this->originalStoragePath = storage_path();
    File::ensureDirectoryExists($this->resolutionTemporaryPath);
});

afterEach(function () {
    app()->usePublicPath($this->originalPublicPath);
    app()->useStoragePath($this->originalStoragePath);
    expect(realpath(dirname($this->resolutionTemporaryPath)))->toBe(realpath(sys_get_temp_dir()));
    File::deleteDirectory($this->resolutionTemporaryPath);
});

test('permanent namespaces resolve their own views across repeated lookups', function () {
    $finder = view()->getFinder();

    foreach (['dusk', 'atom', 'dusk'] as $id) {
        expect(realpath($finder->find("theme-{$id}::index")))
            ->toBe(realpath(resource_path("themes/{$id}/views/index.blade.php")));
    }

    foreach (['atom', 'dusk'] as $id) {
        expect($finder->getHints()["theme-{$id}"])
            ->toBe([app(ThemeValidator::class)->definition($id)->viewPath()]);
    }
});

test('legacy switching and HTTP requests preserve permanent namespace roots', function () {
    installHotel();
    $finder = view()->getFinder();
    $hints = array_intersect_key($finder->getHints(), array_flip(['theme-atom', 'theme-dusk']));

    foreach (['atom', 'dusk'] as $id) {
        expect($finder->getPaths())->not->toContain(realpath(resource_path("themes/{$id}/views")));
    }
    expect(array_intersect_key($finder->getHints(), $hints))->toBe($hints);

    setSetting('theme', 'dusk');
    $this->get(route('welcome'))->assertOk()->assertViewIs('theme-dusk::index');

    expect(realpath(theme_view('index')->getPath()))->toBe(realpath(resource_path('themes/dusk/views/index.blade.php')));
    expect(array_intersect_key($finder->getHints(), $hints))->toBe($hints);
});

test('anonymous component prefixes do not fall back to another theme', function () {
    $html = Blade::render('<x-theme-dusk::page-header sub-header="Fixture subtitle">Fixture heading</x-theme-dusk::page-header>');

    expect($html)->toContain('Fixture heading', 'Fixture subtitle', 'bg-[#21242e]');
    expect(fn () => Blade::render('<x-theme-atom::page-header sub-header="Fixture subtitle">Fixture heading</x-theme-atom::page-header>'))
        ->toThrow(InvalidArgumentException::class, 'Unable to locate a class or view for component [theme-atom::page-header].');
});

test('registering theme prefixes adds no fallback for unprefixed component tags', function () {
    expect(fn () => Blade::compileString('<x-page-header>Fixture heading</x-page-header>'))
        ->toThrow(InvalidArgumentException::class, 'Unable to locate a class or view for component [page-header].');
});

test('theme selection changes only at the next scope in either order', function (string $first, string $second) {
    installHotel();
    setSetting('theme', $first);
    $service = app(ThemeService::class);
    $definition = $service->selected();

    expect(realpath(theme_view('index', ['fixture' => 'data'])->getPath()))
        ->toBe(realpath(resource_path("themes/{$first}/views/index.blade.php")));
    expect(theme_view('index', ['fixture' => 'data'])->getData()['fixture'])->toBe('data');

    setSetting('theme', $second);
    expect($service->selected())->toBe($definition);
    expect(app(ThemeService::class))->toBe($service);

    app()->forgetScopedInstances();

    expect(app(ThemeService::class))->not->toBe($service);
    expect(realpath(theme_view('index')->getPath()))
        ->toBe(realpath(resource_path("themes/{$second}/views/index.blade.php")));

    setSetting('theme', $first);
    app()->forgetScopedInstances();
    expect(realpath(theme_view('index')->getPath()))
        ->toBe(realpath(resource_path("themes/{$first}/views/index.blade.php")));
})->with([
    'dusk then atom' => ['dusk', 'atom'],
    'atom then dusk' => ['atom', 'dusk'],
]);

test('empty and legacy selections use the administrative default', function (string $value, string $default) {
    installHotel();
    setSetting('theme', $value);
    config(['theme.default' => $default]);

    expect(realpath(theme_view('index')->getPath()))
        ->toBe(realpath(resource_path("themes/{$default}/views/index.blade.php")));
})->with(['empty' => '', 'legacy' => '1'])->with(['atom default' => 'atom', 'dusk default' => 'dusk']);

test('an unknown selected theme fails without choosing Atom', function () {
    installHotel();
    setSetting('theme', 'aurora');

    expect(fn () => theme_view('index'))
        ->toThrow(ThemePresentationException::class, 'Theme [aurora] is not installed.');
});

test('same-named root and sibling views cannot satisfy a missing theme view', function () {
    installHotel();
    $originalBase = config('theme.base_path');
    $root = $this->resolutionTemporaryPath . '/themes';
    $sibling = themeResolutionWriteFixture($root, 'sibling', views: ['banned']);
    themeResolutionWriteFixture($root, 'child', views: ['index']);
    $finderRoot = $this->resolutionTemporaryPath . '/root-view';
    File::ensureDirectoryExists($finderRoot);
    File::put($finderRoot . '/maintenance.blade.php', 'Root fixture maintenance');
    $resolvedRoot = str_replace('\\', '/', realpath($root));
    config(['theme.base_path' => $root]);
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetInstance(ThemeService::class);
    View::getFinder()->setPaths([resource_path('views')]);
    View::getFinder()->flush();
    $this->app->register(ThemeServiceProvider::class, true);
    View::getFinder()->addLocation($sibling . '/views');
    View::getFinder()->prependLocation($finderRoot);
    View::getFinder()->flush();

    try {
        setSetting('theme', 'child');

        expect(realpath(view('banned')->getPath()))->toBe(realpath($sibling . '/views/banned.blade.php'));
        expect(fn () => theme_view('banned'))->toThrow(
            ThemePresentationException::class,
            'Theme [child] does not provide view [banned]; expected ' . $resolvedRoot . '/child/views/banned.blade.php.',
        );

        app()->forgetScopedInstances();
        setSetting('theme', 'child');

        expect(realpath(view('maintenance')->getPath()))->toBe(realpath($finderRoot . '/maintenance.blade.php'));
        expect(fn () => theme_view('maintenance'))->toThrow(
            ThemePresentationException::class,
            'Theme [child] does not provide view [maintenance]; expected ' . $resolvedRoot . '/child/views/maintenance.blade.php.',
        );
    } finally {
        config(['theme.base_path' => $originalBase]);
        app()->forgetInstance(ThemeValidator::class);
        app()->forgetInstance(ThemeService::class);
        app()->forgetScopedInstances();
    }
});

test('explicit optional lookups return null for missing non-core views', function (string $id) {
    installHotel();
    setSetting('theme', $id);
    $service = app(ThemeService::class);

    expect($service->optionalView('home.widgets.my-groups'))->toBeNull();
    expect(realpath($service->optionalView('home.widgets.my-profile', ['fixture' => 'data'])->getPath()))
        ->toBe(realpath(resource_path("themes/{$id}/views/home/widgets/my-profile.blade.php")));
    expect($service->optionalView('home.widgets.my-profile', ['fixture' => 'data'])->getData()['fixture'])->toBe('data');
    expect(fn () => $service->view('home.widgets.my-groups'))
        ->toThrow(ThemePresentationException::class, 'does not provide view [home.widgets.my-groups]');
    expect($service->optionalView('unlisted.missing'))->toBeNull();
})->with(['atom', 'dusk']);

test('the optional operation still rejects a missing required view', function () {
    installHotel();
    $originalBase = config('theme.base_path');
    $root = $this->resolutionTemporaryPath . '/themes';
    themeResolutionWriteFixture($root, 'fixture', views: array_values(array_diff(ThemeValidator::REQUIRED_VIEWS, ['banned'])));
    $resolvedRoot = str_replace('\\', '/', realpath($root));
    config(['theme.base_path' => $root]);
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetInstance(ThemeService::class);
    view()->getFinder()->flush();
    $this->app->register(ThemeServiceProvider::class, true);
    setSetting('theme', 'fixture');

    try {
        expect(app(ThemeService::class)->optionalView('home.widgets.my-profile'))->toBeNull();
        expect(fn () => app(ThemeService::class)->optionalView('banned'))
            ->toThrow(ThemePresentationException::class, 'expected ' . $resolvedRoot . '/fixture/views/banned.blade.php.');
    } finally {
        config(['theme.base_path' => $originalBase]);
        app()->forgetInstance(ThemeValidator::class);
        app()->forgetInstance(ThemeService::class);
        app()->forgetScopedInstances();
    }
});

test('a foreign namespace graft is rejected by both view operations', function () {
    installHotel();
    setSetting('theme', 'atom');
    $theme = app(ThemeValidator::class)->definition('atom');
    $foreignRoot = app(ThemeValidator::class)->definition('dusk')->viewPath();
    view()->getFinder()->prependNamespace('theme-atom', $foreignRoot);
    $foreignFile = view()->getFinder()->find('theme-atom::index');

    expect(realpath($foreignFile))->toBe(realpath(resource_path('themes/dusk/views/index.blade.php')));
    $message = "Theme [atom] resolved view [index] outside its own view root [{$theme->viewPath()}]: {$foreignFile}.";
    expect(fn () => theme_view('index'))->toThrow(ThemePresentationException::class, $message);
    expect(fn () => app(ThemeService::class)->optionalView('index'))->toThrow(ThemePresentationException::class, $message);
});

test('Blaze compiles only theme components with its existing defaults', function () {
    foreach (['atom', 'dusk'] as $id) {
        $component = File::allFiles(resource_path("themes/{$id}/views/components"))[0]->getPathname();
        expect(Blaze::optimize()->shouldCompile($component))->toBeTrue();
        expect(Blaze::optimize()->shouldMemoize($component))->toBeFalse();
        expect(Blaze::optimize()->shouldFold($component))->toBeFalse();
        expect(Blaze::optimize()->shouldCompile(resource_path("themes/{$id}/views/index.blade.php")))->toBeFalse();
    }

    expect(Blaze::optimize()->shouldCompile(resource_path('views/core/components/form/primary-button.blade.php')))->toBeFalse();
});

test('an invalid inactive candidate does not break provider boot or legacy pages', function () {
    installHotel();
    $originalBase = config('theme.base_path');
    $root = $this->resolutionTemporaryPath . '/themes';
    $fixture = $root . '/fixture';
    File::ensureDirectoryExists($fixture . '/css');
    File::ensureDirectoryExists($fixture . '/views');
    File::ensureDirectoryExists($root . '/Bad_Theme');
    File::put($fixture . '/css/app.css', '');
    File::put($fixture . '/views/index.blade.php', 'Fixture theme');
    config(['theme.base_path' => $root]);
    app()->forgetInstance(ThemeValidator::class);

    $this->app->register(ThemeServiceProvider::class, true);

    expect(realpath(view()->getFinder()->find('theme-fixture::index')))
        ->toBe(realpath($fixture . '/views/index.blade.php'));
    expect(view()->getFinder()->getHints())->not->toHaveKey('theme-Bad_Theme');
    setSetting('theme', 'Bad_Theme');
    expect(fn () => theme_view('index'))
        ->toThrow(ThemePresentationException::class, 'Theme [Bad_Theme] is invalid: Invalid theme ID [Bad_Theme]; use lowercase letters, digits and single hyphens, starting with a letter.');

    config(['theme.base_path' => $originalBase]);
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetScopedInstances();
    setSetting('theme', 'atom');
    grantHousekeepingPermission('can_access_housekeeping', 6);
    setSetting('force_staff_2fa', '0');
    $staff = User::factory()->create(['rank' => 6]);

    $this->actingAs($staff)->get('/housekeeping')->assertOk();
    auth()->forgetGuards();
    $this->get(route('welcome'))->assertOk()->assertViewIs('theme-atom::index');
    $this->assertGuest();
    expect(realpath(view()->getFinder()->find('theme-atom::index')))
        ->toBe(realpath(resource_path('themes/atom/views/index.blade.php')));
});

test('theme Vite uses its own manifest and preserves shared instance configuration', function () {
    installHotel();
    setSetting('theme', 'atom');
    $this->withVite();
    app()->forgetInstance(Vite::class);
    app()->usePublicPath($this->resolutionTemporaryPath . '/public');
    app()->useStoragePath($this->resolutionTemporaryPath . '/storage');
    File::ensureDirectoryExists(public_path('build/themes/atom'));
    File::put(public_path('build/themes/atom/manifest.json'), json_encode([
        'resources/themes/atom/css/app.css' => ['file' => 'assets/atom-fixture.css', 'src' => 'resources/themes/atom/css/app.css', 'isEntry' => true],
        'resources/themes/atom/js/app.js' => ['file' => 'assets/atom-fixture.js', 'src' => 'resources/themes/atom/js/app.js', 'isEntry' => true],
        'resources/themes/atom/images/fixture.png' => ['file' => 'assets/fixture-hashed.png'],
    ], JSON_THROW_ON_ERROR));
    $shared = app(Vite::class);
    app()->instance(Vite::class, $shared);

    $tags = theme_vite();

    expect($tags)->toBeInstanceOf(HtmlString::class);
    expect($tags->toHtml())->toContain(
        '<link rel="stylesheet" href="http://localhost/build/themes/atom/assets/atom-fixture.css"',
        '<script type="module" src="http://localhost/build/themes/atom/assets/atom-fixture.js" data-turbolinks-eval="false"></script>',
    )->not->toContain('dusk');
    expect(theme_asset('images/fixture.png'))->toBe('http://localhost/build/themes/atom/assets/fixture-hashed.png');
    expect(app(Vite::class))->toBe($shared);
    expect(preg_replace('~/+~', '/', str_replace('\\', '/', $shared->hotFile())))
        ->toBe(str_replace('\\', '/', storage_path('vite/core.hot')));
    expect((string) $shared(['resources/themes/atom/css/app.css'], 'build/themes/atom'))
        ->toContain('/build/themes/atom/assets/atom-fixture.css');
});

test('theme Vite resolves call time swaps and the selected theme hot server', function () {
    installHotel();
    setSetting('theme', 'dusk');
    app()->usePublicPath($this->resolutionTemporaryPath . '/public');
    app()->useStoragePath($this->resolutionTemporaryPath . '/storage');
    File::ensureDirectoryExists(storage_path('vite'));
    File::put(storage_path('vite/dusk.hot'), 'http://dusk-vite.test:5173');

    expect(theme_vite()->toHtml())->toBe('');
    $this->withVite();
    app()->forgetInstance(Vite::class);
    $shared = app(Vite::class);
    app()->instance(Vite::class, $shared);

    expect(theme_vite()->toHtml())->toContain(
        'http://dusk-vite.test:5173/@vite/client',
        'http://dusk-vite.test:5173/resources/themes/dusk/css/app.css',
        'http://dusk-vite.test:5173/resources/themes/dusk/js/app.js',
    )->not->toContain('resources/themes/atom/', '/build/');
    expect(preg_replace('~/+~', '/', str_replace('\\', '/', $shared->hotFile())))
        ->toBe(str_replace('\\', '/', storage_path('vite/core.hot')));
    expect(theme_asset('images/fixture.png'))->toBe('http://dusk-vite.test:5173/resources/themes/dusk/images/fixture.png');

    $this->withoutVite();
    expect(theme_vite()->toHtml())->toBe('');
});

test('theme assets reject non-normalized paths before accessing Vite', function (string $path) {
    installHotel();
    setSetting('theme', 'atom');

    expect(fn () => theme_asset($path))->toThrow(
        ThemePresentationException::class,
        "Theme [atom] asset path [{$path}] must be a normalized relative path inside the theme.",
    );
})->with([
    'traversal' => '../dusk/images/x.png',
    'absolute' => '/etc/passwd',
    'Windows drive' => 'C:/x.png',
    'backslash' => 'images\\x.png',
    'dot segment' => './images/x.png',
    'empty' => '',
    'empty segment' => 'images//x.png',
    'trailing slash' => 'images/x.png/',
    'null byte' => "images/x.png\0",
]);

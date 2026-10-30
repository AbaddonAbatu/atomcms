<?php

use App\Models\User;

/**
 * Each theme owns its production output and development hot file.
 * Real Vite renders owner-specific fixtures, including canonical legacy selection,
 * without reading a developer's build output or storage hot markers.
 */
beforeEach(function (): void {
    $this->assetOriginalPublicPath = public_path();
    $this->assetOriginalStoragePath = storage_path();
    app()->useStoragePath(base_path('tests/Fixtures/vite/manifest'));
    installHotel();

    $this->withVite();
});

afterEach(function (): void {
    app()->usePublicPath($this->assetOriginalPublicPath);
    app()->useStoragePath($this->assetOriginalStoragePath);
});

test('the guest home page links only its own theme\'s build assets', function (string $theme, string $otherTheme): void {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    setSetting('theme', $theme);

    $this->get(route('welcome'))
        ->assertSee('<link rel="stylesheet" href="http://localhost/build/themes/' . $theme . '/assets/' . $theme . '-app-fixture.css" />', escape: false)
        ->assertSee('<script type="module" src="http://localhost/build/themes/' . $theme . '/assets/' . $theme . '-app-fixture.js" data-turbolinks-eval="false"></script>', escape: false)
        ->assertDontSee($otherTheme . '-app-fixture', escape: false);
})->with([
    'atom theme' => ['atom', 'dusk'],
    'dusk theme' => ['dusk', 'atom'],
]);

test('signed-in pages link only their own theme\'s build assets', function (string $theme, string $otherTheme, string $route, string $view): void {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertViewIs("theme-{$theme}::{$view}")
        ->assertSee('<link rel="stylesheet" href="http://localhost/build/themes/' . $theme . '/assets/' . $theme . '-app-fixture.css" />', escape: false)
        ->assertSee('<script type="module" src="http://localhost/build/themes/' . $theme . '/assets/' . $theme . '-app-fixture.js" data-turbolinks-eval="false"></script>', escape: false)
        ->assertDontSee($otherTheme . '-app-fixture', escape: false);
})->with([
    'atom theme' => ['atom', 'dusk'],
    'dusk theme' => ['dusk', 'atom'],
])->with([
    'me page' => ['me.show', 'user.me'],
    'nitro client page' => ['nitro-client', 'client.nitro'],
]);

test('Dusk public navigation icons use its own theme build assets', function (): void {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    setSetting('theme', 'dusk');

    $this->get(route('article.index'))
        ->assertOk()
        ->assertSee([
            'http://localhost/build/themes/dusk/assets/dusk-community-fixture.png',
            'http://localhost/build/themes/dusk/assets/dusk-leaderboards-fixture.png',
            'http://localhost/build/themes/dusk/assets/dusk-news-fixture.png',
            'http://localhost/build/themes/dusk/assets/dusk-shop-fixture.png',
            'http://localhost/build/themes/dusk/assets/dusk-home-fixture.png',
        ])
        ->assertDontSee('/assets/images/dusk/');
});

test('the maintenance notice links the active theme\'s build assets', function (string $theme, string $otherTheme): void {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    setSetting('theme', $theme);
    setSetting('maintenance_enabled', '1');

    $this->get(route('maintenance.show'))
        ->assertViewIs("theme-{$theme}::maintenance")
        ->assertSee('<link rel="stylesheet" href="http://localhost/build/themes/' . $theme . '/assets/' . $theme . '-app-fixture.css" />', escape: false)
        ->assertSee('<script type="module" src="http://localhost/build/themes/' . $theme . '/assets/' . $theme . '-app-fixture.js" data-turbolinks-eval="false"></script>', escape: false)
        ->assertDontSee($otherTheme . '-app-fixture', escape: false);
})->with([
    'atom theme' => ['atom', 'dusk'],
    'dusk theme' => ['dusk', 'atom'],
]);

test('both themes request their own entries from separate hot dev server markers', function (string $theme, string $otherTheme, int $port): void {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath(base_path('tests/Fixtures/vite/hot'));
    setSetting('theme', $theme);

    $this->get(route('welcome'))
        ->assertSee('<script type="module" src="http://vite.test:' . $port . '/@vite/client" data-turbolinks-eval="false"></script>', escape: false)
        ->assertSee('<link rel="stylesheet" href="http://vite.test:' . $port . '/resources/themes/' . $theme . '/css/app.css" />', escape: false)
        ->assertSee('<script type="module" src="http://vite.test:' . $port . '/resources/themes/' . $theme . '/js/app.js" data-turbolinks-eval="false"></script>', escape: false)
        ->assertDontSee('resources/themes/' . $otherTheme . '/', escape: false)
        ->assertDontSee('/build/assets/', escape: false);
})->with([
    'atom theme' => ['atom', 'dusk', 5173],
    'dusk theme' => ['dusk', 'atom', 5174],
]);

test('a legacy or empty theme setting renders Atom views with Atom build assets', function (string $theme, string $missingEntry): void {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    setSetting('theme', $theme);

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('http://localhost/build/themes/atom/assets/atom-app-fixture.css', escape: false)
        ->assertSee('http://localhost/build/themes/atom/assets/atom-app-fixture.js', escape: false)
        ->assertSee('<script src="http://localhost/assets/js/popper.min.js"></script>', escape: false)
        ->assertDontSee('dusk-app-fixture', escape: false)
        ->assertDontSee($missingEntry, escape: false)
        ->assertDontSee('resources/themes/1/', escape: false)
        ->assertDontSee('resources/themes//', escape: false);
})->with([
    'legacy value 1' => ['1', 'resources/themes/1/css/app.css'],
    'empty string' => ['', 'resources/themes//css/app.css'],
]);

test('a legacy or empty theme setting renders Atom views with Atom hot assets', function (string $theme, string $requestedEntryPath): void {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath(base_path('tests/Fixtures/vite/hot'));
    setSetting('theme', $theme);

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('<script src="http://localhost/assets/js/popper.min.js"></script>', escape: false)
        ->assertDontSee('/assets/js/dusk.js', escape: false)
        ->assertSee('<link rel="stylesheet" href="http://vite.test:5173/resources/themes/atom/css/app.css" />', escape: false)
        ->assertSee('<script type="module" src="http://vite.test:5173/resources/themes/atom/js/app.js" data-turbolinks-eval="false"></script>', escape: false)
        ->assertDontSee($requestedEntryPath . '/css/app.css', escape: false)
        ->assertDontSee('resources/themes/1/', escape: false)
        ->assertDontSee('resources/themes//', escape: false);
})->with([
    'legacy value 1' => ['1', 'resources/themes/1'],
    'empty string' => ['', 'resources/themes/'],
]);

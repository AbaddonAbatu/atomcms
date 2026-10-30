<?php

use App\Data\ThemeDefinition;
use App\Exceptions\ThemePresentationException;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * @param  list<string>  $views
 */
function writeThemeFixture(string $root, string $id = 'aurora', array $views = []): string
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

function freshThemeValidator(): ThemeValidator
{
    app()->forgetInstance(ThemeValidator::class);

    return app()->make(ThemeValidator::class);
}

function useThemeAssetFixturePaths(string $temporary): void
{
    app()->usePublicPath($temporary . '/public');
    app()->useStoragePath($temporary . '/storage');
    File::ensureDirectoryExists(public_path());
    File::ensureDirectoryExists(storage_path('vite'));
}

beforeEach(function () {
    $this->themeTemporaryPath = sys_get_temp_dir() . '/atom-theme-contract-' . bin2hex(random_bytes(12));
    $this->themeRoot = $this->themeTemporaryPath . '/themes';
    File::ensureDirectoryExists($this->themeRoot);
    config(['theme.base_path' => $this->themeRoot]);

    // The provider discovered the real themes at boot; commands resolve the singleton.
    app()->forgetInstance(ThemeValidator::class);
});

afterEach(function () {
    File::deleteDirectory($this->themeTemporaryPath);
});

test('bundled themes use folder names and conventional asset paths', function () {
    config(['theme.base_path' => resource_path('themes')]);
    $validator = freshThemeValidator();

    expect(array_keys($validator->definitions()))->toBe(['atom', 'dusk']);
    expect($validator->rejected())->toBe([]);

    foreach (['atom', 'dusk'] as $id) {
        $theme = $validator->definition($id);
        expect($theme)->toBeInstanceOf(ThemeDefinition::class);
        expect($theme->id)->toBe($id);
        expect($theme->path)->toBe(realpath(resource_path('themes/' . $id)));
        expect($theme->namespace())->toBe('theme-' . $id);
        expect($theme->viewPath())->toBe($theme->path . DIRECTORY_SEPARATOR . 'views');
        expect($theme->componentPath())->toBe($theme->viewPath() . DIRECTORY_SEPARATOR . 'components');
        expect($theme->sourceDirectory())->toBe('resources/themes/' . $id);
        expect($theme->buildDirectory())->toBe('build/themes/' . $id);
        expect(str_replace('\\', '/', $theme->hotFile()))->toEndWith('/storage/vite/' . $id . '.hot');
        expect($theme->entrypoints())->toBe(['css/app.css', 'js/app.js']);
        expect($theme->viteEntrypoints())->toBe(['resources/themes/' . $id . '/css/app.css', 'resources/themes/' . $id . '/js/app.js']);
        expect($theme->viewFile('auth.passwords.reset'))->toBe('resources/themes/' . $id . '/views/auth/passwords/reset.blade.php');
    }
});

test('external theme roots stay absolute and hot paths are computed at call time', function () {
    writeThemeFixture($this->themeRoot);
    $theme = freshThemeValidator()->definition('aurora');

    expect($theme->sourceDirectory())->toBe(str_replace('\\', '/', realpath($this->themeRoot . '/aurora')));
    app()->useStoragePath($this->themeTemporaryPath . '/changed-storage');
    expect($theme->hotFile())->toBe(storage_path('vite/aurora.hot'));
});

test('a source-less production theme can activate with only the core views and its compiled build', function (): void {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    $source = writeThemeFixture($this->themeRoot, views: [
        'index', 'auth.login', 'auth.register', 'auth.passwords.forget', 'auth.passwords.reset', 'auth.passwords.confirm', 'auth.two-factor-challenge',
        'user.me', 'user.settings.account', 'user.settings.password', 'user.settings.two-factor', 'banned', 'maintenance', 'client.nitro',
    ]);
    File::put($source . '/views/index.blade.php', '<!doctype html><html><head>{{ theme_vite() }}</head><body>Source-free theme</body></html>');
    $theme = freshThemeValidator()->definition('aurora');
    $build = public_path($theme->buildDirectory());
    File::ensureDirectoryExists($build . '/assets');
    File::put($build . '/assets/app.css', '');
    File::put($build . '/assets/app.js', '');
    File::put($build . '/manifest.json', json_encode([
        $theme->viteEntrypoints()[0] => ['file' => 'assets/app.css', 'src' => $theme->viteEntrypoints()[0], 'isEntry' => true],
        $theme->viteEntrypoints()[1] => ['file' => 'assets/app.js', 'src' => $theme->viteEntrypoints()[1], 'isEntry' => true],
    ], JSON_THROW_ON_ERROR));
    File::delete([$source . '/css/app.css', $source . '/js/app.js']);

    expect(freshThemeValidator()->activationProblems('aurora', true))->toBe([]);

    installHotel();
    setSetting('theme', 'aurora');
    View::replaceNamespace($theme->namespace(), [$theme->viewPath()]);
    app()->forgetInstance(ThemeService::class);
    $this->withVite();
    app()->forgetInstance(Vite::class);

    $this->get(route('welcome'))->assertOk()
        ->assertSee('http://localhost/build/themes/aurora/assets/app.css', escape: false)
        ->assertSee('http://localhost/build/themes/aurora/assets/app.js', escape: false);
});

test('discovery ignores files and missing roots and memoizes per instance', function () {
    config(['theme.base_path' => $this->themeRoot . '/missing']);
    $missingValidator = freshThemeValidator();
    expect($missingValidator->definitions())->toBe([]);
    expect($missingValidator->rejected())->toBe([]);

    config(['theme.base_path' => $this->themeRoot]);
    File::put($this->themeRoot . '/not-a-theme', '{}');
    writeThemeFixture($this->themeRoot, 'zebra');
    writeThemeFixture($this->themeRoot, 'alpha');
    File::ensureDirectoryExists($this->themeRoot . '/z-Rejected');
    File::ensureDirectoryExists($this->themeRoot . '/a-Rejected');
    $validator = freshThemeValidator();

    expect(array_keys($validator->definitions()))->toBe(['alpha', 'zebra']);
    expect(array_keys($validator->rejected()))->toBe(['a-Rejected', 'z-Rejected']);

    writeThemeFixture($this->themeRoot, 'later');
    config(['theme.base_path' => $this->themeRoot . '/missing']);
    expect(array_keys($validator->definitions()))->toBe(['alpha', 'zebra']);
    config(['theme.base_path' => $this->themeRoot]);
    expect(array_keys(freshThemeValidator()->definitions()))->toBe(['alpha', 'later', 'zebra']);
});

test('selectable IDs are the well-formed themes, with or without their views and built assets', function () {
    writeThemeFixture($this->themeRoot, 'zebra');
    writeThemeFixture($this->themeRoot, 'alpha');
    writeThemeFixture($this->themeRoot, 'Bad_Theme');
    File::ensureDirectoryExists($this->themeRoot . '/empty');
    $validator = freshThemeValidator();

    expect($validator->selectableIds())->toBe(['alpha', 'empty', 'zebra'])
        ->and($validator->missingViews($validator->definition('alpha')))->not->toBe([])
        ->and($validator->assetProblems($validator->definition('alpha'), production: true))->not->toBe([]);
});

test('invalid and reserved theme IDs do not hide valid siblings', function (string $id, string $problem) {
    writeThemeFixture($this->themeRoot, $id);
    writeThemeFixture($this->themeRoot, 'other');
    $validator = freshThemeValidator();

    expect($validator->rejected())->toBe([$id => [$problem]]);
    expect(array_keys($validator->definitions()))->toBe(['other']);
})->with([
    'uppercase and underscore' => ['Bad_Theme', 'Invalid theme ID [Bad_Theme]; use lowercase letters, digits and single hyphens, starting with a letter.'],
    'leading digit' => ['9lives', 'Invalid theme ID [9lives]; use lowercase letters, digits and single hyphens, starting with a letter.'],
    'trailing hyphen' => ['aurora-', 'Invalid theme ID [aurora-]; use lowercase letters, digits and single hyphens, starting with a letter.'],
    'hidden directory' => ['.hidden', 'Invalid theme ID [.hidden]; use lowercase letters, digits and single hyphens, starting with a letter.'],
    'core' => ['core', 'Theme ID [core] is reserved.'],
    'housekeeping' => ['housekeeping', 'Theme ID [housekeeping] is reserved.'],
    'vendor' => ['vendor', 'Theme ID [vendor] is reserved.'],
    'all (npm build:all)' => ['all', 'Theme ID [all] is reserved.'],
]);

test('folders are discovered without manifests, views or source assets', function () {
    File::ensureDirectoryExists($this->themeRoot . '/aurora');
    writeThemeFixture($this->themeRoot, 'other');
    $validator = freshThemeValidator();

    expect($validator->rejected())->toBe([]);
    expect(array_keys($validator->definitions()))->toBe(['aurora', 'other']);
    expect($validator->definition('aurora')->id)->toBe('aurora');
    expect($validator->activationProblems('aurora', true))->not->toBe([]);
});

test('legacy JSON manifests are ignored during discovery', function (string $json) {
    $path = writeThemeFixture($this->themeRoot);
    File::put($path . '/theme.json', $json);
    writeThemeFixture($this->themeRoot, 'other');
    $validator = freshThemeValidator();

    expect($validator->rejected())->toBe([]);
    expect(array_keys($validator->definitions()))->toBe(['aurora', 'other']);
})->with(['{', '[]', '"aurora"', '{}', '{"schema":1}']);

test('legacy metadata cannot override folder names or conventional entrypoints', function (array $metadata) {
    $path = writeThemeFixture($this->themeRoot);
    File::put($path . '/theme.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    $validator = freshThemeValidator();
    $theme = $validator->definition('aurora');

    expect($validator->rejected())->toBe([]);
    expect($theme->id)->toBe('aurora');
    expect($theme->entrypoints())->toBe(['css/app.css', 'js/app.js']);
})->with([
    'old schema' => [['schema' => 2]],
    'old contract' => [['presentationContract' => 2]],
    'custom name' => [['name' => 'Another Name']],
    'custom inputs' => [['entrypoints' => ['css/missing.css']]],
    'inheritance' => [['parent' => 'other']],
]);

test('asset paths must be normalized and relative', function (string $path) {
    expect(freshThemeValidator()->isRelativeThemePath($path))->toBeFalse();
})->with([
    'empty' => [''],
    'absolute' => ['/css/app.css'],
    'drive absolute' => ['C:/css/app.css'],
    'drive relative' => ['C:css/app.css'],
    'UNC' => ['//server/share/app.css'],
    'Windows UNC' => ['\\\\server\\share\\app.css'],
    'foreign traversal' => ['../other/css/app.css'],
    'dot segment' => ['./css/app.css'],
    'backslash' => ['css\\app.css'],
    'empty segment' => ['css//app.css'],
    'trailing slash' => ['css/app.css/'],
    'null byte' => ["css/app.css\0"],
]);

test('candidate symlinks cannot escape the canonical root', function () {
    $outside = $this->themeTemporaryPath . '/outside';
    File::ensureDirectoryExists($outside);
    writeThemeFixture($this->themeRoot, 'other');
    $link = $this->themeRoot . '/escape';

    if (! function_exists('symlink') || ! @symlink($outside, $link)) {
        $this->markTestSkipped('Directory symlinks are unavailable on this host.');
    }

    $validator = freshThemeValidator();

    expect($validator->rejected())->toBe(['escape' => ['Theme [escape] must resolve to a direct child of the theme base path.']]);
    expect(array_keys($validator->definitions()))->toBe(['other']);
});

test('entrypoint symlinks cannot escape into another theme', function () {
    $path = writeThemeFixture($this->themeRoot);
    $other = writeThemeFixture($this->themeRoot, 'other');
    File::delete($path . '/css/app.css');

    if (! function_exists('symlink') || ! @symlink($other . '/css/app.css', $path . '/css/app.css')) {
        $this->markTestSkipped('File symlinks are unavailable on this host.');
    }

    $validator = freshThemeValidator();

    expect($validator->rejected())->toBe(['aurora' => ['Entrypoint [css/app.css] resolves outside the theme.']]);
    expect(array_keys($validator->definitions()))->toBe(['other']);
});

test('unknown and rejected definitions throw precise diagnostics', function () {
    writeThemeFixture($this->themeRoot, 'Bad_Theme');
    $validator = freshThemeValidator();

    expect(fn () => $validator->definition('missing'))->toThrow(ThemePresentationException::class, 'Theme [missing] is not installed.');
    expect(fn () => $validator->definition('Bad_Theme'))->toThrow(ThemePresentationException::class, 'Theme [Bad_Theme] is invalid: Invalid theme ID [Bad_Theme]; use lowercase letters, digits and single hyphens, starting with a letter.');
});

test('all required views satisfy the contract without the optional groups widget', function () {
    writeThemeFixture($this->themeRoot, views: ThemeValidator::REQUIRED_VIEWS);
    $validator = freshThemeValidator();

    expect(ThemeValidator::REQUIRED_VIEWS)->toHaveCount(14);
    expect($validator->missingViews($validator->definition('aurora')))->toBe([]);
    expect(File::exists($this->themeRoot . '/aurora/views/home/widgets/my-groups.blade.php'))->toBeFalse();
});

test('missing views cannot be supplied by a sibling theme or the root view finder', function () {
    $path = writeThemeFixture($this->themeRoot, views: ThemeValidator::REQUIRED_VIEWS);
    writeThemeFixture($this->themeRoot, 'other', views: ['auth.passwords.reset']);
    File::delete($path . '/views/auth/passwords/reset.blade.php');
    $rootFixture = $this->themeTemporaryPath . '/root-view';
    File::ensureDirectoryExists($rootFixture . '/auth/passwords');
    File::put($rootFixture . '/auth/passwords/reset.blade.php', 'Root fixture');
    view()->getFinder()->addLocation($rootFixture);
    $validator = freshThemeValidator();
    $theme = $validator->definition('aurora');

    expect(view('auth.passwords.reset')->render())->toBe('Root fixture');
    expect($validator->missingViews($theme))->toBe([
        'auth.passwords.reset' => str_replace('\\', '/', realpath($path)) . '/views/auth/passwords/reset.blade.php',
    ]);
});

test('bundled themes provide every required view in their own root', function () {
    config(['theme.base_path' => resource_path('themes')]);
    $validator = freshThemeValidator();

    foreach (['atom', 'dusk'] as $id) {
        expect($validator->missingViews($validator->definition($id)))->toBe([]);
    }
});

test('assets require a build manifest when there is no hot file', function () {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    writeThemeFixture($this->themeRoot);
    $validator = freshThemeValidator();

    expect($validator->assetProblems($validator->definition('aurora'), false))->toBe([
        'Missing build manifest ' . str_replace('\\', '/', public_path('build/themes/aurora/manifest.json')) . '.',
    ]);
});

test('build manifests must decode to JSON objects', function (string $json, string $message) {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    writeThemeFixture($this->themeRoot);
    File::ensureDirectoryExists(public_path('build/themes/aurora'));
    $path = public_path('build/themes/aurora/manifest.json');
    File::put($path, $json);
    $validator = freshThemeValidator();

    expect($validator->assetProblems($validator->definition('aurora'), false))->toBe([
        str_replace(':path', str_replace('\\', '/', $path), $message),
    ]);
})->with([
    'malformed' => ['{', 'Cannot read build manifest :path: Syntax error.'],
    'list' => ['[]', 'Build manifest :path must be a JSON object.'],
    'scalar' => ['true', 'Build manifest :path must be a JSON object.'],
]);

test('build manifests must contain every fully qualified entrypoint', function (string $entrypoint): void {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    writeThemeFixture($this->themeRoot);
    $validator = freshThemeValidator();
    $theme = $validator->definition('aurora');
    $path = public_path('build/themes/aurora/manifest.json');
    File::ensureDirectoryExists(dirname($path));
    $manifest = array_fill_keys($theme->viteEntrypoints(), ['file' => 'app.js']);
    unset($manifest[$theme->sourceDirectory() . '/' . $entrypoint]);
    File::put($path, json_encode($manifest, JSON_THROW_ON_ERROR));

    expect($validator->assetProblems($theme, false))->toBe([
        'Build manifest ' . str_replace('\\', '/', $path) . ' has no entry for ' . $theme->sourceDirectory() . '/' . $entrypoint . '.',
    ]);

    File::put($path, json_encode(array_fill_keys($theme->viteEntrypoints(), ['file' => 'app.js']), JSON_THROW_ON_ERROR));
    expect($validator->assetProblems($theme, false))->toBe([]);
    expect($validator->assetProblems($theme, true))->toBe([]);
})->with(['css/app.css', 'js/app.js']);

test('build manifest entrypoints require a string emitted file', function (string $entrypoint, mixed $entry): void {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    writeThemeFixture($this->themeRoot);
    $validator = freshThemeValidator();
    $theme = $validator->definition('aurora');
    $path = public_path('build/themes/aurora/manifest.json');
    File::ensureDirectoryExists(dirname($path));
    $manifest = array_fill_keys($theme->viteEntrypoints(), ['file' => 'app.js']);
    $manifest[$theme->sourceDirectory() . '/' . $entrypoint] = $entry;
    File::put($path, json_encode($manifest, JSON_THROW_ON_ERROR));

    expect($validator->assetProblems($theme, true))->toBe([
        'Build manifest ' . str_replace('\\', '/', $path) . ' entry ' . $theme->sourceDirectory() . '/' . $entrypoint . ' has no emitted file.',
    ]);
})->with(['css/app.css', 'js/app.js'])->with([
    'missing file' => [(object) []],
    'null file' => [['file' => null]],
    'non-string file' => [['file' => 123]],
    'non-object entry' => ['app.js'],
]);

test('a hot file is proof only in development and must be removed for deployment', function () {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    writeThemeFixture($this->themeRoot);
    $validator = freshThemeValidator();
    $theme = $validator->definition('aurora');
    File::put($theme->hotFile(), 'http://localhost:5173');

    expect($validator->assetProblems($theme, false))->toBe([]);
    expect($validator->assetProblems($theme, true))->toBe([
        'Remove the development hot file ' . str_replace('\\', '/', $theme->hotFile()) . ' before deploying.',
        'Missing build manifest ' . str_replace('\\', '/', public_path('build/themes/aurora/manifest.json')) . '.',
    ]);

    $path = public_path('build/themes/aurora/manifest.json');
    File::ensureDirectoryExists(dirname($path));
    File::put(dirname($path) . '/app.js', '');
    File::put($path, json_encode(array_fill_keys($theme->viteEntrypoints(), ['file' => 'app.js']), JSON_THROW_ON_ERROR));
    expect($validator->assetProblems($theme, true))->toBe([
        'Remove the development hot file ' . str_replace('\\', '/', $theme->hotFile()) . ' before deploying.',
    ]);
});

test('validation command reports all candidates and can select a single valid theme', function () {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    writeThemeFixture($this->themeRoot, views: ThemeValidator::REQUIRED_VIEWS);
    File::ensureDirectoryExists($this->themeRoot . '/Bad_Theme');
    File::put(storage_path('vite/aurora.hot'), 'http://localhost:5173');

    $this->artisan('atom:themes:validate')
        ->expectsOutput('aurora: valid')
        ->expectsOutput('Bad_Theme: rejected (not selected, ignored)')
        ->expectsOutput('Invalid theme ID [Bad_Theme]; use lowercase letters, digits and single hyphens, starting with a letter.')
        ->assertSuccessful();

    setSetting('theme', 'Bad_Theme');

    $this->artisan('atom:themes:validate')
        ->expectsOutput('aurora: valid')
        ->expectsOutput('Bad_Theme: rejected')
        ->assertFailed();

    $this->artisan('atom:themes:validate', ['theme' => 'aurora'])
        ->expectsOutput('aurora: valid')
        ->doesntExpectOutput('Bad_Theme: rejected')
        ->assertSuccessful();

    $this->artisan('atom:themes:validate', ['theme' => 'Bad_Theme'])
        ->expectsOutput('Bad_Theme: rejected')
        ->expectsOutput('Invalid theme ID [Bad_Theme]; use lowercase letters, digits and single hyphens, starting with a letter.')
        ->doesntExpectOutput('aurora: valid')
        ->assertFailed();
});

test('production command rejects a development-only theme', function () {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    writeThemeFixture($this->themeRoot, views: ThemeValidator::REQUIRED_VIEWS);
    File::put(storage_path('vite/aurora.hot'), 'http://localhost:5173');

    $this->artisan('atom:themes:validate', ['theme' => 'aurora', '--production' => true])
        ->expectsOutput('aurora: 2 problem(s)')
        ->expectsOutput('Remove the development hot file ' . str_replace('\\', '/', storage_path('vite/aurora.hot')) . ' before deploying.')
        ->expectsOutput('Missing build manifest ' . str_replace('\\', '/', public_path('build/themes/aurora/manifest.json')) . '.')
        ->assertFailed();
});

test('validation command rejects unknown theme IDs without fallback', function () {
    $this->artisan('atom:themes:validate', ['theme' => 'unknown'])
        ->expectsOutput('Theme [unknown] is not installed.')
        ->assertFailed();
});

test('validation reports a theme\'s own missing view when a sibling provides it', function () {
    useThemeAssetFixturePaths($this->themeTemporaryPath);
    $missingView = 'banned';
    $fixtureViews = array_values(array_diff(ThemeValidator::REQUIRED_VIEWS, [$missingView]));
    $fixture = writeThemeFixture($this->themeRoot, 'aurora', views: $fixtureViews);
    writeThemeFixture($this->themeRoot, 'other', views: [$missingView]);
    app()->forgetInstance(ThemeValidator::class);
    $missingPath = str_replace('\\', '/', realpath($fixture) . '/views/banned.blade.php');

    $this->artisan('atom:themes:validate', ['theme' => 'aurora'])
        ->expectsOutput('Missing view [banned]: ' . $missingPath)
        ->assertFailed();

    config(['theme.base_path' => resource_path('themes')]);
    $validator = freshThemeValidator();

    foreach (['atom', 'dusk'] as $id) {
        expect($validator->missingViews($validator->definition($id)))->toBe([]);
    }
});

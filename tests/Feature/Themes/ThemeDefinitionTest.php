<?php

use App\Data\ThemeDefinition;
use App\Exceptions\ThemePresentationException;
use App\Services\ThemeValidator;
use Illuminate\Support\Facades\File;

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
        expect($theme->entrypoints())->toBe(['css/app.css', 'js/app.js']);
        expect($theme->viewFile('auth.passwords.reset'))->toBe('resources/themes/' . $id . '/views/auth/passwords/reset.blade.php');
    }
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

    expect($validator->selectableIds())->toBe(['alpha', 'empty', 'zebra']);
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

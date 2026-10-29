<?php

use App\Services\ThemeValidator;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->buildThemeRoot = sys_get_temp_dir() . '/atom-build-theme-' . bin2hex(random_bytes(12));
    File::ensureDirectoryExists($this->buildThemeRoot . '/aurora/css');
    File::ensureDirectoryExists($this->buildThemeRoot . '/aurora/js');
    File::put($this->buildThemeRoot . '/aurora/css/app.css', '');
    File::put($this->buildThemeRoot . '/aurora/js/app.js', '');
    File::ensureDirectoryExists($this->buildThemeRoot . '/Bad_Theme');
    config(['theme.base_path' => $this->buildThemeRoot]);
    app()->forgetInstance(ThemeValidator::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->buildThemeRoot);
});

test('validated build targets run only their named npm script', function (string $target): void {
    Process::fake();
    File::partialMock()->shouldReceive('json')->once()->with(base_path('package.json'))
        ->andReturn(['scripts' => ["build:{$target}" => 'vite build']]);

    $this->artisan('build:theme', ['theme' => $target])
        ->expectsOutput("Building {$target} theme...")
        ->expectsOutput("Theme {$target} built successfully!")
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'run', "build:{$target}"]
        && $process->path === base_path() && $process->timeout === 300);
    Process::assertRanTimes(fn (): bool => true, 1);
})->with(['aurora', 'core', 'housekeeping']);

test('unknown build targets list validated targets without running npm', function (): void {
    Process::fake();

    $this->artisan('build:theme', ['theme' => 'unknown'])
        ->expectsOutput('Unknown theme. Available: aurora, core, housekeeping')
        ->assertFailed();

    Process::assertNothingRan();
});

test('rejected build targets explain their validator problems without running npm', function (): void {
    Process::fake();

    $this->artisan('build:theme', ['theme' => 'Bad_Theme'])
        ->expectsOutput('Theme Bad_Theme is rejected.')
        ->expectsOutput('Invalid theme ID [Bad_Theme]; use lowercase letters, digits and single hyphens, starting with a letter.')
        ->assertFailed();

    Process::assertNothingRan();
});

test('a build target without an npm script fails clearly', function (): void {
    Process::fake();
    File::partialMock()->shouldReceive('json')->once()->with(base_path('package.json'))->andReturn(['scripts' => []]);

    $this->artisan('build:theme', ['theme' => 'aurora'])
        ->expectsOutput('Missing npm script [build:aurora] in package.json. Add it before building this target.')
        ->assertFailed();

    Process::assertNothingRan();
});

test('an unreadable package manifest fails without running npm', function (): void {
    Process::fake();
    File::partialMock()->shouldReceive('json')->once()->with(base_path('package.json'))->andThrow(new JsonException('Syntax error'));

    $this->artisan('build:theme', ['theme' => 'aurora'])
        ->expectsOutput('Cannot read package.json: Syntax error')
        ->assertFailed();

    Process::assertNothingRan();
});

test('a failed npm build returns failure', function (): void {
    Process::fake(['*' => Process::result(exitCode: 1)]);
    File::partialMock()->shouldReceive('json')->once()->with(base_path('package.json'))
        ->andReturn(['scripts' => ['build:aurora' => 'vite build']]);

    $this->artisan('build:theme', ['theme' => 'aurora'])
        ->expectsOutput('Failed to build theme aurora')
        ->assertFailed();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'run', 'build:aurora']);
});

test('building requires source entrypoints even though production rendering does not', function (): void {
    File::delete($this->buildThemeRoot . '/aurora/css/app.css');
    Process::fake();

    $this->artisan('build:theme', ['theme' => 'aurora'])
        ->expectsOutput('Missing entrypoint file [css/app.css].')->assertFailed();

    Process::assertNothingRan();
});

<?php

use App\Console\Commands\AtomInstallCommand;
use App\Emulator\EmulatorManager;
use App\Models\Miscellaneous\WebsiteSetting;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    $this->installThemeOriginalPublicPath = public_path();
    $this->installThemeOriginalStoragePath = storage_path();
    $this->installThemeTemporaryPath = sys_get_temp_dir() . '/atom-install-theme-' . bin2hex(random_bytes(12));
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath($this->installThemeTemporaryPath);
    File::ensureDirectoryExists(storage_path('vite'));
});

afterEach(function (): void {
    app()->usePublicPath($this->installThemeOriginalPublicPath);
    app()->useStoragePath($this->installThemeOriginalStoragePath);
    File::deleteDirectory($this->installThemeTemporaryPath);
});

/**
 * Runs only the theme step of atom:install, so the test touches neither .env
 * nor the full installer. Builds are skipped by default; build tests fake npm.
 *
 * @param  array<string, mixed>  $options
 */
function runInstallerThemeStep(array $options = []): string
{
    $command = new class extends AtomInstallCommand
    {
        public function handle(EmulatorManager $emulators): int
        {
            (new ReflectionMethod(AtomInstallCommand::class, 'setUpTheme'))->invoke($this);

            return self::SUCCESS;
        }
    };
    $command->setLaravel(app());

    $input = new ArrayInput(['--skip-build' => true, ...$options]);
    $input->setInteractive(false);
    $output = new BufferedOutput;

    expect($command->run($input, $output))->toBe(0);

    return $output->fetch();
}

test('the installer writes the chosen theme through the validated path', function () {
    installHotel();
    setSetting('theme', 'dusk');

    expect(setting('theme'))->toBe('dusk');

    $output = runInstallerThemeStep(['--theme' => 'atom']);

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('atom')
        ->and(setting('theme'))->toBe('atom')
        ->and($output)->toContain("Active theme set to 'atom'");
});

test('the installer warns about an unknown theme and keeps the current one', function () {
    installHotel();
    setSetting('theme', 'dusk');

    $output = runInstallerThemeStep(['--theme' => 'missing']);

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('dusk')
        ->and($output)->toContain("Unknown theme 'missing'", 'atom, dusk');
});

test('the installer replaces a stored theme that is not installed with the configured default', function () {
    installHotel();
    setSetting('theme', 'missing');

    runInstallerThemeStep();

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe(config('theme.default'));
});

test('the installer builds the selected theme and shared assets once in order', function (string $theme): void {
    installHotel();
    setSetting('theme', $theme);
    Process::fake();

    runInstallerThemeStep(['--theme' => $theme, '--skip-build' => false]);

    Process::assertRanInOrder([
        ['npm', 'run', "build:{$theme}"],
        ['npm', 'run', 'build:core'],
        ['npm', 'run', 'build:housekeeping'],
    ]);
})->with(['atom', 'dusk']);

test('a failed target warns with its npm command and still attempts later builds', function (string $theme, string $failedTarget): void {
    installHotel();
    setSetting('theme', $theme);
    Process::fake(function (PendingProcess $process) use ($failedTarget) {
        return Process::result(
            errorOutput: "{$failedTarget} failed",
            exitCode: $process->command === ['npm', 'run', "build:{$failedTarget}"] ? 1 : 0,
        );
    });

    $output = runInstallerThemeStep(['--theme' => $theme, '--skip-build' => false]);

    expect($output)->toContain("The {$failedTarget} asset build failed", "npm run build:{$failedTarget}");
    Process::assertRanInOrder([
        ['npm', 'run', "build:{$theme}"],
        ['npm', 'run', 'build:core'],
        ['npm', 'run', 'build:housekeeping'],
    ]);
})->with([
    'selected theme' => ['dusk', 'dusk'],
    'Core' => ['dusk', 'core'],
    'housekeeping' => ['dusk', 'housekeeping'],
]);

test('the installer skip-build option reports every target without starting npm', function (): void {
    installHotel();
    setSetting('theme', 'dusk');
    Process::fake();

    $output = runInstallerThemeStep(['--theme' => 'dusk']);

    expect($output)->toContain('npm run build:dusk', 'npm run build:core', 'npm run build:housekeeping');
    Process::assertNothingRan();
});

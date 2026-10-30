<?php

use App\Console\Commands\AtomInstallCommand;
use App\Emulator\EmulatorManager;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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

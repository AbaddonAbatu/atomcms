<?php

namespace App\Console\Commands;

use App\Services\ThemeValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

class BuildTheme extends Command
{
    protected $signature = 'build:theme {theme? : Theme ID, core or housekeeping}';

    protected $description = 'Build a selected theme assets';

    public function handle(ThemeValidator $validator): int
    {
        $themes = collect(array_keys($validator->definitions()))->push('core', 'housekeeping')->sort()->values();

        $selection = $this->argument('theme') ?? $this->choice(
            'Which theme would you like to build?',
            $themes->toArray(),
            0,
        );

        if (! is_string($selection)) {
            $this->error('The selected theme is invalid.');

            return Command::FAILURE;
        }

        if (isset($validator->rejected()[$selection]) && ! in_array($selection, ['core', 'housekeeping'], true)) {
            $this->error("Theme {$selection} is rejected.");

            foreach ($validator->rejected()[$selection] as $problem) {
                $this->line($problem);
            }

            return Command::FAILURE;
        }

        if (! $themes->contains($selection)) {
            $this->error('Unknown theme. Available: ' . $themes->implode(', '));

            return Command::FAILURE;
        }

        if (! in_array($selection, ['core', 'housekeeping'], true)) {
            $problems = $validator->buildProblems($validator->definition($selection));
            if ($problems !== []) {
                foreach ($problems as $problem) {
                    $this->error($problem);
                }

                return Command::FAILURE;
            }
        }

        try {
            $package = File::json(base_path('package.json'));
        } catch (Throwable $exception) {
            $this->error('Cannot read package.json: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $script = $package['scripts']["build:{$selection}"] ?? null;

        if (! is_string($script) || trim($script) === '') {
            $this->error("Missing npm script [build:{$selection}] in package.json. Add it before building this target.");

            return Command::FAILURE;
        }

        $this->info("Building {$selection} theme...");

        return $this->runBuildCommand($selection);
    }

    private function runBuildCommand(string $theme): int
    {
        $process = Process::path(base_path())->timeout(300)->run(
            ['npm', 'run', "build:{$theme}"],
            fn (string $type, string $buffer) => $this->output->write($buffer),
        );

        if ($process->successful()) {
            $this->info("Theme {$theme} built successfully!");

            return Command::SUCCESS;
        }

        $this->error("Failed to build theme {$theme}");

        return Command::FAILURE;
    }
}

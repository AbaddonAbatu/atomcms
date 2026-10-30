<?php

namespace App\Console\Commands;

use App\Exceptions\ThemePresentationException;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Console\Command;

class ValidateThemes extends Command
{
    protected $signature = 'atom:themes:validate {theme? : Theme ID; every installed theme when omitted}
        {--production : Require built assets and ignore development hot files}';

    protected $description = 'Check installed public themes for required views and usable assets';

    public function handle(ThemeValidator $validator, ThemeService $themes): int
    {
        $definitions = $validator->definitions();
        $rejected = $validator->rejected();
        $id = $this->argument('theme');

        if (is_string($id)) {
            if (isset($rejected[$id])) {
                $definitions = [];
                $rejected = [$id => $rejected[$id]];
            } else {
                try {
                    $definitions = [$id => $validator->definition($id)];
                } catch (ThemePresentationException $exception) {
                    $this->error($exception->getMessage());

                    return self::FAILURE;
                }

                $rejected = [];
            }
        }

        $failed = false;

        foreach ($definitions as $theme) {
            $problems = $validator->activationProblems($theme->id, (bool) $this->option('production'));

            if ($problems === []) {
                $this->info("{$theme->id}: valid");

                continue;
            }

            $failed = true;
            $this->error("{$theme->id}: " . count($problems) . ' problem(s)');

            foreach ($problems as $problem) {
                $this->line($problem);
            }
        }

        // A rejected folder can never be selected, so when checking every theme
        // only the hotel's selected one fails; leftovers such as backups warn.
        $selected = is_string($id) ? $id : ($rejected === [] ? null : $themes->selectedId());

        foreach ($rejected as $candidate => $problems) {
            if ($candidate !== $selected) {
                $this->warn("{$candidate}: rejected (not selected, ignored)");

                foreach ($problems as $problem) {
                    $this->warn($problem);
                }

                continue;
            }

            $failed = true;
            $this->error("{$candidate}: rejected");

            foreach ($problems as $problem) {
                $this->line($problem);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

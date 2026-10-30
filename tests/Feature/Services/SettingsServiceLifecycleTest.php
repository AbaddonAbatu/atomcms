<?php

use App\Services\InstallationSetup;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

test('a validated theme write reaches another application instance on its next lifecycle', function () {
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    installHotel();
    setSetting('theme', 'atom');

    // A second persistent application (a queue worker, say). Booting it points
    // the process-wide statics at it, so hand them back to this test's app and
    // let both share one connection, which holds the test transaction.
    $second = $this->createApplication();

    try {
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);
        Facade::clearResolvedInstances();
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);

        $pdo = $this->app['db']->connection()->getPdo();
        $second['db']->connection()->setPdo($pdo)->setReadPdo($pdo);

        expect($second->make(SettingsService::class)->getOrDefault('theme'))->toBe('atom');

        app(InstallationSetup::class)->configure(['theme' => 'dusk']);

        // The writer sees the change at once; the other instance keeps the
        // value it memoized until its own lifecycle ends.
        expect(setting('theme'))->toBe('dusk')
            ->and($second->make(SettingsService::class)->getOrDefault('theme'))->toBe('atom');

        // What the queue worker does before each job.
        $second->forgetScopedInstances();

        expect($second->make(SettingsService::class)->getOrDefault('theme'))->toBe('dusk')
            ->and($second->make(ThemeService::class)->selectedId())->toBe('dusk');
    } finally {
        $second->flush();
    }
});

<?php

namespace App\Providers;

use App\Contracts\PaypalGateway;
use App\Database\CollisionAwareMariaDbConnection;
use App\Database\CollisionAwareMySqlConnection;
use App\Exceptions\PaypalPaymentException;
use App\Http\Middleware\BannedMiddleware;
use App\Http\Middleware\ForceStaffTwoFactorMiddleware;
use App\Http\Middleware\MaintenanceMiddleware;
use App\Models\WebsiteDrawBadge;
use App\Observers\WebsiteDrawBadgeObserver;
use App\Services\HousekeepingPermissionsService;
use App\Services\InstallationService;
use App\Services\Payments\SrmklivePaypalGateway;
use App\Services\PermissionsService;
use App\Services\SettingsService;
use App\Services\ViteService;
use Filament\Tables\Table;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Collision-aware connections rename tables and columns left behind
        // by the emulator database out of the way during migrations, or turn
        // the raw "already exists" SQL errors into actionable ones.
        Connection::resolverFor('mysql', fn ($connection, $database, $prefix, $config) => new CollisionAwareMySqlConnection($connection, $database, $prefix, $config));
        Connection::resolverFor('mariadb', fn ($connection, $database, $prefix, $config) => new CollisionAwareMariaDbConnection($connection, $database, $prefix, $config));

        // Scoped per request so a webhook that verifies and captures reuses a
        // single gateway (and therefore a single PayPal client and token).
        $this->app->scoped(PaypalGateway::class, SrmklivePaypalGateway::class);

        $this->app->bind(
            Vite::class,
            fn (): ViteService => (new ViteService)
                ->useBuildDirectory('build-core')
                ->useHotFile(storage_path('vite/core.hot')),
        );

        $this->app->singleton(InstallationService::class);

        // Scoped so each request or queued job starts with a fresh in-memory
        // memo; the persistent 'website_settings' cache is still shared.
        $this->app->scoped(SettingsService::class);

        $this->app->singleton(PermissionsService::class);

        $this->app->singleton(HousekeepingPermissionsService::class);

        // Authentication happens lazily on the gateway's first API call, so
        // resolving the client never performs OAuth HTTP inside the container.
        // Scoped so a webhook that verifies and captures reuses one token.
        $this->app->scoped(PayPalClient::class, function (): PayPalClient {
            try {
                $client = new PayPalClient(config('habbo.paypal'));
            } catch (\Throwable $exception) {
                throw PaypalPaymentException::gatewayFailure($exception);
            }
            $client->setClient(new HttpClient([
                'connect_timeout' => 3,
                'timeout' => 10,
                'verify' => true,
            ]));

            return $client;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([
            BannedMiddleware::class,
            MaintenanceMiddleware::class,
            ForceStaffTwoFactorMiddleware::class,
        ]);

        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols());

        Model::preventLazyLoading(! $this->app->isProduction());

        if (config('habbo.site.force_https')) {
            URL::forceScheme('https');
        }

        Table::configureUsing(function (Table $table) {
            $table->paginated([10, 25, 50]);
        });

        WebsiteDrawBadge::observe(WebsiteDrawBadgeObserver::class);
    }
}

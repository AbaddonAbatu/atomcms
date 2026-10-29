<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Services\Auth\AuthPageService;
use App\Services\Auth\PasswordVerifier;
use App\Support\FrontendUrls;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        $this->configureRateLimiting();
        $this->configureViews();
        $this->authenticate();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $username = $request->input('username');
            $identity = is_string($username)
                ? 'username:' . Str::transliterate(Str::lower($username))
                : 'invalid-username';

            return Limit::perMinute(5)->by($identity . '|' . $request->ip());
        });
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
        RateLimiter::for('two-factor-settings', fn (Request $request) => Limit::perMinute(6)->by(
            ($request->user()?->getAuthIdentifier() ?? 'guest') . '|' . $request->ip(),
        ));
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn () => theme_view('auth.login', app(AuthPageService::class)->loginData()));

        Fortify::registerView(function (Request $request) {
            if (setting('disable_registration') === '1') {
                return redirect(app(FrontendUrls::class)->route('welcome'))->withErrors(['register' => __('Registration is currently disabled.')]);
            }

            return theme_view('auth.register', app(AuthPageService::class)->registrationData($request->route('referral_code')));
        });

        Fortify::confirmPasswordView(fn () => theme_view('auth.passwords.confirm'));
        Fortify::twoFactorChallengeView(fn () => theme_view('auth.two-factor-challenge'));
    }

    private function authenticate(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $username = $request->input('username');
            $password = $request->input('password');

            if (! is_string($username) || ! is_string($password)) {
                return null;
            }

            $user = User::query()->where('username', $username)->first();

            return $user !== null && app(PasswordVerifier::class)->verify($user, $password) ? $user : null;
        });

        Fortify::authenticateThrough(function () {
            return array_filter([
                config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,

                Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
                AttemptToAuthenticate::class,
                PrepareAuthenticatedSession::class,
            ]);
        });
    }
}

<?php

use App\Models\Community\Staff\WebsiteOpenPosition;
use App\Models\Community\Teams\WebsiteTeam;
use App\Models\User;
use App\Services\Auth\AuthPageService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

beforeEach(function (): void {
    config([
        'habbo.site.recaptcha_site_key' => 'google-captcha-site-key',
        'services.turnstile.key' => 'cloudflare-captcha-site-key',
        'turnstile.turnstile_secret_key' => 'cloudflare-captcha-secret',
    ]);
});

function captchaFormHtml(TestCase $test, string $theme, string $page): string
{
    if ($page === 'login-view') {
        return theme_view('auth.login', app(AuthPageService::class)->loginData())->render();
    }

    if ($page === 'login-component') {
        return Blade::render("<x-app-layout><x-theme-{$theme}::auth.login-form /></x-app-layout>");
    }

    if ($page === 'maintenance.show') {
        setSetting('maintenance_enabled', '1');
    }

    if (in_array($page, ['welcome', 'register', 'maintenance.show', 'forgot.password.get'], true)) {
        return $test->get(route($page))->assertOk()->getContent();
    }

    $user = User::factory()->create();

    if ($page === 'two-factor.login') {
        return $test->withSession(['login.id' => $user->id])->get(route($page))->assertOk()->getContent();
    }

    $test->actingAs($user);

    if ($page === 'settings.two-factor') {
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['captcha-recovery-code'])),
        ])->save();
    }

    $parameters = [];

    if ($page === 'staff-applications.show') {
        $parameters = WebsiteOpenPosition::create([
            'position_kind' => 'rank',
            'permission_id' => DB::table('permissions')->min('id'),
            'description' => 'Moderate the hotel.',
            'apply_from' => now()->subDay(),
            'apply_to' => now()->addDay(),
        ]);
    }

    if ($page === 'team-applications.show') {
        $team = WebsiteTeam::create([
            'rank_name' => 'Events Team',
            'badge' => '',
            'job_description' => 'Host events.',
            'staff_color' => '#ffffff',
            'staff_background' => '',
        ]);
        $parameters = WebsiteOpenPosition::create([
            'position_kind' => 'team',
            'team_id' => $team->id,
            'description' => 'Join the events team.',
            'apply_from' => now()->subDay(),
            'apply_to' => now()->addDay(),
        ]);
    }

    return $test->get(route($page, $parameters))->assertOk()->getContent();
}

test('forms render each required captcha widget and asset once', function (string $theme, string $page, string $google, string $cloudflare, ?string $secret, int $googleCount, int $cloudflareCount): void {
    installHotel();
    setSetting('theme', $theme);
    setSetting('force_staff_2fa', '0');
    setSetting('google_recaptcha_enabled', $google);
    setSetting('cloudflare_turnstile_enabled', $cloudflare);
    config(['turnstile.turnstile_secret_key' => $secret]);

    $html = captchaFormHtml($this, $theme, $page);
    $formCount = $theme === 'atom' && in_array($page, ['login-view', 'login-component', 'register', 'two-factor.login'], true) ? 2 : 1;

    expect(substr_count($html, 'data-sitekey="google-captcha-site-key"'))->toBe($googleCount * $formCount);
    expect(substr_count($html, 'https://www.google.com/recaptcha/api.js'))->toBe($googleCount);
    expect(substr_count($html, 'data-sitekey="cloudflare-captcha-site-key"'))->toBe($cloudflareCount * $formCount);
    expect(substr_count($html, 'https://challenges.cloudflare.com/turnstile/v0/api.js'))->toBe($cloudflareCount);
    expect($html)->not->toContain('cloudflare-captcha-secret');
})->with(['atom', 'dusk'])->with([
    'welcome',
    'login-view',
    'login-component',
    'register',
    'password.confirm',
    'two-factor.login',
    'settings.account.show',
    'settings.password.show',
    'settings.two-factor',
    'staff-applications.show',
    'team-applications.show',
    'values.index',
    'maintenance.show',
])->with([
    'google only' => ['1', '0', 'cloudflare-captcha-secret', 1, 0],
    'turnstile only' => ['0', '1', 'cloudflare-captcha-secret', 0, 1],
    'both providers' => ['1', '1', 'cloudflare-captcha-secret', 1, 1],
    'empty settings' => ['', '', 'cloudflare-captcha-secret', 0, 0],
    'zero settings' => ['0', '0', 'cloudflare-captcha-secret', 0, 0],
    'nonnumeric settings' => ['false', 'false', 'cloudflare-captcha-secret', 0, 0],
    'nonzero integer settings' => ['2', '2', 'cloudflare-captcha-secret', 1, 1],
    'turnstile without a secret' => ['0', '1', null, 0, 0],
    'turnstile with a blank secret' => ['0', '1', '   ', 0, 0],
]);

test('forgot password and ticket forms have no captcha widgets', function (string $theme, string $page): void {
    installHotel();
    setSetting('theme', $theme);
    setSetting('google_recaptcha_enabled', '1');
    setSetting('cloudflare_turnstile_enabled', '1');

    $html = captchaFormHtml($this, $theme, $page);

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $action = route($page === 'forgot.password.get' ? 'forgot.password.post' : 'help-center.ticket.store');
    $form = $xpath->query('//form[@action="' . $action . '"]')->item(0);

    expect($form)->not->toBeNull();
    expect($xpath->query('.//*[@data-sitekey]', $form)->length)->toBe(0);
    expect(substr_count($html, 'https://www.google.com/recaptcha/api.js'))->toBe(1);
    expect(substr_count($html, 'https://challenges.cloudflare.com/turnstile/v0/api.js'))->toBe(1);
})->with(['atom', 'dusk'])->with(['forgot.password.get', 'help-center.ticket.create']);

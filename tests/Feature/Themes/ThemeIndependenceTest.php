<?php

use App\Exceptions\ThemePresentationException;
use App\Mail\ResetPasswordMail;
use App\Models\Articles\WebsiteArticle;
use App\Models\Community\RareValue\WebsiteRareValue;
use App\Models\Community\RareValue\WebsiteRareValueCategory;
use App\Models\Community\Staff\WebsiteOpenPosition;
use App\Models\Community\Teams\WebsiteTeam;
use App\Models\Help\WebsiteHelpCenterCategory;
use App\Models\Help\WebsiteRule;
use App\Models\Help\WebsiteRuleCategory;
use App\Models\Home\HomeItem;
use App\Models\Miscellaneous\WebsiteMaintenanceTask;
use App\Models\Miscellaneous\WebsiteSetting;
use App\Models\PasswordResetToken;
use App\Models\Shop\WebsiteShopCategory;
use App\Models\User;
use App\Models\User\Ban;
use App\Providers\ThemeServiceProvider;
use App\Services\Auth\AuthPageService;
use App\Services\SettingsService;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    installHotel();
    setSetting('force_staff_2fa', '0');
    setSetting('maintenance_enabled', '0');
    setSetting('disable_registration', '0');
    setSetting('google_recaptcha_enabled', '0');
    setSetting('cloudflare_turnstile_enabled', '0');
    setSetting('requires_beta_code', '0');

    $this->auroraOriginalThemeBasePath = config('theme.base_path');
    $this->auroraOriginalPublicPath = public_path();
    $this->auroraOriginalStoragePath = storage_path();
    $this->auroraTemporaryPath = base_path('storage/framework/testing/aurora-themes-' . bin2hex(random_bytes(6)));
    $this->auroraThemePath = $this->auroraTemporaryPath . '/themes/aurora';
    $this->auroraEmptyPath = $this->auroraTemporaryPath . '/empty';
    $this->auroraPublicPath = $this->auroraTemporaryPath . '/public';
    $this->auroraStoragePath = $this->auroraTemporaryPath . '/storage';

    File::copyDirectory(base_path('tests/Fixtures/themes/aurora'), $this->auroraThemePath);
    File::ensureDirectoryExists($this->auroraEmptyPath);
    File::ensureDirectoryExists($this->auroraPublicPath);
    File::ensureDirectoryExists($this->auroraStoragePath . '/vite');

    config(['theme.base_path' => dirname($this->auroraThemePath)]);
    app()->usePublicPath($this->auroraPublicPath);
    app()->useStoragePath($this->auroraStoragePath);
    auroraRegisterThemeRuntime($this->auroraEmptyPath, $this->auroraEmptyPath);

    auroraStoreThemeSetting('aurora');
});

afterEach(function (): void {
    try {
        app()->usePublicPath($this->auroraOriginalPublicPath);
        app()->useStoragePath($this->auroraOriginalStoragePath);
        config(['theme.base_path' => $this->auroraOriginalThemeBasePath]);

        app()->forgetInstance(ThemeValidator::class);
        app()->forgetInstance(ThemeService::class);
        app()->forgetScopedInstances();
        app()->register(ThemeServiceProvider::class, true);
        View::getFinder()->setPaths([resource_path('views')]);
        View::getFinder()->replaceNamespace('theme-atom', [resource_path('themes/atom/views')]);
        View::getFinder()->replaceNamespace('theme-dusk', [resource_path('themes/dusk/views')]);
        View::getFinder()->flush();
    } finally {
        File::deleteDirectory($this->auroraTemporaryPath);
    }
});

afterAll(function (): void {
    $testingRoot = base_path('storage/framework/testing');

    foreach (File::directories($testingRoot) as $directory) {
        if (str_starts_with(basename($directory), 'aurora-themes-')) {
            File::deleteDirectory($directory);
        }
    }
});

/**
 * Reboots theme discovery while leaving Core's ordinary finder path intact.
 */
function auroraRegisterThemeRuntime(string $atomPath, string $duskPath): void
{
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetInstance(ThemeService::class);
    app()->forgetScopedInstances();
    app()->register(ThemeServiceProvider::class, true);

    View::getFinder()->setPaths([resource_path('views')]);
    View::getFinder()->replaceNamespace('theme-atom', [$atomPath]);
    View::getFinder()->replaceNamespace('theme-dusk', [$duskPath]);
    View::getFinder()->flush();
}

function auroraAssertResolvedPaths(string $themeRoot): int
{
    $roots = array_filter([
        realpath($themeRoot),
        realpath(resource_path('views/core')),
        realpath(resource_path('views/vendor/turnstile')),
        realpath(base_path('vendor')),
    ]);
    $violations = [];
    $resolvedCount = 0;

    foreach (View::getFinder()->getViews() as $name => $path) {
        $resolved = realpath($path);
        $resolvedCount++;
        $allowed = false;

        foreach ($roots as $root) {
            $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $allowed = PHP_OS_FAMILY === 'Windows'
                ? strncasecmp($resolved ?: '', $prefix, strlen($prefix)) === 0
                : str_starts_with($resolved ?: '', $prefix);

            if ($allowed) {
                break;
            }
        }

        if (! $allowed) {
            $violations[] = $name . ': ' . ($resolved ?: $path);
        }
    }

    expect($violations)->toBe([]);

    return $resolvedCount;
}

/** @return array{action: string, method: string, fields: array<string, string>} */
function auroraExtractForm(string $html): array
{
    preg_match('/<form\b([^>]*)>/i', $html, $formMatch);
    $attributes = $formMatch[1] ?? '';
    preg_match('/\baction=["\']([^"\']*)["\']/i', $attributes, $actionMatch);
    preg_match('/\bmethod=["\']([^"\']*)["\']/i', $attributes, $methodMatch);
    preg_match_all('/<input\b([^>]*)>/i', $html, $inputMatches);
    $fields = [];

    foreach ($inputMatches[1] as $input) {
        preg_match('/\bname=["\']([^"\']+)["\']/i', $input, $nameMatch);

        if (! isset($nameMatch[1])) {
            continue;
        }

        preg_match('/\bvalue=["\']([^"\']*)["\']/i', $input, $valueMatch);
        $fields[html_entity_decode($nameMatch[1], ENT_QUOTES | ENT_HTML5)] = html_entity_decode($valueMatch[1] ?? '', ENT_QUOTES | ENT_HTML5);
    }

    return [
        'action' => html_entity_decode($actionMatch[1] ?? '', ENT_QUOTES | ENT_HTML5),
        'method' => strtoupper($methodMatch[1] ?? 'GET'),
        'fields' => $fields,
    ];
}

function auroraArticle(): WebsiteArticle
{
    $author = User::factory()->create(['username' => 'AuroraAuthor']);

    return WebsiteArticle::create([
        'user_id' => $author->id,
        'title' => 'Aurora article',
        'short_story' => 'Aurora short story',
        'full_story' => '<p>Aurora full story.</p>',
        'image' => 'articles/aurora.png',
        'can_comment' => true,
    ]);
}

function auroraStoreThemeSetting(string $value): void
{
    WebsiteSetting::query()->updateOrCreate(['key' => 'theme'], ['value' => $value, 'comment' => 'Aurora fixture setting']);
    app(SettingsService::class)->refresh();
}

/** @return array<string, array{prepare: Closure, request: Closure, expected: string, standalone: bool}> */
function auroraPageCases(): array
{
    return [
        'guest welcome with articles' => [
            'prepare' => function (TestCase $test): void {
                $article = auroraArticle();
                $test->auroraArticleSlug = $article->slug;
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('welcome')),
            'expected' => 'Aurora article',
            'standalone' => false,
        ],
        'registration' => [
            'prepare' => function (TestCase $test): void {},
            'request' => fn (TestCase $test): TestResponse => $test->get(route('register')),
            'expected' => 'Create an account',
            'standalone' => false,
        ],
        'referral registration' => [
            'prepare' => function (TestCase $test): void {
                $referrer = User::factory()->create(['referral_code' => 'AURORA-REF']);
                $test->auroraReferralCode = $referrer->referral_code;
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('register.referral', $test->auroraReferralCode)),
            'expected' => 'AURORA-REF',
            'standalone' => false,
        ],
        'forgot password' => [
            'prepare' => function (TestCase $test): void {},
            'request' => fn (TestCase $test): TestResponse => $test->get(route('forgot.password.get')),
            'expected' => 'Forgot your password?',
            'standalone' => false,
        ],
        'reset password' => [
            'prepare' => function (TestCase $test): void {
                $user = User::factory()->create();
                PasswordResetToken::create(['email' => $user->mail, 'token' => PasswordResetToken::hashToken('aurora-reset')]);
                $test->auroraResetToken = 'aurora-reset';
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('reset.password.get', $test->auroraResetToken)),
            'expected' => 'Choose a new password',
            'standalone' => false,
        ],
        'password confirmation' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('password.confirm')),
            'expected' => 'Confirm password',
            'standalone' => false,
        ],
        'two-factor challenge' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
                auth()->forgetGuards();
                $test->withSession(['login.id' => $test->auroraUser->id]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('two-factor.login')),
            'expected' => 'Two-factor authentication',
            'standalone' => false,
        ],
        'member dashboard' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create(['username' => 'AuroraMember']);
                WebsiteArticle::create(['user_id' => $test->auroraUser->id, 'title' => 'Dashboard article', 'short_story' => 'Dashboard', 'full_story' => '<p>Dashboard</p>', 'image' => 'dashboard.png']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('me.show')),
            'expected' => 'Dashboard article',
            'standalone' => false,
        ],
        'account settings' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create(['mail' => 'aurora@example.com']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('settings.account.show')),
            'expected' => 'aurora@example.com',
            'standalone' => false,
        ],
        'password settings' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('settings.password.show')),
            'expected' => 'New password',
            'standalone' => false,
        ],
        'session log settings' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
                DB::table('sessions')->insert(['id' => 'aurora-session', 'user_id' => $test->auroraUser->id, 'ip_address' => '203.0.113.9', 'user_agent' => 'Aurora test', 'payload' => '', 'last_activity' => time()]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('settings.session-logs')),
            'expected' => '203.0.113.9',
            'standalone' => false,
        ],
        'two-factor settings' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
                $test->auroraUser->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['aurora-recovery']))])->save();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser->refresh())->get(route('settings.two-factor')),
            'expected' => 'aurora-recovery',
            'standalone' => false,
        ],
        'draw badge' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('draw-badge')),
            'expected' => 'Cost: 150 credits',
            'standalone' => false,
        ],
        'home visitor' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create(['username' => 'AuroraHome']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('home.show', $test->auroraUser->username)),
            'expected' => 'AuroraHome',
            'standalone' => false,
        ],
        'home owner' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create(['username' => 'AuroraOwner']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('home.show', $test->auroraUser->username)),
            'expected' => 'Edit home',
            'standalone' => false,
        ],
        'article index' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraArticle = auroraArticle();
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('article.index')),
            'expected' => 'Aurora article',
            'standalone' => false,
        ],
        'article page' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraArticle = auroraArticle();
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('article.show', $test->auroraArticle->slug)),
            'expected' => 'Aurora full story.',
            'standalone' => false,
        ],
        'staff' => [
            'prepare' => function (TestCase $test): void {
                User::factory()->create(['username' => 'AuroraStaff', 'rank' => 7]);
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('staff.index')),
            'expected' => 'AuroraStaff',
            'standalone' => false,
        ],
        'teams' => [
            'prepare' => function (TestCase $test): void {
                $team = WebsiteTeam::create(['rank_name' => 'Aurora Events', 'badge' => '', 'job_description' => 'Aurora events.', 'staff_color' => '#fff', 'staff_background' => '']);
                User::factory()->create(['username' => 'AuroraHost', 'team_id' => $team->id]);
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('teams.index')),
            'expected' => 'Aurora Events',
            'standalone' => false,
        ],
        'staff applications' => [
            'prepare' => function (TestCase $test): void {
                $position = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => DB::table('permissions')->min('id'), 'description' => 'Aurora staff application.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);
                $test->auroraPosition = $position;
                $test->auroraUser = User::factory()->create(['username' => 'AuroraApplicant']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('staff-applications.show', $test->auroraPosition)),
            'expected' => 'AuroraApplicant',
            'standalone' => false,
        ],
        'team applications' => [
            'prepare' => function (TestCase $test): void {
                $team = WebsiteTeam::create(['rank_name' => 'Aurora Team', 'badge' => '', 'job_description' => 'Aurora team.', 'staff_color' => '#fff', 'staff_background' => '']);
                $test->auroraPosition = WebsiteOpenPosition::create(['position_kind' => 'team', 'team_id' => $team->id, 'description' => 'Aurora team application.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('team-applications.show', $test->auroraPosition)),
            'expected' => 'Aurora Team',
            'standalone' => false,
        ],
        'leaderboard' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create(['username' => 'AuroraRich', 'credits' => 987654]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('leaderboard.index')),
            'expected' => 'AuroraRich',
            'standalone' => false,
        ],
        'shop' => [
            'prepare' => function (TestCase $test): void {
                $category = WebsiteShopCategory::create(['name' => 'Aurora packs', 'slug' => 'aurora-packs', 'is_active' => true]);
                makePackage(['website_shop_category_id' => $category->id]);
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('shop.index')),
            'expected' => 'Starter Bundle',
            'standalone' => false,
        ],
        'help center index' => [
            'prepare' => function (TestCase $test): void {
                WebsiteHelpCenterCategory::create(['name' => 'Aurora help', 'content' => 'Aurora support.']);
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('help-center.index')),
            'expected' => 'Aurora help',
            'standalone' => false,
        ],
        'help center rules' => [
            'prepare' => function (TestCase $test): void {
                $category = WebsiteRuleCategory::create(['name' => 'Aurora rules', 'description' => 'Aurora conduct.', 'badge' => 'AUR']);
                WebsiteRule::create(['category_id' => $category->id, 'paragraph' => '1.1', 'rule' => 'Be kind in Aurora.']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('help-center.rules.index')),
            'expected' => 'Aurora rules',
            'standalone' => false,
        ],
        'help ticket create' => [
            'prepare' => function (TestCase $test): void {
                WebsiteHelpCenterCategory::create(['name' => 'Aurora tickets', 'content' => 'Aurora ticket help.']);
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('help-center.ticket.create')),
            'expected' => 'Create ticket',
            'standalone' => false,
        ],
        'help ticket show' => [
            'prepare' => function (TestCase $test): void {
                $category = WebsiteHelpCenterCategory::create(['name' => 'Aurora tickets', 'content' => 'Aurora ticket help.']);
                $test->auroraUser = User::factory()->create();
                $test->auroraTicket = $test->auroraUser->tickets()->create(['category_id' => $category->id, 'title' => 'Aurora ticket', 'content' => 'Aurora ticket body.', 'open' => true]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('help-center.ticket.show', $test->auroraTicket)),
            'expected' => 'Aurora ticket body.',
            'standalone' => false,
        ],
        'help ticket edit' => [
            'prepare' => function (TestCase $test): void {
                $category = WebsiteHelpCenterCategory::create(['name' => 'Aurora tickets', 'content' => 'Aurora ticket help.']);
                $test->auroraUser = User::factory()->create();
                $test->auroraTicket = $test->auroraUser->tickets()->create(['category_id' => $category->id, 'title' => 'Aurora edit ticket', 'content' => 'Edit me.', 'open' => true]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('help-center.ticket.edit', $test->auroraTicket)),
            'expected' => 'Aurora edit ticket',
            'standalone' => false,
        ],
        'help ticket index for staff' => [
            'prepare' => function (TestCase $test): void {
                $category = WebsiteHelpCenterCategory::create(['name' => 'Aurora tickets', 'content' => 'Aurora ticket help.']);
                $owner = User::factory()->create(['username' => 'AuroraTicketOwner']);
                $owner->tickets()->create(['category_id' => $category->id, 'title' => 'Aurora staff ticket', 'content' => 'Staff view.', 'open' => true]);
                $test->auroraUser = User::factory()->create(['rank' => 7]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('help-center.ticket.index')),
            'expected' => 'Aurora staff ticket',
            'standalone' => false,
        ],
        'rare values index' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraRareCategory = WebsiteRareValueCategory::create(['name' => 'Aurora rares', 'badge' => 'AUR', 'priority' => 1]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs(User::factory()->create())->get(route('values.index')),
            'expected' => 'Aurora rares',
            'standalone' => false,
        ],
        'rare values category' => [
            'prepare' => function (TestCase $test): void {
                $category = WebsiteRareValueCategory::create(['name' => 'Aurora rares', 'badge' => 'AUR', 'priority' => 1]);
                $test->auroraRare = WebsiteRareValue::create(['category_id' => $category->id, 'name' => 'Aurora dragon', 'furniture_icon' => 'aurora.png']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs(User::factory()->create())->get(route('values.category', $test->auroraRare->category_id)),
            'expected' => 'Aurora dragon',
            'standalone' => false,
        ],
        'rare value page' => [
            'prepare' => function (TestCase $test): void {
                $category = WebsiteRareValueCategory::create(['name' => 'Aurora rares', 'badge' => 'AUR', 'priority' => 1]);
                $test->auroraRare = WebsiteRareValue::create(['category_id' => $category->id, 'name' => 'Aurora value', 'furniture_icon' => 'aurora.png']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs(User::factory()->create())->get(route('values.value', $test->auroraRare)),
            'expected' => 'Aurora value',
            'standalone' => false,
        ],
        'logo generator for staff' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create(['rank' => 7]);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('logo-generator.index')),
            'expected' => 'Logo generator',
            'standalone' => false,
        ],
        'maintenance notice' => [
            'prepare' => function (TestCase $test): void {
                setSetting('maintenance_enabled', '1');
                setSetting('maintenance_message', 'Aurora maintenance');
                WebsiteMaintenanceTask::create(['user_id' => User::factory()->create()->id, 'task' => 'Aurora task']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->get(route('maintenance.show')),
            'expected' => 'Aurora task',
            'standalone' => false,
        ],
        'banned notice' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
                Ban::create(['user_id' => $test->auroraUser->id, 'ip' => '', 'machine_id' => '', 'user_staff_id' => $test->auroraUser->id, 'timestamp' => time(), 'ban_expire' => time() + 3600, 'ban_reason' => 'Aurora ban', 'type' => 'account']);
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('banned.show')),
            'expected' => 'Aurora ban',
            'standalone' => false,
        ],
        'Nitro client' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('nitro-client')),
            'expected' => 'Nitro client',
            'standalone' => true,
        ],
        'Flash client' => [
            'prepare' => function (TestCase $test): void {
                $test->auroraUser = User::factory()->create();
            },
            'request' => fn (TestCase $test): TestResponse => $test->actingAs($test->auroraUser)->get(route('flash-client')),
            'expected' => 'Flash client',
            'standalone' => true,
        ],
    ];
}

/** @return array{snapshot: string, name: string} */
function auroraLivewireSnapshot(string $html, string $component): array
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encodedSnapshot) {
        $snapshot = html_entity_decode($encodedSnapshot, ENT_QUOTES | ENT_HTML5);
        $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

        if (($decoded['memo']['name'] ?? null) === $component) {
            return ['snapshot' => $snapshot, 'name' => $component];
        }
    }

    throw new RuntimeException("The Aurora page did not render the {$component} component.");
}

test('Aurora is the only discovered fixture and root public views are absent', function (): void {
    $definitions = app(ThemeValidator::class)->definitions();

    expect(array_keys($definitions))->toBe(['aurora']);

    $deletedRootViews = [
        'auth/passwords/confirm.blade.php', 'auth/passwords/forget.blade.php', 'auth/passwords/reset.blade.php', 'auth/two-factor-challenge.blade.php',
        'client/flash.blade.php', 'community/staff-applications.blade.php', 'community/staff-apply.blade.php', 'community/team-applications.blade.php', 'community/teams.blade.php',
        'components/client/client-button.blade.php', 'components/currency-box.blade.php', 'components/fancybox-assets.blade.php', 'components/filler-article-card.blade.php', 'components/footer-credits.blade.php',
        'components/form/danger-button.blade.php', 'components/form/label.blade.php', 'components/icons/cart.blade.php', 'components/icons/chevron-down.blade.php', 'components/icons/eye.blade.php',
        'components/icons/fullscreen.blade.php', 'components/icons/gift.blade.php', 'components/icons/home.blade.php', 'components/icons/moon.blade.php', 'components/icons/reload.blade.php',
        'components/icons/user.blade.php', 'components/messages/flash-messages.blade.php', 'components/modals/modal-wrapper.blade.php', 'components/rares/rare-card.blade.php', 'components/ui/modal.blade.php',
        'help-center/tickets/edit.blade.php', 'help-center/tickets/index.blade.php', 'help-center/tickets/show.blade.php', 'livewire/article-comments.blade.php', 'livewire/article-reactions.blade.php',
        'maintenance.blade.php', 'value.blade.php',
    ];

    foreach ($deletedRootViews as $view) {
        expect(File::exists(resource_path('views/' . $view)))->toBeFalse($view);
    }
});

test('Aurora owns every public page in the presentation matrix', function (Closure $prepare, Closure $request, string $expected, bool $standalone): void {
    $prepare($this);
    $response = $request($this)->assertOk()->assertSee($expected);

    if (! $standalone) {
        $response->assertSee('data-theme="aurora"', false);
    }

    expect(auroraAssertResolvedPaths($this->auroraThemePath))->toBeGreaterThan(0);
})->with(auroraPageCases());

test('Aurora home widgets resolve six owned views and omit my-groups', function (): void {
    $owner = User::factory()->create(['username' => 'AuroraWidgetOwner', 'motto' => 'Aurora motto']);
    $visitor = User::factory()->create();
    $friend = User::factory()->create(['username' => 'AuroraFriend']);
    DB::table('messenger_friendships')->insert(['user_one_id' => $owner->id, 'user_two_id' => $friend->id, 'relation' => 0, 'friends_since' => time(), 'category' => 0]);
    $owner->receivedHomeMessages()->create(['user_id' => $visitor->id, 'content' => 'Aurora guestbook']);
    $owner->homeRatings()->create(['user_id' => $visitor->id, 'rating' => 5]);
    DB::table('rooms')->insert(['owner_id' => $owner->id, 'name' => 'Aurora room', 'description' => 'Room', 'state' => 'open']);

    foreach ([
        'My Profile' => 'Aurora motto',
        'My Friends' => 'AuroraFriend',
        'My Guestbook' => 'Aurora guestbook',
        'My Badges' => 'No badges yet.',
        'My Rooms' => 'Aurora room',
        'My Rating' => 'Average: 5.0',
    ] as $widget => $content) {
        $item = HomeItem::create(['name' => $widget, 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
        $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);
        $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
            ->assertJsonPath('name', $widget)
            ->assertJsonPath('content', fn (?string $html): bool => is_string($html) && str_contains($html, $content));
    }

    $groups = HomeItem::create(['name' => 'My Groups', 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $groupsPlaced = $owner->homeItems()->create(['home_item_id' => $groups->id, 'placed' => true, 'theme' => 'default']);
    $this->getJson(route('home.widget-content', [$owner->username, $groupsPlaced->id]))
        ->assertJsonPath('widget_type', 'my-groups')
        ->assertJsonPath('content', null);
});

test('Aurora can omit a non-core home widget and return empty content', function (): void {
    File::delete($this->auroraThemePath . '/views/home/widgets/my-profile.blade.php');
    $owner = User::factory()->create();
    $item = HomeItem::create(['name' => 'My Profile', 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);

    $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
        ->assertOk()
        ->assertJsonPath('widget_type', 'my-profile')
        ->assertJsonPath('content', null);
});

test('an omitted home widget does not load its data', function (): void {
    File::delete($this->auroraThemePath . '/views/home/widgets/my-friends.blade.php');
    $owner = User::factory()->create();
    $item = HomeItem::create(['name' => 'My Friends', 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);
    DB::enableQueryLog();

    $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
        ->assertOk()
        ->assertJsonPath('widget_type', 'my-friends')
        ->assertJsonPath('content', null);

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains($query, 'messenger_friendships')))->toBeEmpty();
});

test('Aurora forms authenticate, validate, register, reset passwords, and log out', function (): void {
    $user = User::factory()->create(['username' => 'AuroraLogin', 'mail' => 'aurora-login@example.com']);
    $loginForm = auroraExtractForm(app(ThemeService::class)->view('auth.login', app(AuthPageService::class)->loginData())->render());
    expect($loginForm['action'])->toBe(route('login.store'))->and($loginForm['method'])->toBe('POST')->and($loginForm['fields'])->toHaveKey('_token');

    $this->post($loginForm['action'], [...$loginForm['fields'], 'username' => $user->username, 'password' => 'password'])
        ->assertRedirect(route('me.show'));
    expect(auth()->id())->toBe($user->id);

    auth()->logout();
    $invalid = $this->post($loginForm['action'], [...$loginForm['fields'], 'username' => $user->username, 'password' => 'wrong-password']);
    $invalid->assertSessionHasErrors('username');
    expect(auth()->check())->toBeFalse();

    $registration = $this->get(route('register'))->assertOk();
    $registrationForm = auroraExtractForm($registration->getContent());
    expect($registrationForm['action'])->toBe(route('register.store'))->and($registrationForm['method'])->toBe('POST')->and($registrationForm['fields'])->toHaveKey('_token');
    $this->post($registrationForm['action'], [...$registrationForm['fields'], 'username' => 'AuroraNew', 'mail' => 'aurora-new@example.com', 'password' => 'Aurora-password-42!', 'password_confirmation' => 'Aurora-password-42!', 'terms' => '1'])
        ->assertRedirect('/user/me');
    expect(User::where('username', 'AuroraNew')->exists())->toBeTrue();

    Mail::fake();
    auth()->logout();
    $forgot = $this->get(route('forgot.password.get'))->assertOk();
    $forgotForm = auroraExtractForm($forgot->getContent());
    $this->post($forgotForm['action'], [...$forgotForm['fields'], 'mail' => $user->mail])->assertRedirect();
    Mail::assertQueued(ResetPasswordMail::class);
    $token = PasswordResetToken::where('email', $user->mail)->firstOrFail();
    $plainToken = 'aurora-password-reset';
    $token->forceFill(['token' => PasswordResetToken::hashToken($plainToken)])->save();
    $reset = $this->get(route('reset.password.get', $plainToken))->assertOk();
    $resetForm = auroraExtractForm($reset->getContent());
    $this->post($resetForm['action'], [...$resetForm['fields'], 'password' => 'Aurora-new-password-42!', 'password_confirmation' => 'Aurora-new-password-42!'])->assertRedirect(route('login'));
    expect(Hash::check('Aurora-new-password-42!', $user->fresh()->password))->toBeTrue();

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'Aurora-new-password-42!'])->assertRedirect(route('me.show'));
    $shellForm = auroraExtractForm($this->get(route('me.show'))->getContent());
    $this->post($shellForm['action'], $shellForm['fields'])->assertRedirect();
    expect(auth()->check())->toBeFalse();
});

test('Aurora Livewire article updates use the shared endpoint and preserve Aurora markup', function (): void {
    $article = auroraArticle();
    $reader = User::factory()->create(['username' => 'AuroraReader']);
    $page = $this->actingAs($reader)->get(route('article.show', $article->slug))->assertOk();
    $commentSnapshot = auroraLivewireSnapshot($page->getContent(), 'article-comments')['snapshot'];
    $commentResponse = $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $commentSnapshot, 'updates' => ['comment' => 'Aurora comment'], 'calls' => [['method' => 'postComment', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertOk();
    $comment = $article->comments()->where('comment', 'Aurora comment')->firstOrFail();
    expect($commentResponse->json('components.0.effects.html'))->toContain('Aurora comment')->not->toContain('bg-[#f5f5f5]', 'bg-[#21242e]');

    $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $commentResponse->json('components.0.snapshot'), 'updates' => [], 'calls' => [['method' => 'deleteComment', 'params' => [$comment->id]]]]]], ['X-Livewire' => 'true'])->assertOk();
    $this->assertDatabaseMissing('website_article_comments', ['id' => $comment->id]);

    $reactionSnapshot = auroraLivewireSnapshot($page->getContent(), 'article-reactions')['snapshot'];
    $reactionResponse = $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $reactionSnapshot, 'updates' => [], 'calls' => [['method' => 'toggleReaction', 'params' => ['like']]]]]], ['X-Livewire' => 'true'])->assertOk();
    $this->assertDatabaseHas('website_article_reactions', ['article_id' => $article->id, 'user_id' => $reader->id, 'reaction' => 'like', 'active' => true]);
    expect($reactionResponse->json('components.0.effects.html'))->toContain('like')->not->toContain('bg-[#f5f5f5]', 'bg-[#21242e]');

    $open = $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $reactionResponse->json('components.0.snapshot'), 'updates' => [], 'calls' => [['method' => 'openModal', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertOk();
    expect($open->json('components.0.effects.html'))->toContain('<dialog open aria-label="Add reaction">');
    $close = $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $open->json('components.0.snapshot'), 'updates' => [], 'calls' => [['method' => 'closeModal', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertOk();
    expect($close->json('components.0.effects.html'))->not->toContain('<dialog open aria-label="Add reaction">');
});

test('Aurora cannot fall back to a root collision when its required view is missing', function (): void {
    setSetting('maintenance_enabled', '1');
    File::delete($this->auroraThemePath . '/views/maintenance.blade.php');
    $collisionPath = $this->auroraTemporaryPath . '/collision';
    File::ensureDirectoryExists($collisionPath);
    File::put($collisionPath . '/maintenance.blade.php', '<h1>Collision</h1>');
    View::getFinder()->setPaths([resource_path('views'), $collisionPath]);
    View::getFinder()->flush();

    expect(fn (): mixed => theme_view('maintenance'))->toThrow(ThemePresentationException::class);
    $this->get(route('maintenance.show'))->assertNotFound();
});

test('Aurora cannot shadow Core or Filament namespaces and static references stay qualified', function (): void {
    File::ensureDirectoryExists($this->auroraThemePath . '/views/vendor/core/errors');
    File::ensureDirectoryExists($this->auroraThemePath . '/views/vendor/filament-panels/components/page');
    File::put($this->auroraThemePath . '/views/vendor/core/errors/theme-unavailable.blade.php', '<h1>Aurora shadow</h1>');
    File::put($this->auroraThemePath . '/views/vendor/filament-panels/components/page/index.blade.php', '<h1>Aurora Filament shadow</h1>');
    auroraRegisterThemeRuntime($this->auroraEmptyPath, $this->auroraEmptyPath);

    expect(realpath(View::getFinder()->find('core::errors.theme-unavailable')))->toBe(realpath(resource_path('views/core/errors/theme-unavailable.blade.php')))
        ->and(realpath(View::getFinder()->find('filament-panels::components.page.index')))->toBe(realpath(base_path('vendor/filament/filament/resources/views/components/page/index.blade.php')));

    File::delete($this->auroraThemePath . '/views/index.blade.php');
    app()->forgetInstance(ThemeService::class);
    app()->forgetScopedInstances();
    $this->get(route('welcome'))->assertNotFound()->assertDontSee('Aurora shadow');

    $violations = [];
    foreach (File::allFiles($this->auroraThemePath) as $file) {
        $contents = File::get($file->getPathname());
        if (str_contains($contents, 'theme-atom') || str_contains($contents, 'theme-dusk')) {
            $violations[] = $file->getRelativePathname();
        }

        preg_match_all('/<\/?x-([A-Za-z0-9_.:-]+)/', $contents, $tags);
        foreach ($tags[1] as $tag) {
            if ($tag !== 'slot' && ! str_starts_with($tag, 'slot:') && ! str_starts_with($tag, 'theme-aurora::')) {
                $violations[] = $file->getRelativePathname() . ': ' . $tag;
            }
        }

        if (preg_match('/<livewire:[^>]+>/', $contents) === 0 && str_contains($contents, 'livewire:')) {
            $violations[] = $file->getRelativePathname() . ': malformed livewire tag';
        }
    }

    expect($violations)->toBe([]);
});

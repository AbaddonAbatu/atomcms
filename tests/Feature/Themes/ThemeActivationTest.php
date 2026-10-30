<?php

use App\Contracts\PaypalGateway;
use App\Exceptions\ThemePresentationException;
use App\Filament\Resources\Atom\Articles\Pages\EditArticle;
use App\Http\Middleware\SetThemeMiddleware;
use App\Models\Articles\WebsiteArticle;
use App\Models\Help\WebsiteHelpCenterCategory;
use App\Models\Miscellaneous\WebsiteInstallation;
use App\Models\Miscellaneous\WebsiteSetting;
use App\Models\Shop\WebsitePaypalTransaction;
use App\Models\User;
use App\Providers\ThemeServiceProvider;
use App\Services\InstallationService;
use App\Services\SettingsService;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\View\ViewException;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

beforeEach(function (): void {
    $this->activationOriginalBasePath = config('theme.base_path');
    $this->activationOriginalPublicPath = public_path();
    $this->activationOriginalStoragePath = storage_path();
    $this->activationOriginalEnvironment = app()->environment();
    $this->activationTemporaryPath = sys_get_temp_dir() . '/atom-theme-activation-' . bin2hex(random_bytes(12));

    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath($this->activationTemporaryPath . '/storage');
    File::ensureDirectoryExists(storage_path('vite'));
    themeActivationUseBasePath(resource_path('themes'));

    installHotel();
    setSetting('theme', 'atom');
});

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => $this->activationOriginalEnvironment);
    config(['theme.base_path' => $this->activationOriginalBasePath]);
    app()->usePublicPath($this->activationOriginalPublicPath);
    app()->useStoragePath($this->activationOriginalStoragePath);
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetInstance(ThemeService::class);
    app()->forgetScopedInstances();
    app()->register(ThemeServiceProvider::class, true);
    File::deleteDirectory($this->activationTemporaryPath);
});

function themeActivationUseBasePath(string $path): void
{
    config(['theme.base_path' => $path]);
    app()->forgetInstance(ThemeValidator::class);
    app()->forgetInstance(ThemeService::class);
    app()->forgetScopedInstances();
    app()->register(ThemeServiceProvider::class, true);
}

function themeActivationWriteAurora(string $root, string $id = 'aurora'): string
{
    $path = $root . '/' . $id;
    File::copyDirectory(base_path('tests/Fixtures/themes/aurora'), $path);

    return $path;
}

function themeActivationStoreCmsSetting(string $key, string $value): void
{
    WebsiteSetting::query()->updateOrCreate([
        'key' => $key,
    ], [
        'value' => $value,
        'comment' => 'Activation fixture',
    ]);
}

function themeActivationSnapshot(string $html, string $component): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encodedSnapshot) {
        $snapshot = html_entity_decode($encodedSnapshot, ENT_QUOTES | ENT_HTML5);
        $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

        if ($decoded['memo']['name'] === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException("The page did not render the {$component} component.");
}

/**
 * @return array<string, mixed>
 */
function themeActivationCompletedPaypalOrder(string $orderId): array
{
    return [
        'id' => $orderId,
        'status' => 'COMPLETED',
        'purchase_units' => [[
            'payments' => ['captures' => [[
                'id' => 'ACTIVATION-CAPTURE',
                'status' => 'COMPLETED',
                'amount' => ['value' => '10.00', 'currency_code' => 'USD'],
            ]]],
        ]],
    ];
}

test('a missing page template leaves unrelated pages and writes available', function (): void {
    $root = $this->activationTemporaryPath . '/themes';
    $theme = themeActivationWriteAurora($root);
    File::delete($theme . '/views/auth/passwords/forget.blade.php');
    themeActivationUseBasePath($root);
    setSetting('theme', 'aurora');

    $this->get(route('welcome'))->assertOk()->assertViewIs('theme-aurora::index');
    $this->get(route('forgot.password.get'))->assertNotFound();

    $user = User::factory()->create();
    $category = WebsiteHelpCenterCategory::create(['name' => 'Support', 'content' => 'Support', 'position' => 1]);
    $this->actingAs($user)->post(route('help-center.ticket.store'), [
        'category_id' => $category->id, 'title' => 'A valid ticket title', 'content' => 'A valid ticket body',
    ])->assertRedirect();

    expect($user->tickets()->count())->toBe(1);
});

test('invalid externally written theme settings return the Core 503 diagnostic without assets', function (string $kind): void {
    if ($kind === 'rejected') {
        $root = $this->activationTemporaryPath . '/themes';
        themeActivationWriteAurora($root, 'Bad_Theme');
        themeActivationUseBasePath($root);
        setSetting('theme', 'Bad_Theme');
    } else {
        setSetting('theme', 'unknown');
    }

    $this->get(route('welcome'))
        ->assertServiceUnavailable()
        ->assertSee('Service Unavailable')
        ->assertSee('The site is temporarily unavailable. Please try again later.')
        ->assertDontSee('/build/themes/', escape: false)
        ->assertDontSee('<link rel="stylesheet"', escape: false);
    $this->getJson(route('welcome'))
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Service Unavailable');
})->with(['unknown', 'rejected']);

test('missing theme views return a safe HTML or JSON 404 without an error report', function (string $view, string $route): void {
    $root = $this->activationTemporaryPath . '/themes';
    $theme = themeActivationWriteAurora($root);
    File::delete($theme . '/views/' . str_replace('.', '/', $view) . '.blade.php');
    themeActivationUseBasePath($root);
    setSetting('theme', 'aurora');
    Log::spy();

    $this->get(route($route))->assertNotFound()->assertSee('Not Found')
        ->assertDontSee($theme, escape: false)
        ->assertDontSee('/build/themes/', escape: false);
    $this->getJson(route($route))->assertNotFound()->assertExactJson(['message' => 'Not Found']);

    Log::shouldNotHaveReceived('error');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, "does not provide view [{$view}]"));
})->with([
    'missing required page' => ['index', 'welcome'],
    'missing non-core page' => ['community.articles', 'article.index'],
]);

test('a view missing inside a rendering page returns the Core 503 diagnostic and is reported', function (string $missing, Closure $prepare): void {
    $root = $this->activationTemporaryPath . '/themes';
    $theme = themeActivationWriteAurora($root);
    File::delete($theme . '/views/' . str_replace('.', '/', $missing) . '.blade.php');
    themeActivationUseBasePath($root);
    setSetting('theme', 'aurora');
    $route = $prepare($theme);
    Log::spy();

    $this->get($route)->assertServiceUnavailable()->assertSee('Service Unavailable')
        ->assertDontSee($theme, escape: false);
    $this->getJson($route)->assertServiceUnavailable()->assertJsonPath('message', 'Service Unavailable');

    Log::shouldHaveReceived('error');
})->with([
    'partial requested inside Blade' => ['community.badges', function (string $theme): string {
        File::put($theme . '/views/index.blade.php', '{{ theme_view("community.badges") }}');

        return route('welcome');
    }],
    'Livewire component view inside a page' => ['livewire.article-comments', function (): string {
        $article = WebsiteArticle::create(['user_id' => User::factory()->create()->id, 'title' => 'Nested view check', 'short_story' => 'Short', 'full_story' => '<p>Story</p>', 'image' => 'articles/nested.png', 'can_comment' => true]);

        return route('article.show', $article->slug);
    }],
]);

test('an invalid theme blocks an ordinary public write before creating a ticket, including a forged Livewire header', function (): void {
    setSetting('theme', 'unknown');
    $user = User::factory()->create();
    $category = WebsiteHelpCenterCategory::create(['name' => 'Activation support', 'content' => 'Support', 'position' => 1]);
    $data = ['category_id' => $category->id, 'title' => 'A valid ticket title', 'content' => 'A valid ticket body'];

    $this->actingAs($user)->post(route('help-center.ticket.store'), $data)->assertServiceUnavailable();
    $this->actingAs($user)->postJson(route('help-center.ticket.store'), $data, ['X-Livewire' => 'true'])->assertServiceUnavailable();

    expect($user->tickets()->count())->toBe(0);
});

test('logout, the installer, payment callbacks, the webhook, and a public API route remain available with an invalid theme', function (): void {
    setSetting('theme', 'unknown');
    config(['habbo.paypal.webhook_id' => 'ACTIVATION-WEBHOOK']);
    $user = User::factory()->create(['website_balance' => 0]);
    $transaction = $user->transactions()->create([
        'transaction_id' => 'ACTIVATION-ORDER',
        'status' => WebsitePaypalTransaction::STATUS_CREATED,
        'amount' => 1000,
        'currency' => 'USD',
    ]);
    $gateway = Mockery::mock(PaypalGateway::class);
    $gateway->shouldReceive('captureOrder')->once()->andReturn(themeActivationCompletedPaypalOrder($transaction->transaction_id));
    $gateway->shouldReceive('verifyWebhook')->once()->andReturnTrue();
    $this->app->instance(PaypalGateway::class, $gateway);

    $this->actingAs($user)->post(route('logout'))->assertRedirect();
    $this->assertGuest();

    WebsiteInstallation::query()->update(['completed' => false, 'step' => 0]);
    Cache::flush();
    app()->forgetInstance(InstallationService::class);
    app()->forgetInstance(SettingsService::class);
    $this->get(route('installation.index'))->assertOk()->assertSee('Welcome to Atom CMS');
    WebsiteInstallation::query()->update(['completed' => true]);
    Cache::flush();
    app()->forgetInstance(InstallationService::class);

    $this->actingAs($user)->get(route('paypal.successful-transaction', ['token' => $transaction->transaction_id]))
        ->assertSessionHas('success');
    expect($transaction->refresh()->status)->toBe(WebsitePaypalTransaction::STATUS_COMPLETED)
        ->and((int) $user->refresh()->website_balance)->toBe(1000);

    $cancelled = $user->transactions()->create([
        'transaction_id' => 'ACTIVATION-CANCELLED',
        'status' => WebsitePaypalTransaction::STATUS_CREATED,
        'amount' => 1000,
        'currency' => 'USD',
    ]);
    $this->actingAs($user)->get(route('paypal.cancelled-transaction', ['token' => $cancelled->transaction_id]))
        ->assertSessionHasErrors('message');
    expect($cancelled->refresh()->status)->not->toBe(WebsitePaypalTransaction::STATUS_COMPLETED);

    $this->postJson(route('paypal.webhook'), [
        'id' => 'ACTIVATION-EVENT',
        'event_type' => 'CHECKOUT.ORDER.APPROVED',
        'resource' => [
            'id' => 'ACTIVATION-UNKNOWN-ORDER',
        ],
    ])->assertOk();
    $this->getJson('/api/online-count')->assertOk();
});

test('housekeeping dashboard and Filament actions remain available with an invalid theme', function (): void {
    setSetting('theme', 'unknown');
    grantHousekeepingPermission('can_access_housekeeping', 6);
    grantHousekeepingPermission('write_article', 6);
    grantHousekeepingPermission('edit_article', 6);
    $staff = User::factory()->create(['rank' => 6]);
    $article = WebsiteArticle::create(['user_id' => $staff->id, 'title' => 'Before activation', 'short_story' => 'Short', 'full_story' => '<p>Full</p>', 'image' => 'articles/activation.png', 'can_comment' => true]);

    $page = $this->actingAs($staff)->get('/housekeeping')->assertOk();
    expect($page->status())->toBe(200);

    $editPage = $this->get('/housekeeping/website/articles/' . $article->id . '/edit')->assertOk();
    $snapshot = themeActivationSnapshot($editPage->getContent(), Livewire::new(EditArticle::class)->getName());
    $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => ['data.title' => 'Saved through Filament'],
            'calls' => [['method' => 'save', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();

    expect($article->refresh()->title)->toBe('Saved through Filament');
});

test('public Livewire rejects a stale or invalid theme before comment and reaction mutations', function (): void {
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Livewire activation', 'short_story' => 'Short', 'full_story' => '<p>Full</p>', 'image' => 'articles/livewire.png', 'can_comment' => true]);
    $page = $this->actingAs($reader)->get(route('article.show', $article->slug))->assertOk();
    $commentsSnapshot = themeActivationSnapshot($page->getContent(), 'article-comments');
    $reactionsSnapshot = themeActivationSnapshot($page->getContent(), 'article-reactions');
    $snapshotData = json_decode($commentsSnapshot, true, flags: JSON_THROW_ON_ERROR);

    expect($snapshotData['data']['renderedTheme'])->toBe('atom');

    setSetting('theme', 'dusk');
    app()->forgetScopedInstances();
    $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $commentsSnapshot, 'updates' => ['comment' => 'Stale comment'], 'calls' => [['method' => 'postComment', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertStatus(419);
    $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $reactionsSnapshot, 'updates' => [], 'calls' => [['method' => 'toggleReaction', 'params' => ['like']]]]]], ['X-Livewire' => 'true'])->assertStatus(419);

    setSetting('theme', 'unknown');
    app()->forgetScopedInstances();
    $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $commentsSnapshot, 'updates' => ['comment' => 'Invalid comment'], 'calls' => [['method' => 'postComment', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertServiceUnavailable();
    $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $reactionsSnapshot, 'updates' => [], 'calls' => [['method' => 'toggleReaction', 'params' => ['like']]]]]], ['X-Livewire' => 'true'])->assertServiceUnavailable();

    setSetting('theme', 'atom');
    app()->forgetScopedInstances();
    $forged = $snapshotData;
    $forged['data']['renderedTheme'] = 'dusk';
    $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => json_encode($forged, JSON_THROW_ON_ERROR), 'updates' => ['comment' => 'Forged comment'], 'calls' => [['method' => 'postComment', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertStatus(419);

    expect($article->comments()->count())->toBe(0)
        ->and($article->reactions()->count())->toBe(0);
});

test('a render-time theme asset failure returns the Core 503 page', function (): void {
    app()->usePublicPath($this->activationTemporaryPath . '/empty-public');
    app()->useStoragePath($this->activationTemporaryPath . '/empty-storage');
    File::ensureDirectoryExists(public_path());
    File::ensureDirectoryExists(storage_path('vite'));
    $this->withVite();
    app()->forgetInstance(Vite::class);

    $this->get(route('welcome'))
        ->assertServiceUnavailable()
        ->assertSee('The site is temporarily unavailable. Please try again later.');
});

test('GuestLayout renders the selected theme and never falls back to Atom', function (): void {
    expect(Blade::render('<x-guest-layout>Fixture</x-guest-layout>'))->toContain('Fixture');

    setSetting('theme', 'dusk');
    app()->forgetScopedInstances();

    try {
        Blade::render('<x-guest-layout>Fixture</x-guest-layout>');
        $this->fail('Dusk rendered a guest layout it does not provide.');
    } catch (ViewException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ThemePresentationException::class)
            ->and($exception->getPrevious()->getMessage())->toContain('Theme [dusk]', 'does not provide view [layouts.guest]');
    }
});

test('the theme middleware lets headless requests through without resolving a theme', function (): void {
    setSetting('theme', 'unknown');
    config(['atom.mode' => 'headless']);
    $request = Request::create('/');
    $request->setRouteResolver(fn (): Route => (new Route('GET', '/', fn (): Response => response('unused')))->name('welcome'));

    $response = app(SetThemeMiddleware::class)->handle($request, fn (Request $request): Response => response('headless'));

    expect($response->getContent())->toBe('headless');
});

test('public Livewire checks only its own view before a mutation', function (string $missing, string $component, array $updates, array $calls, bool $allowed, int $expectedComments = 0, int $expectedReactions = 0): void {
    $root = $this->activationTemporaryPath . '/themes';
    $theme = themeActivationWriteAurora($root);
    themeActivationUseBasePath($root);
    setSetting('theme', 'aurora');
    $author = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Localized view checks', 'short_story' => 'Short', 'full_story' => '<p>Story</p>', 'image' => 'articles/view-check.png', 'can_comment' => true]);
    $page = $this->actingAs(User::factory()->create())->get(route('article.show', $article->slug))->assertOk();
    $snapshot = themeActivationSnapshot($page->getContent(), $component);
    File::delete($theme . '/views/' . str_replace('.', '/', $missing) . '.blade.php');

    $response = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => $calls]],
    ], ['X-Livewire' => 'true']);

    if ($allowed) {
        $response->assertOk();
    } else {
        $response->assertNotFound();
    }

    expect($article->comments()->count())->toBe($expectedComments);
    expect($article->reactions()->count())->toBe($expectedReactions);
    if ($expectedComments > 0) {
        expect($article->comments()->value('comment'))->toBe('Posted on an incomplete theme');
    }
})->with([
    'missing own comments view' => ['livewire.article-comments', 'article-comments', ['comment' => 'Must not be saved'], [['method' => 'postComment', 'params' => []]], false],
    'missing own reactions view' => ['livewire.article-reactions', 'article-reactions', [], [['method' => 'toggleReaction', 'params' => ['like']]], false],
    'missing unrelated view permits comments' => ['banned', 'article-comments', ['comment' => 'Posted on an incomplete theme'], [['method' => 'postComment', 'params' => []]], true, 1, 0],
    'missing unrelated view permits reactions' => ['banned', 'article-reactions', [], [['method' => 'toggleReaction', 'params' => ['like']]], true, 0, 1],
]);

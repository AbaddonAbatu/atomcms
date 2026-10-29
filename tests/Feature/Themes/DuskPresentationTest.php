<?php

use App\Emulator\Contracts\BadgeRepository;
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
use App\Models\PasswordResetToken;
use App\Models\Shop\WebsiteShopCategory;
use App\Models\User;
use App\Models\User\Ban;
use App\Services\Auth\AuthPageService;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

beforeEach(function (): void {
    installHotel();
    config(['theme.parent' => null]);
    $this->duskPresentationEmptyPath = sys_get_temp_dir() . '/dusk-presentation-' . bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->duskPresentationEmptyPath);

    View::getFinder()->setPaths([resource_path('themes/dusk/views')]);
    View::getFinder()->replaceNamespace('theme-atom', [$this->duskPresentationEmptyPath]);
    View::getFinder()->flush();
});

afterEach(function (): void {
    File::deleteDirectory($this->duskPresentationEmptyPath);
});

test('Dusk renders Core SEO metadata and Fancybox assets on a public page', function (): void {
    setSetting('theme', 'dusk');
    setSetting('hotel_name', 'Dusk SEO Hotel');

    $this->get(route('welcome'))
        ->assertSee([
            '<meta property="og:site_name" content="Dusk SEO Hotel">',
            'https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0.31/dist/fancybox.umd.js',
            'https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0.31/dist/fancybox.css',
        ], false);

    assertDuskPresentationResolvedPaths();
});

function assertDuskPresentationResolvedPaths(): void
{
    $views = View::getFinder()->getViews();
    expect($views)->not->toBe([]);

    $roots = [
        realpath(resource_path('themes/dusk/views')),
        realpath(resource_path('views/core')),
        realpath(resource_path('views/vendor/turnstile')),
        realpath(base_path('vendor')),
    ];
    $violations = [];

    foreach ($views as $name => $path) {
        $resolved = realpath($path);
        $inside = false;

        foreach ($roots as $root) {
            if ($root === false) {
                continue;
            }

            $prefix = $root . DIRECTORY_SEPARATOR;
            $inside = PHP_OS_FAMILY === 'Windows'
                ? strncasecmp($resolved ?: '', $prefix, strlen($prefix)) === 0
                : str_starts_with($resolved ?: '', $prefix);

            if ($inside) {
                break;
            }
        }

        if ($resolved === false || ! $inside) {
            $violations[] = $name . ': ' . ($resolved ?: $path);
        }
    }

    expect($violations)->toBe([]);
}

function duskArticleSnapshotForComponent(string $html, string $component): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encodedSnapshot) {
        $snapshot = html_entity_decode($encodedSnapshot, ENT_QUOTES | ENT_HTML5);

        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException("The page did not render the {$component} component.");
}

test('Dusk renders all four formerly root authentication pages from its own views', function (): void {
    setSetting('theme', 'dusk');
    $user = User::factory()->create();
    PasswordResetToken::create(['email' => $user->mail, 'token' => PasswordResetToken::hashToken('dusk-token')]);

    $this->get(route('forgot.password.get'))->assertSee(['Forgot password', 'name="mail"'], false);
    $this->get(route('reset.password.get', 'dusk-token'))->assertSee(['Reset Password', 'name="password_confirmation"'], false);
    $this->actingAs($user)->get(route('password.confirm'))->assertSee(['Confirm your password', 'name="password"'], false);
    auth()->forgetGuards();
    $this->withSession(['login.id' => $user->id])->get(route('two-factor.login'))
        ->assertSee(['Two-Factor Authentication', 'name="code"', 'name="recovery_code"'], false);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders its client flash page', function (): void {
    setSetting('theme', 'dusk');
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('flash-client'))
        ->assertSee(['"sso.ticket":', 'You are nearly in Habbo!'], false);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders its teams and staff application pages', function (): void {
    setSetting('theme', 'dusk');
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    $member = User::factory()->create(['username' => 'ApplicantOne']);
    User::factory()->create(['username' => 'EventHost', 'team_id' => $team->id]);
    $rankPosition = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => DB::table('permissions')->min('id'), 'description' => 'Help us moderate the hotel.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);
    $teamPosition = WebsiteOpenPosition::create(['position_kind' => 'team', 'team_id' => $team->id, 'description' => 'Join the events crew.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs($member)->get(route('teams.index'))
        ->assertSee(['Events Team', 'EventHost']);
    $this->actingAs($member)->get(route('staff-applications.index'))
        ->assertSee(['Help us moderate the hotel.', 'href="' . route('staff-applications.show', $rankPosition) . '"'], false);
    $this->actingAs($member)->get(route('staff-applications.show', $rankPosition))
        ->assertSee(['name="username" value="ApplicantOne"', 'name="content"'], false);
    $this->actingAs($member)->get(route('team-applications.index'))
        ->assertSee(['Events Team', 'Join the events crew.', 'href="' . route('team-applications.show', $teamPosition) . '"'], false);
    $this->actingAs($member)->get(route('team-applications.show', $teamPosition))
        ->assertSee(['name="username" value="ApplicantOne"', 'name="content"'], false);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders all three ticket pages with their permitted data', function (): void {
    setSetting('theme', 'dusk');
    setSetting('force_staff_2fa', '0');
    $owner = User::factory()->create(['username' => 'TicketOwner']);
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?']);
    $ticket = $owner->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->actingAs($owner)->get(route('help-center.ticket.show', $ticket))
        ->assertSee(['Broken door', 'The door is jammed.']);
    $this->actingAs($owner)->get(route('help-center.ticket.edit', $ticket))
        ->assertSee(['value="Broken door"', 'name="category_id"'], false);
    $staff = User::factory()->create(['rank' => 7]);
    auth()->forgetGuards();
    $this->flushSession()->actingAs($staff)->get(route('help-center.ticket.index'))
        ->assertSee(['Broken door', 'TicketOwner']);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders maintenance with its task data', function (): void {
    setSetting('theme', 'dusk');
    setSetting('maintenance_enabled', '1');
    setSetting('maintenance_message', 'Back before lunch');
    $engineer = User::factory()->create();
    WebsiteMaintenanceTask::create(['user_id' => $engineer->id, 'task' => 'Upgrading the database']);

    $this->get(route('maintenance.show'))
        ->assertSee(['Maintenance break!', 'Upgrading the database', 'Back before lunch', 'Staff login']);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders an active member ban with its details and themed illustration', function (): void {
    setSetting('theme', 'dusk');
    $member = User::factory()->create();
    Ban::create(['user_id' => $member->id, 'ip' => '', 'machine_id' => '', 'user_staff_id' => $member->id, 'timestamp' => time(), 'ban_expire' => time() + 3600, 'ban_reason' => 'Dusk asset proof', 'type' => 'account']);

    $this->actingAs($member)->get(route('banned.show'))
        ->assertSee(['Dusk asset proof', 'Ban type:</strong> account'], false)
        ->assertSee('/assets/images/angry_frank.png', false);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders a rare value page through its own value view', function (): void {
    setSetting('theme', 'dusk');
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    $rare = WebsiteRareValue::create(['category_id' => $category->id, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);

    $this->actingAs(User::factory()->create())->get(route('values.value', $rare))
        ->assertSee('Blue Dragon Lamp');

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders an article page with both Livewire components and dark branch markers', function (): void {
    setSetting('theme', 'dusk');
    $author = User::factory()->create(['username' => 'ArticleAuthor']);
    $reader = User::factory()->create(['username' => 'ReaderOne']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $article->comments()->create(['user_id' => $reader->id, 'comment' => 'Congratulations on the launch!']);
    $article->reactions()->create(['user_id' => $reader->id, 'reaction' => 'like', 'active' => true]);

    $this->get(route('article.show', $article->slug))
        ->assertSee(['Grand opening', 'Congratulations on the launch!', 'bg-[#21242e]', 'bg-[#2b303c]'], false)
        ->assertSee('dark', false)
        ->assertDontSee('bg-[#f5f5f5]', false)
        ->assertDontSee('rounded bg-white shadow-sm dark:bg-gray-800', false);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders copied components on articles, photos, Nitro, shop, and toast pages', function (): void {
    setSetting('theme', 'dusk');
    $photographer = User::factory()->create(['username' => 'Photographer']);
    DB::table('camera_web')->insert(['user_id' => $photographer->id, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/lobby.png', 'visible' => true]);
    $category = WebsiteShopCategory::create(['name' => 'Credit packs', 'slug' => 'credit-packs', 'is_active' => true]);
    makePackage(['website_shop_category_id' => $category->id]);
    $member = User::factory()->create();

    $this->get(route('article.index'))->assertSee('No published articles');
    $this->actingAs($member)->get(route('photos.index'))
        ->assertSee(['data-fancybox="gallery"', 'fancybox.umd.js'], false);
    $this->actingAs($member)->get(route('nitro-client'))
        ->assertSee(['index.html?sso=', 'bg-[#eeb425]'], false);
    $this->actingAs($member)->get(route('shop.index'))
        ->assertSee(['Starter Bundle', 'x-data="{ open: false }"', 'bg-[#2b303c]'], false);
    auth()->logout();
    $this->withSession(['message' => 'Dusk toast'])->get(route('welcome'))
        ->assertSee(['toastHub', 'Dusk toast', 'dark pointer-events-none', 'bg-[#2b303c]', 'atom-credits'], false)
        ->assertDontSee('class="pointer-events-none fixed right-4', false);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders guest pages with article, photo, registration, referral, and login data', function (): void {
    setSetting('theme', 'dusk');
    $author = User::factory()->create(['username' => 'NewsWriter']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Hotel reopens', 'short_story' => 'Come in.', 'full_story' => '<p>Doors open.</p>', 'image' => 'articles/open.png']);
    DB::table('camera_web')->insert(['user_id' => $author->id, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/lobby.png', 'visible' => true]);
    User::factory()->create(['referral_code' => 'REFCODE1']);

    $this->get(route('welcome'))
        ->assertSee(['Hotel reopens', 'NewsWriter', 'href="' . route('article.show', $article->slug) . '"', '/camera/lobby.png'], false);
    $this->get(route('register'))
        ->assertSee(['name="mail"', 'name="password_confirmation"', 'Hotel reopens'], false);
    $this->get(route('register.referral', 'REFCODE1'))
        ->assertSee(['name="referral_code" value="REFCODE1"', 'Hotel reopens'], false);

    $login = app(ThemeService::class)->view('auth.login', app(AuthPageService::class)->loginData())->render();
    expect($login)->toContain('name="username"', 'name="password"', 'Hotel reopens');

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders the member dashboard and all settings states with their data', function (): void {
    setSetting('theme', 'dusk');
    setSetting('force_staff_2fa', '0');
    $member = User::factory()->create(['username' => 'DashboardMember', 'mail' => 'member@example.com', 'referral_code' => 'MYREF123']);
    $friend = User::factory()->create(['username' => 'OnlineFriend', 'online' => true]);
    DB::table('messenger_friendships')->insert(['user_one_id' => $member->id, 'user_two_id' => $friend->id, 'relation' => 0, 'friends_since' => time(), 'category' => 0]);
    WebsiteArticle::create(['user_id' => $friend->id, 'title' => 'Dashboard news', 'short_story' => 'News', 'full_story' => '<p>News</p>', 'image' => 'articles/news.png']);
    DB::table('sessions')->insert(['id' => 'dusk-session', 'user_id' => $member->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0', 'payload' => '', 'last_activity' => time() - 3600]);

    $this->actingAs($member)->get(route('me.show'))
        ->assertSee(['Dashboard news', 'OnlineFriend', 'name="referral" value="http://localhost/register/MYREF123"'], false);
    $this->actingAs($member)->get(route('settings.account.show'))
        ->assertSee(['name="mail" value="member@example.com"', 'name="motto"', 'name="current_password"'], false);
    $this->actingAs($member)->get(route('settings.password.show'))
        ->assertSee(['name="current_password"', 'name="password_confirmation"'], false);
    $this->actingAs($member)->get(route('settings.session-logs'))
        ->assertSee('203.0.113.7')
        ->assertDontSee('No session logs found');

    $member->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-one']))])->save();
    $this->actingAs($member->refresh())->get(route('settings.two-factor'))
        ->assertSee(['name="code"', 'recovery-code-one'], false);
    $member->forceFill(['two_factor_confirmed_at' => now()])->save();
    $this->actingAs($member->refresh())->get(route('settings.two-factor'))
        ->assertSee(['name="_method" value="delete"', 'Disable 2FA'], false);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders badge drawing, homes, and every home widget from owner data', function (): void {
    setSetting('theme', 'dusk');
    setSetting('force_staff_2fa', '0');
    setSetting('drawbadge_currency_value', '275');
    setSetting('drawbadge_currency_type', 'duckets');
    setSetting('badge_path_filesystem', storage_path('framework/testing/missing-badges'));
    $owner = User::factory()->create(['username' => 'HomeOwner', 'motto' => 'Home motto']);
    $visitor = User::factory()->create();
    $friend = User::factory()->create(['username' => 'BestFriend']);
    DB::table('messenger_friendships')->insert(['user_one_id' => $owner->id, 'user_two_id' => $friend->id, 'relation' => 0, 'friends_since' => time(), 'category' => 0]);
    $owner->receivedHomeMessages()->create(['user_id' => $visitor->id, 'content' => 'Lovely home']);
    $owner->homeRatings()->create(['user_id' => $visitor->id, 'rating' => 5]);
    app(BadgeRepository::class)->grant($owner, 'ACH_Test1');
    DB::table('rooms')->insert(['owner_id' => $owner->id, 'name' => 'Cozy Lounge', 'description' => 'A quiet room', 'state' => 'open']);

    $this->actingAs($owner)->get(route('draw-badge'))
        ->assertSee(["badgeDrawer({ cost: 275, currencyType: 'duckets' })", 'Badges path not configured.'], false);
    auth()->logout();
    $this->get(route('home.show', $owner->username))
        ->assertSee("x-data=\"homeManager('HomeOwner', false)\"", false)
        ->assertSee('Home of HomeOwner')
        ->assertDontSee('Edit Home');
    $this->actingAs($owner)->get(route('home.show', $owner->username))
        ->assertSee("x-data=\"homeManager('HomeOwner', true)\"", false)
        ->assertSee('Edit Home');

    foreach ([
        'My Profile' => 'Home motto',
        'My Friends' => 'BestFriend',
        'My Guestbook' => 'Lovely home',
        'My Badges' => 'ACH_Test1.gif',
        'My Rooms' => 'Cozy Lounge',
        'My Rating' => 'Average rating: 5.0',
    ] as $widget => $expectedContent) {
        $item = HomeItem::create(['name' => $widget, 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
        $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);
        $response = $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
            ->assertJsonPath('name', $widget);

        expect($response->json('content'))->toBeString()->toContain($expectedContent);
    }

    $groups = HomeItem::create(['name' => 'My Groups', 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $groupsPlaced = $owner->homeItems()->create(['home_item_id' => $groups->id, 'placed' => true, 'theme' => 'default']);
    $this->getJson(route('home.widget-content', [$owner->username, $groupsPlaced->id]))
        ->assertJsonPath('widget_type', 'my-groups')
        ->assertJsonPath('content', null);

    assertDuskPresentationResolvedPaths();
});

test('Dusk renders community, leaderboard, help, rare-value, and staff logo pages with their data', function (): void {
    setSetting('theme', 'dusk');
    setSetting('force_staff_2fa', '0');
    $author = User::factory()->create(['username' => 'ArticleAuthor']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png']);
    $staff = User::factory()->create(['username' => 'HeadAdmin', 'rank' => 7]);
    $member = User::factory()->create(['username' => 'RichestMember', 'credits' => 987654]);
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?', 'button_text' => 'Open ticket', 'button_url' => '/help-center/tickets/create']);
    $rulesCategory = WebsiteRuleCategory::create(['name' => 'Behaviour', 'description' => 'How to behave', 'badge' => 'RB1']);
    WebsiteRule::create(['category_id' => $rulesCategory->id, 'paragraph' => '1.1', 'rule' => 'Be kind to others']);
    $rareCategory = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    $rare = WebsiteRareValue::create(['category_id' => $rareCategory->id, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);
    $ticket = $member->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->get(route('article.index'))
        ->assertSee(['Grand opening', 'ArticleAuthor', 'href="' . route('article.show', $article->slug) . '"'], false);
    $this->actingAs($member)->get(route('staff.index'))
        ->assertSee(['HeadAdmin', 'href="' . route('home.show', 'HeadAdmin') . '"'], false);
    $this->actingAs($member)->get(route('leaderboard.index'))
        ->assertSee(['RichestMember', '987654'])
        ->assertDontSee('HeadAdmin');
    $this->actingAs($member)->get(route('help-center.index'))
        ->assertSee(['Account help', 'Trouble signing in?', 'href="/help-center/tickets/create"'], false);
    $this->get(route('help-center.rules.index'))
        ->assertSee(['Behaviour', 'How to behave', '<strong>1.1.</strong> Be kind to others'], false);
    $this->actingAs($member)->get(route('help-center.ticket.create'))
        ->assertSee(['action="http://localhost/help-center/tickets/store"', 'name="category_id"', 'Broken door', 'href="' . route('help-center.ticket.show', $ticket) . '"'], false);
    $this->actingAs($member)->get(route('values.index'))
        ->assertSee(['Dragons', 'Blue Dragon Lamp', 'name="search"', 'href="' . route('values.category', $rareCategory) . '"'], false);
    $this->actingAs($member)->get(route('values.category', $rareCategory))
        ->assertSee('Blue Dragon Lamp');
    $this->actingAs($member)->post(route('values.search'), ['search' => 'Dragon'])
        ->assertSee('Blue Dragon Lamp');
    auth()->forgetGuards();
    $this->flushSession()->actingAs($staff)->get(route('logo-generator.index'))
        ->assertSee(['Logo text', 'Download logo']);

    assertDuskPresentationResolvedPaths();
});

test('Dusk Livewire updates preserve its comment and reaction presentation through the real endpoint', function (): void {
    setSetting('theme', 'dusk');
    $author = User::factory()->create(['username' => 'ArticleAuthor']);
    $reader = User::factory()->create(['username' => 'ReaderOne']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);

    $page = $this->actingAs($reader)->get(route('article.show', $article->slug))->assertOk();
    $commentResponse = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => duskArticleSnapshotForComponent($page->getContent(), 'article-comments'),
            'updates' => ['comment' => 'Posted through Dusk'],
            'calls' => [['method' => 'postComment', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();
    $comment = $article->comments()->where('comment', 'Posted through Dusk')->firstOrFail();
    $commentHtml = $commentResponse->json('components.0.effects.html');
    expect($commentHtml)->toContain('Posted through Dusk', 'bg-[#21242e]', 'bg-[#2b303c]')
        ->not->toContain('bg-[#f5f5f5]', 'p-2 bg-gray-100 dark:bg-gray-900');

    $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $commentResponse->json('components.0.snapshot'),
            'updates' => [],
            'calls' => [['method' => 'deleteComment', 'params' => [$comment->id]]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();
    $this->assertDatabaseMissing('website_article_comments', ['id' => $comment->id]);

    $reactionResponse = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => duskArticleSnapshotForComponent($page->getContent(), 'article-reactions'),
            'updates' => [],
            'calls' => [['method' => 'toggleReaction', 'params' => ['like']]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();
    $reactionHtml = $reactionResponse->json('components.0.effects.html');
    $this->assertDatabaseHas('website_article_reactions', ['article_id' => $article->id, 'user_id' => $reader->id, 'reaction' => 'like', 'active' => true]);
    expect($reactionHtml)->toContain('p-2 bg-gray-900 text-gray-100')
        ->not->toContain('bg-[#f5f5f5]', 'p-2 bg-gray-100 dark:bg-gray-900');

    $openResponse = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $reactionResponse->json('components.0.snapshot'),
            'updates' => [],
            'calls' => [['method' => 'openModal', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();
    $openHtml = $openResponse->json('components.0.effects.html');
    expect($openHtml)->toContain('Insert Reaction', 'p-2 bg-gray-900 text-gray-100')
        ->not->toContain('bg-[#f5f5f5]', 'p-2 bg-gray-100 dark:bg-gray-900');

    $closeResponse = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $openResponse->json('components.0.snapshot'),
            'updates' => [],
            'calls' => [['method' => 'closeModal', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();
    expect($closeResponse->json('components.0.effects.html'))->not->toContain('Insert Reaction');

    assertDuskPresentationResolvedPaths();
});

test('Dusk keeps every view component qualified and free of theme setting branches', function (): void {
    $viewRoot = resource_path('themes/dusk/views');
    $componentRoot = $viewRoot . DIRECTORY_SEPARATOR . 'components';
    $violations = [];

    foreach (File::allFiles($viewRoot) as $file) {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
        $path = $file->getPathname();
        $contents = File::get($path);
        if (str_contains($contents, "setting('theme'") || str_contains($contents, 'setting("theme"')) {
            $violations[] = $relative . ': reads setting(theme)';
        }

        preg_match_all('/<\\/?x-([A-Za-z0-9_.:-]+)/', $contents, $matches);
        foreach ($matches[1] as $tag) {
            if (in_array($tag, ['app-layout', 'turnstile', 'turnstile.scripts'], true) || $tag === 'slot' || str_starts_with($tag, 'slot:')) {
                continue;
            }

            if (str_starts_with($tag, 'core::')) {
                $root = resource_path('views/core/components');
                $name = substr($tag, strlen('core::'));
            } elseif (str_starts_with($tag, 'theme-dusk::')) {
                $root = $componentRoot;
                $name = substr($tag, strlen('theme-dusk::'));
            } else {
                $violations[] = $relative . ': ' . $tag;

                continue;
            }

            $component = $root . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.blade.php';
            $componentIndex = substr($component, 0, -strlen('.blade.php')) . DIRECTORY_SEPARATOR . 'index.blade.php';
            if (! File::isFile($component) && ! File::isFile($componentIndex)) {
                $violations[] = $relative . ': missing ' . $component;
            }
        }
    }

    expect($violations)->toBe([]);
});

test('Dusk resolves every required presentation view inside its own namespace', function (): void {
    setSetting('theme', 'dusk');
    $root = realpath(resource_path('themes/dusk/views'));
    $service = app(ThemeService::class);

    foreach (ThemeValidator::REQUIRED_VIEWS as $key) {
        $path = realpath($service->view($key)->getPath());
        $prefix = $root . DIRECTORY_SEPARATOR;
        $inside = $path !== false && (PHP_OS_FAMILY === 'Windows'
            ? strncasecmp($path, $prefix, strlen($prefix)) === 0
            : str_starts_with($path, $prefix));

        expect($inside)->toBeTrue();
    }
});

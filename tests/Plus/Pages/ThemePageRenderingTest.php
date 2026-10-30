<?php

use App\Emulator\Contracts\BadgeRepository;
use App\Emulator\Contracts\CurrencyRepository;
use App\Emulator\Contracts\FurnitureRepository;
use App\Emulator\Contracts\PlayerSettingsRepository;
use App\Enums\CurrencyTypes;
use App\Models\Articles\WebsiteArticle;
use App\Models\Community\RareValue\WebsiteRareValue;
use App\Models\Community\RareValue\WebsiteRareValueCategory;
use App\Models\Community\Staff\WebsiteOpenPosition;
use App\Models\Community\Teams\WebsiteTeam;
use App\Models\Help\WebsiteHelpCenterCategory;
use App\Models\Home\HomeItem;
use App\Models\Miscellaneous\WebsiteMaintenanceTask;
use App\Models\Shop\WebsiteShopCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\View\ViewException;

/*
|--------------------------------------------------------------------------
| PlusEMU characterization of today's public themes
|--------------------------------------------------------------------------
|
| Keep the native Plus repositories and schema, including unsupported features
| and recorded defects. Set the theme once per test: views are memoized.
| The native role IDs deliberately differ from their security levels.
*/

dataset('plus page rendering themes', ['atom theme' => 'atom', 'dusk theme' => 'dusk']);

beforeEach(function () {
    installHotel();
    setSetting('start_duckets', '0');
    setSetting('start_diamonds', '0');
    setSetting('start_points', '0');

    DB::table('roles')->insert([
        ['id' => 1, 'slug' => 'default', 'name' => 'Member', 'description' => '', 'weight' => 1, 'security_level' => 1, 'badge_code' => '', 'is_staff' => false],
        ['id' => 70, 'slug' => 'owner', 'name' => 'Hotel Owners', 'description' => 'Keep the Plus hotel running.', 'weight' => 700, 'security_level' => 7, 'badge_code' => 'ADM', 'is_staff' => true],
    ]);
});

test('the welcome page teases the latest article without camera photos on Plus', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['username' => 'NewsWriter']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Hotel reopens', 'short_story' => 'Come in.', 'full_story' => '<p>Doors open.</p>', 'image' => 'articles/open.png']);

    $this->get(route('welcome'))
        ->assertSee(['Hotel reopens', 'NewsWriter', 'action="http://localhost/login"', 'name="username"', 'name="password"'], false)
        ->assertSee('href="' . route('article.show', $article->slug) . '"', false)
        ->assertViewHas('photos', fn (Collection $photos): bool => $photos->isEmpty())
        ->assertDontSee(['Latest Photos', 'data-fancybox="gallery"'], false);
})->with('plus page rendering themes');

test('the registration form renders on the Plus schema', function (string $theme) {
    setSetting('theme', $theme);

    $this->get(route('register'))
        ->assertSee(['action="http://localhost/register"', 'name="mail"', 'name="password_confirmation"', 'name="terms"'], false);
})->with('plus page rendering themes');

test('the dashboard shows Plus online friends, currencies, articles and the referral link', function (string $theme, bool $reverseFriendship) {
    setSetting('theme', $theme);
    $member = User::factory()->create(['credits' => 0, 'referral_code' => 'MYREF123']);
    $friend = User::factory()->create(['username' => 'OnlineFriend', 'online' => true]);
    $offline = User::factory()->create(['username' => 'OfflineFriend', 'online' => false]);
    DB::table('messenger_friendships')->insert([
        ['user_one_id' => $reverseFriendship ? $friend->id : $member->id, 'user_two_id' => $reverseFriendship ? $member->id : $friend->id, 'relationship' => 0],
        ['user_one_id' => $member->id, 'user_two_id' => $offline->id, 'relationship' => 0],
    ]);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Credits, 24680);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Duckets, 13579);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Diamonds, 8642);
    WebsiteArticle::create(['user_id' => $friend->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png']);

    $this->actingAs($member->refresh())->get(route('me.show'))
        ->assertSee(['Grand opening', 'OnlineFriend', '24680', '13579', '8642', 'name="referral" value="http://localhost/register/MYREF123"'], false)
        ->assertDontSee('OfflineFriend');
})->with('plus page rendering themes')->with(['outgoing friendship' => false, 'incoming friendship' => true]);

test('the leaderboard renders Plus currencies and all supported statistics and excludes staff', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create(['username' => 'RichestMember', 'credits' => 0]);
    $staff = User::factory()->create(['username' => 'StaffMillionaire', 'rank' => 7, 'credits' => 999999]);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Credits, 987654);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Duckets, 54321);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Diamonds, 3210);
    app(CurrencyRepository::class)->give($staff, CurrencyTypes::Duckets, 999999);
    app(CurrencyRepository::class)->give($staff, CurrencyTypes::Diamonds, 999999);
    DB::table('user_statistics')->where('id', $member->id)->update(['OnlineTime' => 7200, 'Respect' => 87, 'AchievementScore' => 654]);
    DB::table('user_statistics')->where('id', $staff->id)->update(['OnlineTime' => 999999, 'Respect' => 999999, 'AchievementScore' => 999999]);

    $this->actingAs(User::factory()->create())->get(route('leaderboard.index'))
        ->assertSee(['RichestMember', '987654 Credits', '54321 Duckets', '3210 Diamonds', '2 Hours online', '87 Respect received', '654 Achievement points'])
        ->assertDontSee('StaffMillionaire');
})->with('plus page rendering themes');

test('the Plus camera feature gate redirects members with an unavailable error', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create())->get(route('photos.index'))
        ->assertRedirect(route('welcome'))
        ->assertSessionHasErrors(['message' => 'This feature is not available on this hotel.']);
})->with('plus page rendering themes');

test('the Nitro client embeds the SSO ticket issued by Plus', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get(route('nitro-client'));

    $ticket = $member->fresh()->auth_ticket;
    expect($ticket)->toBeString()->not->toBeEmpty();
    $response->assertSee('index.html?sso=' . $ticket, false);
    $this->assertDatabaseHas('users', ['id' => $member->id, 'auth_ticket' => $ticket, 'auth_ticket_exchanged' => false]);
})->with('plus page rendering themes');

test('a banned Plus member sees their username-targeted ban details', function (string $theme) {
    $this->travelTo(Carbon::parse('2026-10-23 00:00:00 UTC'));
    setSetting('theme', $theme);
    setSetting('discord_invitation_link', 'https://discord.gg/appeal');
    $member = User::factory()->create();
    DB::table('bans')->insert(['bantype' => 'user', 'value' => $member->username, 'reason' => 'Spamming the lobby', 'expire' => '2030-06-15 12:00:00', 'added_by' => 'system', 'added_date' => now()]);

    $this->actingAs($member)->get(route('banned.show'))
        ->assertSee('Ban type:</strong> user', false)
        ->assertSee(['Spamming the lobby', '2030/06/15', 'https://discord.gg/appeal'], false);
})->with('plus page rendering themes');

test('a guest on a Plus banned address sees the IP ban details', function (string $theme) {
    $this->freezeTime();
    setSetting('theme', $theme);
    DB::table('bans')->insert(['bantype' => 'ip', 'value' => '127.0.0.1', 'reason' => 'Shared address abuse', 'expire' => now()->addHour(), 'added_by' => 'system', 'added_date' => now()]);

    $this->get(route('banned.show'))
        ->assertSee('Ban type:</strong> ip', false)
        ->assertSee('Shared address abuse');
})->with('plus page rendering themes');

test('recorded defect: the Plus banned page crashes when the cached verdict outlives the ban', function (string $theme) {
    $this->freezeTime();
    Exceptions::fake();
    setSetting('theme', $theme);
    $member = User::factory()->create();
    DB::table('bans')->insert(['bantype' => 'user', 'value' => $member->username, 'reason' => 'Short ban', 'expire' => now()->addHour(), 'added_by' => 'system', 'added_date' => now()]);
    $this->actingAs($member)->get(route('banned.show'))->assertSee('Short ban');
    DB::table('bans')->delete();

    $this->get(route('banned.show'))->assertInternalServerError();

    Exceptions::assertReported(fn (ViewException $exception): bool => str_contains($exception->getMessage(), 'Attempt to read property "type" on null'));
})->with('plus page rendering themes');

test('Atom renders Plus articles whose author is missing', function (string $uri, string $content) {
    setSetting('theme', 'atom');
    User::factory()->create();
    WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless news', 'short_story' => 'No author', 'full_story' => '<p>Ghost story.</p>', 'image' => 'a.png']);

    $this->get($uri)->assertSee($content);
})->with([
    'article index' => ['/community/articles', 'Authorless news'],
    'article page' => ['/community/article/authorless-news', 'Ghost story.'],
]);

test('recorded defect: Dusk crashes on Plus articles whose author is missing', function (string $uri, string $failure) {
    Exceptions::fake();
    setSetting('theme', 'dusk');
    User::factory()->create();
    WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless news', 'short_story' => 'No author', 'full_story' => '<p>Ghost story.</p>', 'image' => 'a.png']);

    $this->get($uri)->assertInternalServerError();

    Exceptions::assertReported(fn (ViewException $exception): bool => str_contains($exception->getMessage(), $failure));
})->with([
    'article index' => ['/community/articles', 'Attempt to read property "username" on null'],
    'article page' => ['/community/article/authorless-news', 'Missing required parameter for [Route: home.show]'],
]);

test('account settings prefill Plus member data and post to the account route', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create(['mail' => 'member@example.com', 'motto' => 'Plus motto']);

    $this->actingAs($member)->get(route('settings.account.show'))
        ->assertSee(['action="http://localhost/user/settings/account"', 'name="mail" value="member@example.com"', 'name="motto"', 'Plus motto', 'name="current_password"'], false)
        ->assertDontSee('name="username"', false);
})->with('plus page rendering themes');

test('recorded defect: granting a Plus name change still hides the username field', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create(['username' => 'RenamableOne', 'mail' => 'rename@example.com']);
    app(PlayerSettingsRepository::class)->setCanChangeName($member, true);

    $this->actingAs($member->refresh())->get(route('settings.account.show'))
        ->assertSee('name="mail" value="rename@example.com"', false)
        ->assertDontSee('name="username"', false);
})->with('plus page rendering themes');

test('Atom labels a Plus staff article author with their role', function () {
    setSetting('theme', 'atom');
    $author = User::factory()->create(['username' => 'StaffWriter', 'rank' => 7]);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Staff news', 'short_story' => 'From the owners', 'full_story' => '<p>A staff announcement.</p>', 'image' => 'articles/staff.png']);

    $this->get(route('article.show', $article->slug))
        ->assertSee(['StaffWriter', 'Hotel Owners', 'A staff announcement.'])
        ->assertDontSee('Member');
});

test('the maintenance notice lists the tasks and message and offers staff a sign-in', function (string $theme) {
    setSetting('theme', $theme);
    setSetting('maintenance_enabled', '1');
    setSetting('maintenance_message', 'Back before lunch');
    $engineer = User::factory()->create();
    WebsiteMaintenanceTask::create(['user_id' => $engineer->id, 'task' => 'Upgrading the database']);

    $this->get(route('maintenance.show'))
        ->assertSee('Upgrading the database')
        ->assertSee('Back before lunch')
        ->assertSee('action="http://localhost/login"', false);
})->with('plus page rendering themes');

test('the article index lists each article with its author', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['username' => 'ArticleAuthor']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png']);

    $this->get(route('article.index'))
        ->assertSee('Grand opening')
        ->assertSee('href="' . route('article.show', $article->slug) . '"', false)
        ->assertSee('ArticleAuthor');
})->with('plus page rendering themes');

test('an article page shows the story, author, other articles, comments and reactions to a guest', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['username' => 'ArticleAuthor', 'motto' => 'Author motto', 'rank' => 7]);
    $reader = User::factory()->create(['username' => 'ReaderOne']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome to the hotel, everyone.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $other = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Second post', 'short_story' => 'More news', 'full_story' => '<p>More.</p>', 'image' => 'articles/two.png']);
    $article->comments()->create(['user_id' => $reader->id, 'comment' => 'Congratulations on the launch!']);
    $article->reactions()->create(['user_id' => $reader->id, 'reaction' => 'like', 'active' => true]);

    $this->get(route('article.show', $article->slug))
        ->assertSee('<p>Welcome to the hotel, everyone.</p>', false)
        ->assertSee('ArticleAuthor')
        ->assertSee('Author motto')
        ->assertSee('href="' . route('home.show', $author->username) . '"', false)
        ->assertSee('href="' . route('article.show', $other->slug) . '"', false)
        ->assertSee('Congratulations on the launch!')
        ->assertSee('href="' . route('home.show', 'ReaderOne') . '"', false)
        ->assertSee("wire:click=\"toggleReaction('like')\"", false)
        ->assertDontSee('wire:submit="postComment"', false);
})->with('plus page rendering themes');

test('an article page gives a member the comment form and delete control for their own comment', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $comment = $article->comments()->create(['user_id' => $reader->id, 'comment' => 'First!']);

    $this->actingAs($reader)->get(route('article.show', $article->slug))
        ->assertSee(['wire:submit="postComment"', 'wire:model="comment"', 'wire:click="deleteComment(' . $comment->id . ')"'], false);
})->with('plus page rendering themes');

test('the ticket form posts to the store route and lists the member\'s open tickets', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create();
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?']);
    $ticket = $member->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->actingAs($member)->get(route('help-center.ticket.create'))
        ->assertSee(['action="http://localhost/help-center/tickets/store"', 'name="category_id"', 'name="title"', 'name="content"'], false)
        ->assertSee('Account help')
        ->assertSee('Broken door')
        ->assertSee('href="' . route('help-center.ticket.show', $ticket) . '"', false);
})->with('plus page rendering themes');

test('the staff page lists each rank with its members', function (string $theme) {
    setSetting('theme', $theme);
    User::factory()->create(['username' => 'HeadAdmin', 'rank' => 7]);

    $this->actingAs(User::factory()->create())->get(route('staff.index'))
        ->assertSee(['Hotel Owners', 'Keep the Plus hotel running.'])
        ->assertSee('href="' . route('home.show', 'HeadAdmin') . '"', false);
})->with('plus page rendering themes');

test('the teams page lists each team with its members', function (string $theme) {
    setSetting('theme', $theme);
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    User::factory()->create(['username' => 'EventHost', 'team_id' => $team->id]);

    $this->actingAs(User::factory()->create())->get(route('teams.index'))
        ->assertSee(['Events Team', 'Host events for the community.', 'EventHost']);
})->with('plus page rendering themes');

test('the staff applications page lists open rank positions with a link to apply', function (string $theme) {
    setSetting('theme', $theme);
    $position = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => 70, 'description' => 'Help us moderate the hotel.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create())->get(route('staff-applications.index'))
        ->assertSee('Help us moderate the hotel.')
        ->assertSee('href="' . route('staff-applications.show', $position) . '"', false);
})->with('plus page rendering themes');

test('the staff application form posts to the position with the applicant name locked', function (string $theme) {
    setSetting('theme', $theme);
    $position = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => 70, 'description' => 'Help us moderate the hotel.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create(['username' => 'ApplicantOne']))->get(route('staff-applications.show', $position))
        ->assertSee(['action="' . route('staff-applications.store', $position) . '"', 'name="username" value="ApplicantOne"', 'name="content"'], false);
})->with('plus page rendering themes');

test('the team applications page lists open team positions with a link to apply', function (string $theme) {
    setSetting('theme', $theme);
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    $position = WebsiteOpenPosition::create(['position_kind' => 'team', 'team_id' => $team->id, 'description' => 'Join the events crew.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create())->get(route('team-applications.index'))
        ->assertSee(['Events Team', 'Join the events crew.', 'href="' . route('team-applications.show', $position) . '"'], false);
})->with('plus page rendering themes');

test('the team application form posts to the position with the applicant name locked', function (string $theme) {
    setSetting('theme', $theme);
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    $position = WebsiteOpenPosition::create(['position_kind' => 'team', 'team_id' => $team->id, 'description' => 'Join the events crew.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create(['username' => 'ApplicantOne']))->get(route('team-applications.show', $position))
        ->assertSee(['action="' . route('team-applications.store', $position) . '"', 'name="username" value="ApplicantOne"', 'name="content"'], false);
})->with('plus page rendering themes');

test('the rare values index lists the categories and their rares with a search form', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    WebsiteRareValue::create(['category_id' => $category->id, 'name' => 'Blue Dragon Lamp', 'credit_value' => '250', 'currency_value' => '5', 'currency_type' => 'diamonds', 'furniture_icon' => 'dragonlamp.png']);

    $this->actingAs(User::factory()->create())->get(route('values.index'))
        ->assertSee(['Dragons', 'Blue Dragon Lamp', 'action="http://localhost/values/search"', 'name="search"', 'href="' . route('values.category', $category) . '"'], false);
})->with('plus page rendering themes');

test('a rare category page shows only the rares of that category', function (string $theme) {
    setSetting('theme', $theme);
    $dragons = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    $pets = WebsiteRareValueCategory::create(['name' => 'Pets', 'badge' => 'PET', 'priority' => 2]);
    WebsiteRareValue::create(['category_id' => $dragons->id, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);
    WebsiteRareValue::create(['category_id' => $pets->id, 'name' => 'Gold Parrot', 'furniture_icon' => 'parrot.png']);

    $this->actingAs(User::factory()->create())->get(route('values.category', $dragons))
        ->assertSee('Blue Dragon Lamp')
        ->assertDontSee('Gold Parrot');
})->with('plus page rendering themes');

test('a rare value page lists its holders and how many they own', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    $baseItemId = (int) DB::table('furniture')->insertGetId(['item_name' => 'dragonlamp']);
    $rare = WebsiteRareValue::create(['category_id' => $category->id, 'item_id' => $baseItemId, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);
    app(FurnitureRepository::class)->grant(User::factory()->create(['username' => 'LampCollector']), $baseItemId, 3);

    $this->actingAs(User::factory()->create())->get(route('values.value', $rare))
        ->assertSee(['Blue Dragon Lamp', 'LampCollector', '3 owned', 'href="' . route('values.index') . '"'], false);
})->with('plus page rendering themes');

test('the shop lists packages with their purchase and voucher forms', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteShopCategory::create(['name' => 'Credit packs', 'slug' => 'credit-packs', 'is_active' => true]);
    $package = makePackage(['website_shop_category_id' => $category->id]);

    $member = User::factory()->create(['website_balance' => 1234]);

    $this->actingAs($member)->get(route('shop.index'))
        ->assertSee('Current balance: USD 12.34')
        ->assertSee(['Starter Bundle', '100 Credits', 'Credit packs', 'href="' . route('shop.index', $category) . '"', 'action="' . route('shop.buy-package', $package) . '"', 'action="http://localhost/shop/voucher"', 'name="code"'], false);
})->with('plus page rendering themes');

test('a shop category page lists only its own packages', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteShopCategory::create(['name' => 'Credit packs', 'slug' => 'credit-packs', 'is_active' => true]);
    makePackage(['website_shop_category_id' => $category->id]);
    makePackage(['name' => 'Uncategorised Deal']);

    $this->actingAs(User::factory()->create())->get(route('shop.index', $category))
        ->assertSee('Starter Bundle')
        ->assertDontSee('Uncategorised Deal');
})->with('plus page rendering themes');

test('the logo generator is offered to staff', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 7]))->get(route('logo-generator.index'))
        ->assertSee(['Logo text', 'Download logo']);
})->with('plus page rendering themes');

test('members without the logo permission are sent to their dashboard with a message', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 1]))->get(route('logo-generator.index'))
        ->assertRedirect(route('me.show'))
        ->assertSessionHas('message', 'You do not have permission to do this.');
})->with('plus page rendering themes');

test('staff see the logo generator and housekeeping links on the dashboard', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 7]))->get(route('me.show'))
        ->assertSee(['href="' . route('logo-generator.index') . '"', 'href="/housekeeping"'], false);
})->with('plus page rendering themes');

test('members do not see the logo generator and housekeeping links on the dashboard', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 1]))->get(route('me.show'))
        ->assertDontSee(['href="' . route('logo-generator.index') . '"', 'href="/housekeeping"'], false);
})->with('plus page rendering themes');

test('a home page shows a visitor the owner\'s name without edit controls', function (string $theme) {
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'HomeOwner']);

    $this->get(route('home.show', $owner->username))
        ->assertSee("x-data=\"homeManager('HomeOwner', false)\"", false)
        ->assertSee('Home of HomeOwner')
        ->assertDontSee('Edit Home');
})->with('plus page rendering themes');

test('a home page gives its owner the edit controls', function (string $theme) {
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'HomeOwner']);

    $this->actingAs($owner)->get(route('home.show', $owner->username))
        ->assertSee("x-data=\"homeManager('HomeOwner', true)\"", false)
        ->assertSee('Edit Home');
})->with('plus page rendering themes');

test('home widgets render the owner\'s data as content', function (string $theme, string $widget, string $content) {
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'HomeOwner', 'motto' => 'Home motto']);
    $visitor = User::factory()->create();
    $friend = User::factory()->create(['username' => 'BestFriend']);
    DB::table('messenger_friendships')->insert(['user_one_id' => $owner->id, 'user_two_id' => $friend->id, 'relationship' => 0]);
    $owner->receivedHomeMessages()->create(['user_id' => $visitor->id, 'content' => 'Lovely home']);
    $owner->homeRatings()->create(['user_id' => $visitor->id, 'rating' => 5]);
    app(BadgeRepository::class)->grant($owner, 'ACH_Test1');
    makePlusRoom($owner->id, ['caption' => 'Cozy Lounge', 'description' => 'A quiet room', 'state' => 'open']);
    $item = HomeItem::create(['name' => $widget, 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);

    $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
        ->assertJsonPath('name', $widget)
        ->assertJsonPath('content', fn (string $html): bool => str_contains($html, $content));
})->with('plus page rendering themes')->with([
    'my profile' => ['My Profile', 'Home motto'],
    'my friends' => ['My Friends', 'BestFriend'],
    'my guestbook' => ['My Guestbook', 'Lovely home'],
    'my badges' => ['My Badges', 'ACH_Test1.gif'],
    'my rooms' => ['My Rooms', 'Cozy Lounge'],
    'my rating' => ['My Rating', 'Average rating: 5.0'],
]);

test('the my groups widget has no view and returns no content', function (string $theme) {
    setSetting('theme', $theme);
    $owner = User::factory()->create();
    $item = HomeItem::create(['name' => 'My Groups', 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);

    $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
        ->assertJsonPath('widget_type', 'my-groups')
        ->assertJsonPath('content', null);
})->with('plus page rendering themes');

test('Plus staff pages render multiple staff members with lazy loading prevented', function (string $theme) {
    setSetting('theme', $theme);
    User::factory()->create(['username' => 'HeadAdmin', 'rank' => 7]);
    User::factory()->create(['username' => 'SecondAdmin', 'rank' => 7]);

    $this->actingAs(User::factory()->create())->get(route('staff.index'))
        ->assertOk()
        ->assertSee(['Hotel Owners', 'HeadAdmin', 'SecondAdmin', 'Keep the Plus hotel running.'])
        ->assertSee('href="' . route('home.show', 'HeadAdmin') . '"', false)
        ->assertSee('href="' . route('home.show', 'SecondAdmin') . '"', false);
})->with('plus page rendering themes');

test('Plus staff pages render multiple staff members when lazy loading is allowed', function (string $theme) {
    setSetting('theme', $theme);
    User::factory()->create(['username' => 'HeadAdmin', 'rank' => 7]);
    User::factory()->create(['username' => 'SecondAdmin', 'rank' => 7]);
    $viewer = User::factory()->create();
    $preventLazyLoading = Model::preventsLazyLoading();
    Model::preventLazyLoading(false);

    try {
        $this->actingAs($viewer)->get(route('staff.index'))
            ->assertSee(['Hotel Owners', 'HeadAdmin', 'SecondAdmin', 'Keep the Plus hotel running.'])
            ->assertSee('href="' . route('home.show', 'HeadAdmin') . '"', false)
            ->assertSee('href="' . route('home.show', 'SecondAdmin') . '"', false);
    } finally {
        Model::preventLazyLoading($preventLazyLoading);
    }
})->with('plus page rendering themes');

<?php

use App\Emulator\Contracts\BadgeRepository;
use App\Emulator\Contracts\FurnitureRepository;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\View\ViewException;

/*
|--------------------------------------------------------------------------
| Characterization of today's public pages under both themes
|--------------------------------------------------------------------------
|
| Each test renders a page under the atom and dusk themes and asserts the
| records, form fields and form targets it shows right now. Tests named
| "recorded defect" pin behavior that is wrong today, so a fix shows up as a
| diff here. Set the theme once per test: resolved views are memoized.
*/

dataset('page rendering themes', ['atom theme' => 'atom', 'dusk theme' => 'dusk']);

beforeEach(function () {
    installHotel();
});

test('guests get the sign-in, registration and password forms of the active theme', function (string $theme, string $route, array $contents) {
    setSetting('theme', $theme);

    $this->get(route($route))->assertSee($contents, false);
})->with('page rendering themes')->with([
    'welcome page' => ['welcome', ['action="http://localhost/login"', 'name="username"', 'name="password"']],
    'registration' => ['register', ['action="http://localhost/register"', 'name="mail"', 'name="password_confirmation"', 'name="terms"']],
    'forgot password' => ['forgot.password.get', ['action="http://localhost/forgot-password"', 'name="mail"']],
]);

test('the welcome page teases the latest article and photo to guests', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['username' => 'NewsWriter']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Hotel reopens', 'short_story' => 'Come in.', 'full_story' => '<p>Doors open.</p>', 'image' => 'articles/open.png']);
    DB::table('camera_web')->insert(['user_id' => $author->id, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/lobby.png', 'visible' => true]);

    $this->get(route('welcome'))
        ->assertSee('Hotel reopens')
        ->assertSee('href="' . route('article.show', $article->slug) . '"', false)
        ->assertSee('NewsWriter')
        ->assertSee('/camera/lobby.png', false);
})->with('page rendering themes');

test('guests are redirected from member pages and the dead sign-in route', function (string $theme, string $uri, string $destination) {
    setSetting('theme', $theme);

    $this->get($uri)->assertRedirect(route($destination));
})->with('page rendering themes')->with([
    'dashboard' => ['/user/me', 'login'],
    'help center' => ['/help-center', 'login'],
    'photos' => ['/community/photos', 'login'],
    'nitro client' => ['/game/nitro', 'login'],
    'two-factor challenge without a pending sign-in' => ['/two-factor-challenge', 'login'],
    'sign-in route' => ['/login', 'welcome'],
]);

test('members are sent from guest-only pages to their dashboard', function (string $theme, string $uri) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create())->get($uri)->assertRedirect(route('me.show'));
})->with('page rendering themes')->with([
    'welcome page' => '/',
    'registration' => '/register',
    'forgot password' => '/forgot-password',
]);

test('the referral registration page carries the referrer code into the form', function (string $theme) {
    setSetting('theme', $theme);
    User::factory()->create(['referral_code' => 'REFCODE1']);

    $this->get(route('register.referral', 'REFCODE1'))
        ->assertSee('action="http://localhost/register"', false)
        ->assertSee('name="referral_code" value="REFCODE1"', false);
})->with('page rendering themes');

test('registration pages redirect to the welcome page with an error while registration is closed', function (string $theme, string $uri) {
    setSetting('theme', $theme);
    setSetting('disable_registration', '1');
    User::factory()->create(['referral_code' => 'REFCODE1']);

    $this->get($uri)->assertRedirect(route('welcome'))->assertSessionHasErrors('register');
})->with('page rendering themes')->with([
    'registration' => '/register',
    'referral registration' => '/register/REFCODE1',
]);

test('an unknown referral code is a 404', function (string $theme) {
    setSetting('theme', $theme);

    $this->get('/register/NOSUCHCODE')->assertNotFound();
})->with('page rendering themes');

test('the reset form posts the new password to the token url', function (string $theme) {
    setSetting('theme', $theme);
    $user = User::factory()->create();
    PasswordResetToken::create(['email' => $user->mail, 'token' => PasswordResetToken::hashToken('valid-token')]);

    $this->get(route('reset.password.get', 'valid-token'))
        ->assertSee('action="http://localhost/reset-password/valid-token"', false)
        ->assertSee('name="password_confirmation"', false);
})->with('page rendering themes');

test('an unknown reset token sends the visitor back to the forgot-password page', function (string $theme) {
    setSetting('theme', $theme);

    $this->get(route('reset.password.get', 'unknown-token'))
        ->assertRedirect(route('forgot.password.get'))
        ->assertSessionHasErrors('message');
})->with('page rendering themes');

test('the two-factor challenge asks for a code or a recovery code', function (string $theme) {
    setSetting('theme', $theme);
    $user = User::factory()->create();

    $this->withSession(['login.id' => $user->id])
        ->get(route('two-factor.login'))
        ->assertSee(['action="http://localhost/two-factor-challenge"', 'name="code"', 'name="recovery_code"'], false);
})->with('page rendering themes');

test('guests are redirected to the maintenance notice while maintenance is on', function (string $theme) {
    setSetting('theme', $theme);
    setSetting('maintenance_enabled', '1');

    $this->get(route('welcome'))->assertRedirect(route('maintenance.show'));
})->with('page rendering themes');

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
})->with('page rendering themes');

test('staff above the maintenance rank still reach the dashboard', function (string $theme) {
    setSetting('theme', $theme);
    setSetting('maintenance_enabled', '1');
    setSetting('force_staff_2fa', '0');
    $staff = User::factory()->create(['username' => 'NightEngineer', 'rank' => 7]);

    $this->actingAs($staff)->get(route('me.show'))->assertSee('NightEngineer');
})->with('page rendering themes');

test('a banned member sees the ban details instead of the site', function (string $theme) {
    setSetting('theme', $theme);
    setSetting('discord_invitation_link', 'https://discord.gg/appeal');
    $member = User::factory()->create();
    Ban::create(['user_id' => $member->id, 'ip' => '', 'machine_id' => '', 'user_staff_id' => $member->id, 'timestamp' => time(), 'ban_expire' => Carbon::parse('2030-06-15 12:00:00 UTC')->timestamp, 'ban_reason' => 'Spamming the lobby', 'type' => 'account']);

    $this->actingAs($member)->get(route('banned.show'))
        ->assertSee('Ban type:</strong> account', false)
        ->assertSee('Spamming the lobby')
        ->assertSee('2030/06/15')
        ->assertSee('https://discord.gg/appeal', false);
})->with('page rendering themes');

test('a guest on a banned address sees the ban details', function (string $theme) {
    setSetting('theme', $theme);
    $offender = User::factory()->create();
    Ban::create(['user_id' => $offender->id, 'ip' => '127.0.0.1', 'machine_id' => '', 'user_staff_id' => $offender->id, 'timestamp' => time(), 'ban_expire' => time() + 3600, 'ban_reason' => 'Shared address abuse', 'type' => 'ip']);

    $this->get(route('banned.show'))->assertSee('Ban type:</strong> ip', false)->assertSee('Shared address abuse');
})->with('page rendering themes');

test('recorded defect: the banned page crashes when the cached ban verdict outlives the ban', function (string $theme) {
    Exceptions::fake();
    setSetting('theme', $theme);
    $member = User::factory()->create();
    Ban::create(['user_id' => $member->id, 'ip' => '', 'machine_id' => '', 'user_staff_id' => $member->id, 'timestamp' => time(), 'ban_expire' => time() + 3600, 'ban_reason' => 'Short ban', 'type' => 'account']);
    $this->actingAs($member)->get(route('banned.show'));
    Ban::query()->delete();

    $this->get(route('banned.show'))->assertInternalServerError();

    Exceptions::assertReported(fn (ViewException $exception): bool => str_contains($exception->getMessage(), 'Attempt to read property "type" on null'));
})->with('page rendering themes');

test('the article index lists each article with its author', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['username' => 'ArticleAuthor']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png']);

    $this->get(route('article.index'))
        ->assertSee('Grand opening')
        ->assertSee('href="' . route('article.show', $article->slug) . '"', false)
        ->assertSee('ArticleAuthor');
})->with('page rendering themes');

test('an article page shows the story, author, other articles, comments and reactions to a guest', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['username' => 'ArticleAuthor', 'motto' => 'Author motto']);
    $reader = User::factory()->create(['username' => 'ReaderOne']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome to the hotel, everyone.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $other = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Second post', 'short_story' => 'More news', 'full_story' => '<p>More.</p>', 'image' => 'articles/two.png']);
    $article->comments()->create(['user_id' => $reader->id, 'comment' => 'Congratulations on the launch!']);
    $article->reactions()->create(['user_id' => $reader->id, 'reaction' => 'like', 'active' => true]);

    $this->get(route('article.show', $article->slug))
        ->assertSee('<p>Welcome to the hotel, everyone.</p>', false)
        ->assertSee('ArticleAuthor')
        ->assertSee('Author motto')
        ->assertSee('href="' . route('article.show', $other->slug) . '"', false)
        ->assertSee('Congratulations on the launch!')
        ->assertSee('href="' . route('home.show', 'ReaderOne') . '"', false)
        ->assertSee("wire:click=\"toggleReaction('like')\"", false)
        ->assertDontSee('wire:submit="postComment"', false);
})->with('page rendering themes');

test('an article page gives a member the comment form and delete control for their own comment', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $comment = $article->comments()->create(['user_id' => $reader->id, 'comment' => 'First!']);

    $this->actingAs($reader)->get(route('article.show', $article->slug))
        ->assertSee(['wire:submit="postComment"', 'wire:model="comment"', 'wire:click="deleteComment(' . $comment->id . ')"'], false);
})->with('page rendering themes');

test('atom renders an article and a photo whose author is missing', function (string $uri, string $content) {
    setSetting('theme', 'atom');
    WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless news', 'short_story' => 'No author', 'full_story' => '<p>Ghost story.</p>', 'image' => 'a.png']);
    DB::table('camera_web')->insert(['user_id' => 999998, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/ghost.png', 'visible' => true]);

    $this->actingAs(User::factory()->create())->get($uri)->assertSee($content);
})->with([
    'article index' => ['/community/articles', 'Authorless news'],
    'article page' => ['/community/article/authorless-news', 'Ghost story.'],
    'photos' => ['/community/photos', 'Unknown'],
]);

test('recorded defect: dusk pages crash on an article or photo whose author is missing', function (string $uri, string $failure) {
    Exceptions::fake();
    setSetting('theme', 'dusk');
    WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless news', 'short_story' => 'No author', 'full_story' => '<p>Ghost story.</p>', 'image' => 'a.png']);
    DB::table('camera_web')->insert(['user_id' => 999998, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/ghost.png', 'visible' => true]);

    $this->actingAs(User::factory()->create())->get($uri)->assertInternalServerError();

    Exceptions::assertReported(fn (ViewException $exception): bool => str_contains($exception->getMessage(), $failure));
})->with([
    'article index' => ['/community/articles', 'Attempt to read property "username" on null'],
    'article page' => ['/community/article/authorless-news', 'Missing required parameter for [Route: home.show]'],
    'photos' => ['/community/photos', 'Attempt to read property "username" on null'],
]);

test('the rules page lists each category and rule', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteRuleCategory::create(['name' => 'Behaviour', 'description' => 'How to behave', 'badge' => 'RB1']);
    WebsiteRule::create(['category_id' => $category->id, 'paragraph' => '1.1', 'rule' => 'Be kind to others']);

    $this->get(route('help-center.rules.index'))
        ->assertSee('Behaviour')
        ->assertSee('How to behave')
        ->assertSee('<strong>1.1.</strong> Be kind to others', false);
})->with('page rendering themes');

test('the help center lists each category with its button', function (string $theme) {
    setSetting('theme', $theme);
    WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?', 'button_text' => 'Open ticket', 'button_url' => '/help-center/tickets/create']);

    $this->actingAs(User::factory()->create())->get(route('help-center.index'))
        ->assertSee('Account help')
        ->assertSee('Trouble signing in?')
        ->assertSee('href="/help-center/tickets/create"', false);
})->with('page rendering themes');

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
})->with('page rendering themes');

test('a member sees their ticket and edit form with the ticket data', function (string $theme, string $route, array $contents) {
    setSetting('theme', $theme);
    $member = User::factory()->create();
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?']);
    $ticket = $member->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->actingAs($member)->get(route($route, $ticket))->assertSee($contents, false);
})->with('page rendering themes')->with([
    'ticket page' => ['help-center.ticket.show', ['Broken door', 'The door is jammed.', 'action="http://localhost/help-center/tickets/reply/', 'action="http://localhost/help-center/tickets/toggle-status/']],
    'edit form' => ['help-center.ticket.edit', ['value="Broken door"', 'The door is jammed.', 'action="http://localhost/help-center/tickets/edit/', 'name="category_id"']],
]);

test('another member cannot open or edit a ticket', function (string $theme, string $route) {
    setSetting('theme', $theme);
    $owner = User::factory()->create();
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?']);
    $ticket = $owner->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->actingAs(User::factory()->create(['rank' => 1]))->get(route($route, $ticket))->assertForbidden();
})->with('page rendering themes')->with(['ticket page' => 'help-center.ticket.show', 'edit form' => 'help-center.ticket.edit']);

test('staff see every ticket on the ticket index', function (string $theme) {
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'TicketOwner']);
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?']);
    $owner->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->actingAs(User::factory()->create(['rank' => 7]))->get(route('help-center.ticket.index'))
        ->assertSee(['Broken door', 'TicketOwner']);
})->with('page rendering themes');

test('members are refused the ticket index', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 1]))->get(route('help-center.ticket.index'))->assertForbidden();
})->with('page rendering themes');

test('the staff page lists each rank with its members', function (string $theme) {
    setSetting('theme', $theme);
    $rankName = DB::table('permissions')->where('id', 7)->value('rank_name');
    User::factory()->create(['username' => 'HeadAdmin', 'rank' => 7]);

    $this->actingAs(User::factory()->create())->get(route('staff.index'))
        ->assertSee($rankName)
        ->assertSee('href="' . route('home.show', 'HeadAdmin') . '"', false);
})->with('page rendering themes');

test('the teams page lists each team with its members', function (string $theme) {
    setSetting('theme', $theme);
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    User::factory()->create(['username' => 'EventHost', 'team_id' => $team->id]);

    $this->actingAs(User::factory()->create())->get(route('teams.index'))
        ->assertSee(['Events Team', 'Host events for the community.', 'EventHost']);
})->with('page rendering themes');

test('the staff applications page lists open rank positions with a link to apply', function (string $theme) {
    setSetting('theme', $theme);
    $position = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => DB::table('permissions')->min('id'), 'description' => 'Help us moderate the hotel.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create())->get(route('staff-applications.index'))
        ->assertSee('Help us moderate the hotel.')
        ->assertSee('href="' . route('staff-applications.show', $position) . '"', false);
})->with('page rendering themes');

test('the staff application form posts to the position with the applicant name locked', function (string $theme) {
    setSetting('theme', $theme);
    $position = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => DB::table('permissions')->min('id'), 'description' => 'Help us moderate the hotel.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create(['username' => 'ApplicantOne']))->get(route('staff-applications.show', $position))
        ->assertSee(['action="' . route('staff-applications.store', $position) . '"', 'name="username" value="ApplicantOne"', 'name="content"'], false);
})->with('page rendering themes');

test('the team applications page lists open team positions with a link to apply', function (string $theme) {
    setSetting('theme', $theme);
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    $position = WebsiteOpenPosition::create(['position_kind' => 'team', 'team_id' => $team->id, 'description' => 'Join the events crew.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create())->get(route('team-applications.index'))
        ->assertSee(['Events Team', 'Join the events crew.', 'href="' . route('team-applications.show', $position) . '"'], false);
})->with('page rendering themes');

test('the team application form posts to the position with the applicant name locked', function (string $theme) {
    setSetting('theme', $theme);
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    $position = WebsiteOpenPosition::create(['position_kind' => 'team', 'team_id' => $team->id, 'description' => 'Join the events crew.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);

    $this->actingAs(User::factory()->create(['username' => 'ApplicantOne']))->get(route('team-applications.show', $position))
        ->assertSee(['action="' . route('team-applications.store', $position) . '"', 'name="username" value="ApplicantOne"', 'name="content"'], false);
})->with('page rendering themes');

test('the rare values index lists the categories and their rares with a search form', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    WebsiteRareValue::create(['category_id' => $category->id, 'name' => 'Blue Dragon Lamp', 'credit_value' => '250', 'currency_value' => '5', 'currency_type' => 'diamonds', 'furniture_icon' => 'dragonlamp.png']);

    $this->actingAs(User::factory()->create())->get(route('values.index'))
        ->assertSee(['Dragons', 'Blue Dragon Lamp', 'action="http://localhost/values/search"', 'name="search"', 'href="' . route('values.category', $category) . '"'], false);
})->with('page rendering themes');

test('a rare category page shows only the rares of that category', function (string $theme) {
    setSetting('theme', $theme);
    $dragons = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    $pets = WebsiteRareValueCategory::create(['name' => 'Pets', 'badge' => 'PET', 'priority' => 2]);
    WebsiteRareValue::create(['category_id' => $dragons->id, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);
    WebsiteRareValue::create(['category_id' => $pets->id, 'name' => 'Gold Parrot', 'furniture_icon' => 'parrot.png']);

    $this->actingAs(User::factory()->create())->get(route('values.category', $dragons))
        ->assertSee('Blue Dragon Lamp')
        ->assertDontSee('Gold Parrot');
})->with('page rendering themes');

test('a rare value page lists its holders and how many they own', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    $rare = WebsiteRareValue::create(['category_id' => $category->id, 'item_id' => 4242, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);
    app(FurnitureRepository::class)->grant(User::factory()->create(['username' => 'LampCollector']), 4242, 3);

    $this->actingAs(User::factory()->create())->get(route('values.value', $rare))
        ->assertSee(['Blue Dragon Lamp', 'LampCollector', '3 owned', 'href="' . route('values.index') . '"'], false);
})->with('page rendering themes');

test('searching the rare values shows the matching rares', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    WebsiteRareValue::create(['category_id' => $category->id, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);

    $this->actingAs(User::factory()->create())->post(route('values.search'), ['search' => 'Dragon'])
        ->assertSee('Blue Dragon Lamp');
})->with('page rendering themes');

test('searching the rare values without a match returns to the previous page with an error', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create())->from(route('values.index'))->post(route('values.search'), ['search' => 'Unicorn'])
        ->assertRedirect(route('values.index'))
        ->assertSessionHasErrors('message');
})->with('page rendering themes');

test('the photos page links each photo and names its photographer', function (string $theme) {
    setSetting('theme', $theme);
    $photographer = User::factory()->create(['username' => 'Photographer']);
    DB::table('camera_web')->insert(['user_id' => $photographer->id, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/lobby.png', 'visible' => true]);

    $this->actingAs(User::factory()->create())->get(route('photos.index'))
        ->assertSee('href="/camera/lobby.png"', false)
        ->assertSee('Photographer');
})->with('page rendering themes');

test('the shop lists packages with their purchase and voucher forms', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteShopCategory::create(['name' => 'Credit packs', 'slug' => 'credit-packs', 'is_active' => true]);
    $package = makePackage(['website_shop_category_id' => $category->id]);

    $this->actingAs(User::factory()->create())->get(route('shop.index'))
        ->assertSee(['Starter Bundle', '100 Credits', 'Credit packs', 'href="' . route('shop.index', $category) . '"', 'action="' . route('shop.buy-package', $package) . '"', 'action="http://localhost/shop/voucher"', 'name="code"'], false);
})->with('page rendering themes');

test('a shop category page lists only its own packages', function (string $theme) {
    setSetting('theme', $theme);
    $category = WebsiteShopCategory::create(['name' => 'Credit packs', 'slug' => 'credit-packs', 'is_active' => true]);
    makePackage(['website_shop_category_id' => $category->id]);
    makePackage(['name' => 'Uncategorised Deal']);

    $this->actingAs(User::factory()->create())->get(route('shop.index', $category))
        ->assertSee('Starter Bundle')
        ->assertDontSee('Uncategorised Deal');
})->with('page rendering themes');

test('the leaderboard ranks members by their currencies and leaves staff out', function (string $theme) {
    setSetting('theme', $theme);
    User::factory()->create(['username' => 'RichestMember', 'credits' => 987654]);
    User::factory()->create(['username' => 'StaffMillionaire', 'credits' => 999999, 'rank' => 7]);

    $this->actingAs(User::factory()->create())->get(route('leaderboard.index'))
        ->assertSee(['RichestMember', '987654'])
        ->assertDontSee('StaffMillionaire');
})->with('page rendering themes');

test('the game client shells embed the member\'s sso ticket', function (string $theme, string $route, string $embed) {
    setSetting('theme', $theme);
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get(route($route));

    $response->assertSee(sprintf($embed, $member->fresh()->auth_ticket), false);
})->with('page rendering themes')->with([
    'nitro client' => ['nitro-client', 'index.html?sso=%s'],
    'flash client' => ['flash-client', '"sso.ticket": \'%s\''],
]);

test('the badge drawer shows the configured price and a missing badge folder', function (string $theme) {
    setSetting('theme', $theme);
    setSetting('drawbadge_currency_value', '275');
    setSetting('drawbadge_currency_type', 'duckets');
    setSetting('badge_path_filesystem', storage_path('framework/testing/missing-badges'));

    $this->actingAs(User::factory()->create())->get(route('draw-badge'))
        ->assertSee("badgeDrawer({ cost: 275, currencyType: 'duckets' })", false)
        ->assertSee('Badges path not configured.');
})->with('page rendering themes');

test('the logo generator is offered to staff', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 7]))->get(route('logo-generator.index'))
        ->assertSee(['Logo text', 'Download logo']);
})->with('page rendering themes');

test('members without the logo permission are sent to their dashboard with a message', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 1]))->get(route('logo-generator.index'))
        ->assertRedirect(route('me.show'))
        ->assertSessionHas('message', 'You do not have permission to do this.');
})->with('page rendering themes');

test('the dashboard shows the latest articles, online friends and the referral link', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create(['referral_code' => 'MYREF123']);
    $friend = User::factory()->create(['username' => 'OnlineFriend', 'online' => '1']);
    DB::table('messenger_friendships')->insert(['user_one_id' => $member->id, 'user_two_id' => $friend->id, 'relation' => 0, 'friends_since' => time(), 'category' => 0]);
    WebsiteArticle::create(['user_id' => $friend->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png']);

    $this->actingAs($member)->get(route('me.show'))
        ->assertSee(['Grand opening', 'OnlineFriend', 'name="referral" value="http://localhost/register/MYREF123"'], false);
})->with('page rendering themes');

test('staff see the logo generator and housekeeping links on the dashboard', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 7]))->get(route('me.show'))
        ->assertSee(['href="' . route('logo-generator.index') . '"', 'href="/housekeeping"'], false);
})->with('page rendering themes');

test('members do not see the logo generator and housekeeping links on the dashboard', function (string $theme) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create(['rank' => 1]))->get(route('me.show'))
        ->assertDontSee(['href="' . route('logo-generator.index') . '"', 'href="/housekeeping"'], false);
})->with('page rendering themes');

test('a member\'s form pages post to their own routes', function (string $theme, string $route, array $contents) {
    setSetting('theme', $theme);

    $this->actingAs(User::factory()->create())->get(route($route))->assertSee($contents, false);
})->with('page rendering themes')->with([
    'account settings' => ['settings.account.show', ['action="http://localhost/user/settings/account"', 'name="motto"', 'name="current_password"']],
    'password settings' => ['settings.password.show', ['action="http://localhost/user/settings/password"', 'name="current_password"', 'name="password_confirmation"']],
    'session logs without sessions' => ['settings.session-logs', ['No session logs found']],
    'two-factor activation' => ['settings.two-factor', ['action="http://localhost/user/settings/two-factor-authentication"', 'name="current_password"', 'Activate 2FA']],
    'confirm password' => ['password.confirm', ['action="/user/confirm-password"', 'name="password"']],
]);

test('account settings prefill the mail address and hide the username without a name change', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create(['mail' => 'member@example.com']);

    $this->actingAs($member)->get(route('settings.account.show'))
        ->assertSee('name="mail" value="member@example.com"', false)
        ->assertDontSee('name="username"', false);
})->with('page rendering themes');

test('account settings offer the username field once a name change is granted', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create(['username' => 'RenamableOne']);
    $member->settings()->updateOrCreate([], ['can_change_name' => '1']);

    $this->actingAs($member->refresh())->get(route('settings.account.show'))
        ->assertSee('name="username" value="RenamableOne"', false);
})->with('page rendering themes');

test('session logs list the member\'s sessions', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $member->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36', 'payload' => '', 'last_activity' => time() - 3600]);

    $this->actingAs($member)->get(route('settings.session-logs'))
        ->assertSee('203.0.113.7')
        ->assertDontSee('No session logs found');
})->with('page rendering themes');

test('two-factor settings ask a member with a pending secret for a confirmation code', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create();
    $member->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-one']))])->save();

    $this->actingAs($member)->get(route('settings.two-factor'))
        ->assertSee(['action="http://localhost/user/settings/two-factor-authentication/confirm"', 'name="code"', 'recovery-code-one'], false);
})->with('page rendering themes');

test('two-factor settings offer removal once two-factor authentication is confirmed', function (string $theme) {
    setSetting('theme', $theme);
    $member = User::factory()->create();
    $member->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-one'])), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($member)->get(route('settings.two-factor'))
        ->assertSee(['name="_method" value="delete"', 'Disable 2FA'], false);
})->with('page rendering themes');

test('a home page shows a visitor the owner\'s name without edit controls', function (string $theme) {
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'HomeOwner']);

    $this->get(route('home.show', $owner->username))
        ->assertSee("x-data=\"homeManager('HomeOwner', false)\"", false)
        ->assertSee('Home of HomeOwner')
        ->assertDontSee('Edit Home');
})->with('page rendering themes');

test('a home page gives its owner the edit controls', function (string $theme) {
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'HomeOwner']);

    $this->actingAs($owner)->get(route('home.show', $owner->username))
        ->assertSee("x-data=\"homeManager('HomeOwner', true)\"", false)
        ->assertSee('Edit Home');
})->with('page rendering themes');

test('home widgets render the owner\'s data as content', function (string $theme, string $widget, string $content) {
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'HomeOwner', 'motto' => 'Home motto']);
    $visitor = User::factory()->create();
    $friend = User::factory()->create(['username' => 'BestFriend']);
    DB::table('messenger_friendships')->insert(['user_one_id' => $owner->id, 'user_two_id' => $friend->id, 'relation' => 0, 'friends_since' => time(), 'category' => 0]);
    $owner->receivedHomeMessages()->create(['user_id' => $visitor->id, 'content' => 'Lovely home']);
    $owner->homeRatings()->create(['user_id' => $visitor->id, 'rating' => 5]);
    app(BadgeRepository::class)->grant($owner, 'ACH_Test1');
    DB::table('rooms')->insert(['owner_id' => $owner->id, 'name' => 'Cozy Lounge', 'description' => 'A quiet room', 'state' => 'open']);
    $item = HomeItem::create(['name' => $widget, 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);

    $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
        ->assertJsonPath('name', $widget)
        ->assertJsonPath('content', fn (string $html): bool => str_contains($html, $content));
})->with('page rendering themes')->with([
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
})->with('page rendering themes');

test('Arcturus staff pages render multiple staff members with lazy loading prevention', function (string $theme) {
    setSetting('theme', $theme);
    $rankName = DB::table('permissions')->where('id', 7)->value('rank_name');
    User::factory()->create(['username' => 'HeadAdmin', 'rank' => 7]);
    User::factory()->create(['username' => 'SecondAdmin', 'rank' => 7]);

    $this->actingAs(User::factory()->create())->get(route('staff.index'))
        ->assertSee([$rankName, 'HeadAdmin', 'SecondAdmin'])
        ->assertSee('href="' . route('home.show', 'HeadAdmin') . '"', false)
        ->assertSee('href="' . route('home.show', 'SecondAdmin') . '"', false);
})->with('page rendering themes');

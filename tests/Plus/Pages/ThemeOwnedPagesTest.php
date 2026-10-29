<?php

use App\Emulator\Contracts\BadgeRepository;
use App\Emulator\Contracts\CurrencyRepository;
use App\Enums\CurrencyTypes;
use App\Models\Articles\WebsiteArticle;
use App\Models\Community\Staff\WebsiteOpenPosition;
use App\Models\Community\Teams\WebsiteTeam;
use App\Models\Help\WebsiteHelpCenterCategory;
use App\Models\Home\HomeItem;
use App\Models\Miscellaneous\WebsiteMaintenanceTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

dataset('plus owned themes', ['atom theme' => 'atom', 'dusk theme' => 'dusk']);

beforeEach(function (): void {
    installHotel();
    setSetting('start_duckets', '0');
    setSetting('start_diamonds', '0');
    setSetting('start_points', '0');
    config(['theme.parent' => null]);

    DB::table('roles')->insert([
        ['id' => 1, 'slug' => 'default', 'name' => 'Member', 'description' => '', 'weight' => 1, 'security_level' => 1, 'badge_code' => '', 'is_staff' => false],
        ['id' => 70, 'slug' => 'owner', 'name' => 'Hotel Owners', 'description' => 'Keep the Plus hotel running.', 'weight' => 700, 'security_level' => 7, 'badge_code' => 'ADM', 'is_staff' => true],
    ]);

    $this->plusPresentationEmptyPath = sys_get_temp_dir() . '/plus-presentation-' . bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->plusPresentationEmptyPath);
});

afterEach(function (): void {
    File::deleteDirectory($this->plusPresentationEmptyPath);
});

function isolatePlusOwnedTheme(string $theme, string $emptyPath): void
{
    $other = $theme === 'atom' ? 'dusk' : 'atom';

    View::getFinder()->setPaths([resource_path("themes/{$theme}/views")]);
    View::getFinder()->replaceNamespace("theme-{$other}", [$emptyPath]);
    View::getFinder()->flush();
}

test('Plus teams render from the selected theme with the other theme and root hidden', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    User::factory()->create(['username' => 'EventHost', 'team_id' => $team->id]);

    $this->actingAs(User::factory()->create())->get(route('teams.index'))
        ->assertSee(['Events Team', 'Host events for the community.', 'EventHost']);
})->with('plus owned themes');

test('Plus staff applications render the Plus role permission through the selected theme', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $position = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => 70, 'description' => 'Help us moderate the hotel.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);
    $applicant = User::factory()->create(['username' => 'ApplicantOne']);

    $this->actingAs($applicant)->get(route('staff-applications.index'))
        ->assertSee(['Help us moderate the hotel.', 'href="' . route('staff-applications.show', $position) . '"'], false);
    $this->actingAs($applicant)->get(route('staff-applications.show', $position))
        ->assertSee(['Hotel Owners', 'name="username" value="ApplicantOne"', 'name="content"'], false);
})->with('plus owned themes');

test('Plus maintenance renders a task author from the selected theme', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    setSetting('maintenance_enabled', '1');
    setSetting('maintenance_message', 'Back before lunch');
    $engineer = User::factory()->create(['username' => 'TaskAuthor']);
    WebsiteMaintenanceTask::create(['user_id' => $engineer->id, 'task' => 'Upgrading the database']);

    $this->get(route('maintenance.show'))
        ->assertSee(['Maintenance break!', 'Upgrading the database', 'TaskAuthor', 'Back before lunch']);
})->with('plus owned themes');

test('Plus bans render a user-targeted ban from the selected theme', function (string $theme): void {
    $this->freezeTime();
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $member = User::factory()->create();
    DB::table('bans')->insert(['bantype' => 'user', 'value' => $member->username, 'reason' => 'Spamming the lobby', 'expire' => now()->addHour(), 'added_by' => 'system', 'added_date' => now()]);

    $this->actingAs($member)->get(route('banned.show'))
        ->assertSee(['Ban type:</strong> user', 'Spamming the lobby'], false);
})->with('plus owned themes');

test('Plus ticket show pages render a member-owned ticket from the selected theme', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $owner = User::factory()->create(['username' => 'TicketOwner']);
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?']);
    $ticket = $owner->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->actingAs($owner)->get(route('help-center.ticket.show', $ticket))
        ->assertSee(['Broken door', 'The door is jammed.', 'Submit reply']);
})->with('plus owned themes');

test('Plus dashboards render online friends, currencies, articles, and referral data from the selected theme', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $member = User::factory()->create(['credits' => 0, 'referral_code' => 'MYREF123']);
    $friend = User::factory()->create(['username' => 'OnlineFriend', 'online' => true]);
    $offline = User::factory()->create(['username' => 'OfflineFriend', 'online' => false]);
    DB::table('messenger_friendships')->insert([
        ['user_one_id' => $member->id, 'user_two_id' => $friend->id, 'relationship' => 0],
        ['user_one_id' => $member->id, 'user_two_id' => $offline->id, 'relationship' => 0],
    ]);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Credits, 24680);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Duckets, 13579);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Diamonds, 8642);
    WebsiteArticle::create(['user_id' => $friend->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome!</p>', 'image' => 'articles/opening.png']);

    $this->actingAs($member->refresh())->get(route('me.show'))
        ->assertSee(['Grand opening', 'OnlineFriend', '24680', '13579', '8642', 'name="referral" value="http://localhost/register/MYREF123"'], false)
        ->assertDontSee('OfflineFriend');
})->with('plus owned themes');

test('Plus leaderboards render native currencies and exclude the single staff member', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $member = User::factory()->create(['username' => 'RichestMember', 'credits' => 0]);
    $staff = User::factory()->create(['username' => 'StaffMillionaire', 'rank' => 7, 'credits' => 999999]);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Credits, 987654);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Duckets, 54321);
    app(CurrencyRepository::class)->give($member, CurrencyTypes::Diamonds, 3210);
    app(CurrencyRepository::class)->give($staff, CurrencyTypes::Duckets, 999999);
    app(CurrencyRepository::class)->give($staff, CurrencyTypes::Diamonds, 999999);
    DB::table('user_statistics')->where('id', $member->id)->update(['OnlineTime' => 7200, 'Respect' => 87, 'AchievementScore' => 654]);

    $this->actingAs(User::factory()->create())->get(route('leaderboard.index'))
        ->assertSee(['RichestMember', '987654 Credits', '54321 Duckets', '3210 Diamonds', '2 Hours online', '87 Respect received', '654 Achievement points'])
        ->assertDontSee('StaffMillionaire');
})->with('plus owned themes');

test('Plus staff pages render one staff member from the selected theme', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $staff = User::factory()->create(['username' => 'HeadAdmin', 'rank' => 7]);

    $this->actingAs(User::factory()->create())->get(route('staff.index'))
        ->assertSee(['Hotel Owners', 'Keep the Plus hotel running.', 'HeadAdmin'])
        ->assertSee('href="' . route('home.show', $staff->username) . '"', false);
})->with('plus owned themes');

test('Plus Nitro renders the emulator-issued SSO ticket from the selected theme', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get(route('nitro-client'));
    $ticket = $member->fresh()->auth_ticket;

    expect($ticket)->toBeString()->not->toBeEmpty();
    $response->assertSee('index.html?sso=' . $ticket, false);
    $this->assertDatabaseHas('users', ['id' => $member->id, 'auth_ticket' => $ticket, 'auth_ticket_exchanged' => false]);
})->with('plus owned themes');

test('Plus home widgets render native friend, badge, room, and rating data through JSON', function (string $theme, string $widget, string $content): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
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

    $response = $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
        ->assertJsonPath('name', $widget);

    expect($response->json('content'))->toBeString()->toContain($content);
})->with('plus owned themes')->with([
    'my friends' => ['My Friends', 'BestFriend'],
    'my badges' => ['My Badges', 'ACH_Test1.gif'],
    'my rooms' => ['My Rooms', 'Cozy Lounge'],
    'my rating' => ['My Rating', 'Average rating: 5.0'],
]);

test('Plus my-groups widget still returns no content through JSON', function (string $theme): void {
    isolatePlusOwnedTheme($theme, $this->plusPresentationEmptyPath);
    setSetting('theme', $theme);
    $owner = User::factory()->create();
    $item = HomeItem::create(['name' => 'My Groups', 'type' => 'w', 'currency_type' => -1, 'price' => 0, 'image' => 'widget.png', 'enabled' => true, 'order' => 0]);
    $placed = $owner->homeItems()->create(['home_item_id' => $item->id, 'placed' => true, 'theme' => 'default']);

    $this->getJson(route('home.widget-content', [$owner->username, $placed->id]))
        ->assertJsonPath('widget_type', 'my-groups')
        ->assertJsonPath('content', null);
})->with('plus owned themes');

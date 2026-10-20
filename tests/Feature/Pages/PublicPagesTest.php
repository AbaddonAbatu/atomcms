<?php

use App\Models\Community\Teams\WebsiteTeam;
use App\Models\User;

beforeEach(function () {
    installHotel();
});

test('public community pages render', function (string $route) {
    $this->get(route($route))->assertOk();
})->with([
    'articles index' => 'article.index',
    'rules' => 'help-center.rules.index',
]);

test('public responses include browser security headers', function () {
    $this->get(route('article.index'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'")
        ->assertHeader('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('secure production responses enable transport security', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->withHeader('X-Forwarded-Proto', 'https')
        ->get(route('article.index'))
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('authenticated community pages render', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertOk();
})->with([
    'photos' => 'photos.index',
    'staff' => 'staff.index',
    'teams' => 'teams.index',
    'staff applications' => 'staff-applications.index',
    'team applications' => 'team-applications.index',
    'help center' => 'help-center.index',
    'ticket create' => 'help-center.ticket.create',
]);

test('a user home page renders', function () {
    $user = User::factory()->create();
    $subject = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home.show', $subject->username))
        ->assertOk();
});

test('teams use the staff layout with team descriptions', function (string $theme) {
    setSetting('theme', $theme);
    setSetting('enable_caching', '0');
    $team = WebsiteTeam::create([
        'rank_name' => 'Events Crew',
        'badge' => 'EVT',
        'hidden_rank' => false,
        'staff_color' => '#123456',
        'staff_background' => '',
        'job_description' => 'Hosts community events.',
    ]);
    $member = User::factory()->create(['team_id' => $team->id]);

    $this->actingAs($member)->get(route('teams.index'))
        ->assertSee('col-span-12 lg:col-span-9 lg:w-[96%]', false)
        ->assertSee('grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3', false)
        ->assertSee(['Events Crew', 'Hosts community events.', $member->username, 'Apply for teams'])
        ->assertSee(__('Our community teams bring players together to host events, help others, and make :hotel a fun place to be.', ['hotel' => setting('hotel_name')]))
        ->assertSee(route('team-applications.index'), false)
        ->assertDontSee('Hide empty teams');
})->with(['atom', 'dusk']);

test('empty teams keep their staff layout and application sidebar', function (string $theme) {
    setSetting('theme', $theme);
    setSetting('enable_caching', '0');
    WebsiteTeam::create([
        'rank_name' => 'Empty Crew',
        'badge' => '',
        'hidden_rank' => false,
        'staff_color' => '#123456',
        'staff_background' => '',
        'job_description' => 'Waiting for members.',
    ]);

    $this->actingAs(User::factory()->create())->get(route('teams.index'))
        ->assertSee(['Empty Crew', 'We currently have no members in this team', 'How to join a team'])
        ->assertSee('col-span-12 lg:col-span-3 lg:w-[110%] space-y-4 lg:-ml-[32px]', false);
})->with(['atom', 'dusk']);

test('switching language stores the locale', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('me.show'))
        ->get(route('language.select', 'en'))
        ->assertRedirect();

    expect(session('locale'))->toBe('en');
});

<?php

use App\Models\Articles\WebsiteArticle;
use App\Models\Miscellaneous\WebsiteLanguage;
use App\Models\User;
use App\Services\Articles\ArticleService;
use App\Services\Auth\AuthPageService;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\LoginViewResponse;

/*
|--------------------------------------------------------------------------
| Data the public layouts and pages receive instead of querying in Blade
|--------------------------------------------------------------------------
|
| The language flags, the Atom article author link and the auth page sidebar
| are prepared in Core and handed to the templates. Set the theme once per
| test: resolved views are memoized.
*/

dataset('shared data selectors per theme', [
    'atom theme' => ['atom', 2],
    'dusk theme' => ['dusk', 1],
]);

dataset('shared data themes', ['atom theme' => 'atom', 'dusk theme' => 'dusk']);

dataset('shared data registration pages', [
    'registration' => [null],
    'referral registration' => ['REFCODE1'],
]);

beforeEach(function () {
    installHotel();
});

/**
 * Newest first: the ids of the authored articles and visible photos a page
 * should show, after seeding an authorless article that is the newest of all.
 *
 * @return array{articles: list<int>, photos: list<int>}
 */
function seedAuthPageSidebar(User $author, int $articles, int $photos): array
{
    $articleIds = collect(range(1, $articles))->map(fn (int $number): int => WebsiteArticle::create([
        'user_id' => $author->id,
        'title' => "Sidebar news {$number}",
        'short_story' => 'Short.',
        'full_story' => '<p>Story.</p>',
        'image' => 'articles/news.png',
    ])->id);

    WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless sidebar news', 'short_story' => 'Short.', 'full_story' => '<p>Ghost.</p>', 'image' => 'a.png']);

    $photoIds = collect(range(1, $photos))->map(fn (int $number): int => DB::table('camera_web')->insertGetId([
        'user_id' => $author->id, 'room_id' => 0, 'timestamp' => time(), 'url' => "/camera/sidebar-{$number}.png", 'visible' => true,
    ]));

    return [
        'articles' => $articleIds->reverse()->values()->all(),
        'photos' => $photoIds->reverse()->values()->all(),
    ];
}

test('every public page links a flag for each website language in table order', function (string $theme, int $selectors, string $viewer) {
    setSetting('theme', $theme);
    WebsiteLanguage::query()->delete();
    foreach (['se' => 'Swedish', 'en' => 'English', 'da' => 'Danish'] as $code => $language) {
        WebsiteLanguage::create(['country_code' => $code, 'language' => $language]);
    }
    DB::enableQueryLog();

    $response = $viewer === 'member'
        ? $this->actingAs(User::factory()->create())->get(route('article.index'))
        : $this->get(route('welcome'));

    $links = array_map(fn (string $code): string => 'href="' . route('language.select', $code) . '"', ['se', 'en', 'da']);
    $response->assertOk()->assertSeeInOrder($links, false);
    foreach (['se', 'en', 'da'] as $position => $code) {
        expect(substr_count($response->getContent(), $links[$position]))->toBe($selectors)
            ->and(substr_count($response->getContent(), "src=\"/assets/images/icons/flags/{$code}.png\" alt=\"{$code}\""))->toBe($selectors);
    }

    $languageListQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => preg_match('/^select \* from [`"]website_languages[`"]$/', $query['query']) === 1);
    expect($languageListQueries)->toHaveCount(1);
})->with('shared data selectors per theme')->with(['guest', 'member']);

test('atom links the avatar of an authorless article to the first user and hands that user to the view', function () {
    setSetting('theme', 'atom');
    $first = User::factory()->create(['username' => 'FirstUser']);
    $viewer = User::factory()->create(['username' => 'ViewerTwo']);
    $article = WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless news', 'short_story' => 'No author', 'full_story' => '<p>Ghost story.</p>', 'image' => 'a.png']);

    $this->actingAs($viewer)->get(route('article.show', $article->slug))
        ->assertOk()
        ->assertSee('href="' . route('home.show', $first->username) . '"', false)
        ->assertViewHas('authorHome', fn (User $authorHome): bool => $authorHome->is($first));
});

test('the article author is the avatar link target and the first user is only queried without one', function () {
    $first = User::factory()->create();
    $author = User::factory()->create();
    $service = app(ArticleService::class);
    $attributes = ['short_story' => 'Short.', 'full_story' => '<p>Story.</p>', 'image' => 'a.png'];
    $authored = $service->loadForDisplay(WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Authored news', ...$attributes]));
    $authorless = $service->loadForDisplay(WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless news', ...$attributes]));

    DB::enableQueryLog();
    $authoredHome = $service->authorHome($authored);
    $queriesForAuthored = DB::getQueryLog();
    $authorlessHome = $service->authorHome($authorless);

    expect($authoredHome?->is($author))->toBeTrue()
        ->and($queriesForAuthored)->toBeEmpty()
        ->and($authorlessHome?->is($first))->toBeTrue()
        ->and(DB::getQueryLog())->toHaveCount(1);
});

test('the registration pages share the latest authored articles and photos', function (string $theme, ?string $referralCode) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['referral_code' => $referralCode]);
    $expected = seedAuthPageSidebar($author, 5, 3);
    $uri = $referralCode === null ? route('register') : route('register.referral', $referralCode);

    $this->get($uri)
        ->assertOk()
        ->assertViewHas('referral_code', fn (?string $code): bool => $code === $referralCode)
        ->assertViewHas('articles', fn (Collection $articles): bool => array_slice($expected['articles'], 0, 4) === $articles->pluck('id')->all())
        ->assertViewHas('photos', fn (Collection $photos): bool => array_slice($expected['photos'], 0, 2) === $photos->pluck('id')->all());
})->with('shared data themes')->with('shared data registration pages');

test('the login payload carries the latest four authored articles and four photos', function () {
    $expected = seedAuthPageSidebar(User::factory()->create(), 6, 5);

    $payload = app(AuthPageService::class)->loginData();
    $view = app(LoginViewResponse::class)->toResponse(Request::create('/login'));

    expect($payload['articles']->pluck('id')->all())->toBe(array_slice($expected['articles'], 0, 4))
        ->and($payload['photos']->pluck('id')->all())->toBe(array_slice($expected['photos'], 0, 4))
        ->and($view->name())->toBe('theme-' . app(ThemeService::class)->selectedId() . '::auth.login')
        ->and($view->getData()['articles']->pluck('id')->all())->toBe(array_slice($expected['articles'], 0, 4))
        ->and($view->getData()['photos']->pluck('id')->all())->toBe(array_slice($expected['photos'], 0, 4));
});

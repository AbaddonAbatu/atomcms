<?php

use App\Models\Articles\WebsiteArticle;
use App\Models\User;
use App\Services\Auth\AuthPageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| PlusEMU data the auth pages receive from Core
|--------------------------------------------------------------------------
|
| Plus has no camera feature, so the shared auth page data carries no photos
| there. Set the theme once per test: views are memoized.
*/

dataset('plus shared data themes', ['atom theme' => 'atom', 'dusk theme' => 'dusk']);

beforeEach(function () {
    installHotel();
    setSetting('start_duckets', '0');
    setSetting('start_diamonds', '0');
    setSetting('start_points', '0');

    DB::table('roles')->insert(['id' => 1, 'slug' => 'default', 'name' => 'Member', 'description' => '', 'weight' => 1, 'security_level' => 1, 'badge_code' => '', 'is_staff' => false]);
});

/**
 * Seed authored articles and one newer authorless article.
 *
 * @return list<int> The authored article ids, newest first.
 */
function seedPlusAuthPageArticles(User $author, int $count): array
{
    $ids = collect(range(1, $count))->map(fn (int $number): int => WebsiteArticle::create([
        'user_id' => $author->id,
        'title' => "Plus sidebar news {$number}",
        'short_story' => 'Short.',
        'full_story' => '<p>Story.</p>',
        'image' => 'articles/news.png',
    ])->id);

    WebsiteArticle::create(['user_id' => null, 'title' => 'Authorless sidebar news', 'short_story' => 'Short.', 'full_story' => '<p>Ghost.</p>', 'image' => 'a.png']);

    return $ids->reverse()->values()->all();
}

test('the referral registration page renders on Plus with the code, the latest articles and no photos', function (string $theme) {
    setSetting('theme', $theme);
    $author = User::factory()->create(['referral_code' => 'PLUSREF1']);
    $articleIds = seedPlusAuthPageArticles($author, 5);

    $this->get(route('register.referral', 'PLUSREF1'))
        ->assertOk()
        ->assertSee('name="referral_code" value="PLUSREF1"', false)
        ->assertViewHas('referral_code', 'PLUSREF1')
        ->assertViewHas('articles', fn (Collection $articles): bool => array_slice($articleIds, 0, 4) === $articles->pluck('id')->all())
        ->assertViewHas('photos', fn (Collection $photos): bool => $photos->isEmpty());
})->with('plus shared data themes');

test('the Plus login payload carries four articles and no photos', function () {
    $articleIds = seedPlusAuthPageArticles(User::factory()->create(), 6);

    $payload = app(AuthPageService::class)->loginData();

    expect($payload['articles']->pluck('id')->all())->toBe(array_slice($articleIds, 0, 4))
        ->and($payload['photos'])->toBeEmpty();
});

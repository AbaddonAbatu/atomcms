<?php

use App\Models\Articles\WebsiteArticle;
use App\Models\User;
use Livewire\Livewire;

/*
 * Public Livewire updates run through the global theme middleware, so each
 * theme's own article component views render the round trip. Livewire::test()
 * skips HTTP middleware and cannot prove this.
 */
beforeEach(function (): void {
    installHotel();
    setSetting('force_staff_2fa', '0');
    setSetting('maintenance_enabled', '0');
});

function themedArticleSnapshot(string $html, string $component): string
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

test('the article Livewire update endpoint re-renders each theme\'s own component markup', function (string $theme, array $ownMarkers, array $foreignMarkers) {
    setSetting('theme', $theme);
    $article = WebsiteArticle::create(['user_id' => User::factory()->create()->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $page = $this->actingAs(User::factory()->create())->get(route('article.show', $article->slug))->assertOk();

    $comments = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => themedArticleSnapshot($page->getContent(), 'article-comments'),
            'updates' => ['comment' => "Posted through {$theme}"],
            'calls' => [['method' => 'postComment', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();

    $reactions = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => themedArticleSnapshot($page->getContent(), 'article-reactions'),
            'updates' => [],
            'calls' => [['method' => 'toggleReaction', 'params' => ['like']]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();

    $html = $comments->json('components.0.effects.html') . $reactions->json('components.0.effects.html');

    expect($html)->toContain("Posted through {$theme}");
    expect($article->comments()->value('comment'))->toBe("Posted through {$theme}");
    expect($article->reactions()->where('reaction', 'like')->where('active', true)->count())->toBe(1);

    foreach ($ownMarkers as $marker) {
        expect($html)->toContain($marker);
    }

    foreach ($foreignMarkers as $marker) {
        expect($html)->not->toContain($marker);
    }
})->with([
    'atom' => ['atom', ['bg-[#f5f5f5]', 'p-2 bg-gray-100 dark:bg-gray-900'], ['bg-[#21242e]', 'p-2 bg-gray-900 text-gray-100']],
    'dusk' => ['dusk', ['bg-[#21242e]', 'p-2 bg-gray-900 text-gray-100'], ['bg-[#f5f5f5]', 'p-2 bg-gray-100 dark:bg-gray-900']],
]);

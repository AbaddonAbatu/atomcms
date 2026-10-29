<?php

use App\Emulator\Contracts\FurnitureRepository;
use App\Livewire\ArticleComments;
use App\Livewire\ArticleReactions;
use App\Models\Articles\WebsiteArticle;
use App\Models\Community\RareValue\WebsiteRareValue;
use App\Models\Community\RareValue\WebsiteRareValueCategory;
use App\Models\Community\Staff\WebsiteOpenPosition;
use App\Models\Community\Teams\WebsiteTeam;
use App\Models\Help\WebsiteHelpCenterCategory;
use App\Models\Miscellaneous\WebsiteMaintenanceTask;
use App\Models\Miscellaneous\WebsiteSetting;
use App\Models\PasswordResetToken;
use App\Models\Shop\WebsiteShopCategory;
use App\Models\User;
use App\Models\User\Ban;
use App\Services\SettingsService;
use App\Services\ThemeService;
use App\Services\ThemeValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

beforeEach(function (): void {
    installHotel();
    $this->atomPresentationEmptyPath = sys_get_temp_dir() . '/atom-presentation-' . bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->atomPresentationEmptyPath);

    View::getFinder()->setPaths([resource_path('themes/atom/views')]);
    View::getFinder()->replaceNamespace('theme-dusk', [$this->atomPresentationEmptyPath]);
    View::getFinder()->flush();
});

afterEach(function (): void {
    File::deleteDirectory($this->atomPresentationEmptyPath);
});

test('Atom renders Core SEO metadata on a public page', function (): void {
    setSetting('theme', 'atom');
    setSetting('hotel_name', 'Atom SEO Hotel');

    $this->get(route('welcome'))
        ->assertSee('<meta property="og:site_name" content="Atom SEO Hotel">', false);
});

test('Atom renders the forgot-password page from its own view root', function (): void {
    setSetting('theme', 'atom');

    $this->get(route('forgot.password.get'))
        ->assertSee(['Forgot password', 'name="mail"'], false);
});

test('Atom renders a valid reset form from its own view root', function (): void {
    setSetting('theme', 'atom');
    $user = User::factory()->create();
    PasswordResetToken::create(['email' => $user->mail, 'token' => PasswordResetToken::hashToken('atom-token')]);

    $this->get(route('reset.password.get', 'atom-token'))
        ->assertSee(['Reset Password', 'action="http://localhost/reset-password/atom-token"', 'name="password_confirmation"'], false);
});

test('Atom renders the password-confirmation page from its own view root', function (): void {
    setSetting('theme', 'atom');

    $this->actingAs(User::factory()->create())
        ->get(route('password.confirm'))
        ->assertSee(['Confirm your password', 'name="password"'], false);
});

test('Atom renders the two-factor challenge with a pending login session', function (): void {
    setSetting('theme', 'atom');
    $user = User::factory()->create();

    $this->withSession(['login.id' => $user->id])
        ->get(route('two-factor.login'))
        ->assertSee(['Two-Factor Authentication', 'name="code"', 'name="recovery_code"'], false);
});

test('Atom renders maintenance tasks and its guest staff-login form', function (): void {
    setSetting('theme', 'atom');
    setSetting('maintenance_enabled', '1');
    setSetting('maintenance_message', 'Back before lunch');
    $engineer = User::factory()->create();
    WebsiteMaintenanceTask::create(['user_id' => $engineer->id, 'task' => 'Upgrading the database']);

    $this->get(route('maintenance.show'))
        ->assertSee(['Maintenance break!', 'Upgrading the database', 'Back before lunch', 'Staff login']);
});

test('Atom renders an active member ban with its details and themed illustration', function (): void {
    setSetting('theme', 'atom');
    $member = User::factory()->create();
    Ban::create(['user_id' => $member->id, 'ip' => '', 'machine_id' => '', 'user_staff_id' => $member->id, 'timestamp' => time(), 'ban_expire' => time() + 3600, 'ban_reason' => 'Spamming the lobby', 'type' => 'account']);

    $this->actingAs($member)->get(route('banned.show'))
        ->assertSee(['Ban type:</strong> account', 'Spamming the lobby'], false)
        ->assertDontSee('/assets/images/angry_frank.png', false);
});

test('Atom renders teams with their members', function (): void {
    setSetting('theme', 'atom');
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    User::factory()->create(['username' => 'EventHost', 'team_id' => $team->id]);

    $this->actingAs(User::factory()->create())->get(route('teams.index'))
        ->assertSee(['Events Team', 'Host events for the community.', 'EventHost']);
});

test('Atom renders staff applications and the application form', function (): void {
    setSetting('theme', 'atom');
    $position = WebsiteOpenPosition::create(['position_kind' => 'rank', 'permission_id' => DB::table('permissions')->min('id'), 'description' => 'Help us moderate the hotel.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);
    $applicant = User::factory()->create(['username' => 'ApplicantOne']);

    $this->actingAs($applicant)->get(route('staff-applications.index'))
        ->assertSee(['Help us moderate the hotel.', 'href="' . route('staff-applications.show', $position) . '"'], false);
    $this->actingAs($applicant)->get(route('staff-applications.show', $position))
        ->assertSee(['name="username" value="ApplicantOne"', 'name="content"'], false);
});

test('Atom renders team applications and the application form', function (): void {
    setSetting('theme', 'atom');
    $team = WebsiteTeam::create(['rank_name' => 'Events Team', 'badge' => '', 'job_description' => 'Host events for the community.', 'staff_color' => '#ffffff', 'staff_background' => '']);
    $position = WebsiteOpenPosition::create(['position_kind' => 'team', 'team_id' => $team->id, 'description' => 'Join the events crew.', 'apply_from' => now()->subDay(), 'apply_to' => now()->addDay()]);
    $applicant = User::factory()->create(['username' => 'ApplicantOne']);

    $this->actingAs($applicant)->get(route('team-applications.index'))
        ->assertSee(['Events Team', 'Join the events crew.', 'href="' . route('team-applications.show', $position) . '"'], false);
    $this->actingAs($applicant)->get(route('team-applications.show', $position))
        ->assertSee(['name="username" value="ApplicantOne"', 'name="content"'], false);
});

test('Atom renders ticket index, show, and edit pages for their permitted users', function (): void {
    setSetting('theme', 'atom');
    setSetting('force_staff_2fa', '0');
    $owner = User::factory()->create(['username' => 'TicketOwner']);
    $category = WebsiteHelpCenterCategory::create(['name' => 'Account help', 'content' => 'Trouble signing in?']);
    $ticket = $owner->tickets()->create(['category_id' => $category->id, 'title' => 'Broken door', 'content' => 'The door is jammed.', 'open' => true]);

    $this->actingAs($owner)->get(route('help-center.ticket.show', $ticket))
        ->assertSee(['Broken door', 'The door is jammed.']);
    $this->actingAs($owner)->get(route('help-center.ticket.edit', $ticket))
        ->assertSee(['value="Broken door"', 'The door is jammed.', 'name="category_id"'], false);
    $staff = User::factory()->create(['rank' => 7]);
    auth()->forgetGuards();
    $this->flushSession()->actingAs($staff)->get(route('help-center.ticket.index'))
        ->assertSee(['Broken door', 'TicketOwner']);
});

test('Atom renders a rare value page with its holder count', function (): void {
    setSetting('theme', 'atom');
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    $rare = WebsiteRareValue::create(['category_id' => $category->id, 'item_id' => 4242, 'name' => 'Blue Dragon Lamp', 'furniture_icon' => 'dragonlamp.png']);
    app(FurnitureRepository::class)->grant(User::factory()->create(['username' => 'LampCollector']), 4242, 3);

    $this->actingAs(User::factory()->create())->get(route('values.value', $rare))
        ->assertSee(['Blue Dragon Lamp', 'LampCollector', '3 owned']);
});

test('Atom renders the rare-values index through its rare-card component', function (): void {
    setSetting('theme', 'atom');
    $category = WebsiteRareValueCategory::create(['name' => 'Dragons', 'badge' => 'DRG', 'priority' => 1]);
    WebsiteRareValue::create(['category_id' => $category->id, 'name' => 'Blue Dragon Lamp', 'credit_value' => '250', 'currency_value' => '5', 'currency_type' => 'diamonds', 'furniture_icon' => 'dragonlamp.png']);

    $this->actingAs(User::factory()->create())->get(route('values.index'))
        ->assertSee(['Dragons', 'Blue Dragon Lamp', 'name="search"'], false);
});

test('Atom renders an empty article index through its filler card', function (): void {
    setSetting('theme', 'atom');

    $this->get(route('article.index'))
        ->assertSee('No published articles');
});

test('Atom renders photos with its Fancybox assets', function (): void {
    setSetting('theme', 'atom');
    $photographer = User::factory()->create(['username' => 'Photographer']);
    DB::table('camera_web')->insert(['user_id' => $photographer->id, 'room_id' => 0, 'timestamp' => time(), 'url' => '/camera/lobby.png', 'visible' => true]);

    $this->actingAs(User::factory()->create())->get(route('photos.index'))
        ->assertSee(['data-fancybox="gallery"', 'Photographer'], false)
        ->assertSee('fancybox.umd.js', false);
});

test('Atom renders Nitro and Flash client pages with themed client controls', function (): void {
    setSetting('theme', 'atom');
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('nitro-client'))
        ->assertSee(['index.html?sso=', 'bg-[#eeb425]'], false);
    $this->actingAs($member)->get(route('flash-client'))
        ->assertSee(['"sso.ticket":', 'You are nearly in Habbo!'], false);
});

test('Atom renders shop packages with its modal wrapper', function (): void {
    setSetting('theme', 'atom');
    $category = WebsiteShopCategory::create(['name' => 'Credit packs', 'slug' => 'credit-packs', 'is_active' => true]);
    makePackage(['website_shop_category_id' => $category->id]);

    $this->actingAs(User::factory()->create())->get(route('shop.index'))
        ->assertSee(['Starter Bundle', '100 Credits', 'x-data="{ open: false }"'], false)
        ->assertSee('rounded bg-white shadow-sm dark:bg-gray-800', false);
});

test('Atom renders flash toasts and the footer credits modal without Dusk presentation classes', function (): void {
    setSetting('theme', 'atom');

    $this->withSession(['message' => 'Atom toast'])->get(route('welcome'))
        ->assertSee(['toastHub', 'Atom toast', 'atom-credits', 'rounded bg-white shadow-sm dark:bg-gray-800'], false)
        ->assertSee('class="pointer-events-none fixed right-4', false)
        ->assertDontSee('dark pointer-events-none', false)
        ->assertDontSee(['bg-[#21242e]', 'bg-[#2b303c]'], false);
});

test('Atom renders an article page with both Livewire components and its light comment card', function (): void {
    setSetting('theme', 'atom');
    $author = User::factory()->create(['username' => 'ArticleAuthor']);
    $reader = User::factory()->create(['username' => 'ReaderOne']);
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $article->comments()->create(['user_id' => $reader->id, 'comment' => 'Congratulations on the launch!']);
    $article->reactions()->create(['user_id' => $reader->id, 'reaction' => 'like', 'active' => true]);

    $this->get(route('article.show', $article->slug))
        ->assertSee(['Grand opening', 'Congratulations on the launch!', 'wire:click="toggleReaction(\'like\')"', 'bg-[#f5f5f5]'], false)
        ->assertDontSee(['bg-[#21242e]', 'bg-[#2b303c]'], false);
});

test('Atom article Livewire views keep Atom styling when the theme setting row is absent', function (): void {
    // The theme-owned Livewire views keep the selected theme when its setting row is absent.
    WebsiteSetting::query()->where('key', 'theme')->delete();
    app(SettingsService::class)->refresh();
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $article->comments()->create(['user_id' => $reader->id, 'comment' => 'Congratulations on the launch!']);

    $this->get(route('article.show', $article->slug))
        ->assertSee(['Congratulations on the launch!', 'bg-[#f5f5f5]', 'bg-gray-100 dark:bg-gray-900'], false)
        ->assertDontSee(['bg-[#21242e]', 'p-2 bg-gray-900 text-gray-100'], false);
});

test('Atom Livewire comments persist and re-render a valid comment', function (): void {
    setSetting('theme', 'atom');
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);

    Livewire::actingAs($reader)->test(ArticleComments::class, ['article' => $article])
        ->set('comment', 'Posted from Atom')
        ->call('postComment')
        ->assertSee('Posted from Atom');

    $this->assertDatabaseHas('website_article_comments', ['article_id' => $article->id, 'user_id' => $reader->id, 'comment' => 'Posted from Atom']);
});

test('Atom Livewire comments allow the owner to delete and re-render without the comment', function (): void {
    setSetting('theme', 'atom');
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);
    $comment = $article->comments()->create(['user_id' => $reader->id, 'comment' => 'Owned by the reader']);

    Livewire::actingAs($reader)->test(ArticleComments::class, ['article' => $article])
        ->call('deleteComment', $comment->id)
        ->assertDontSee('Owned by the reader');

    $this->assertDatabaseMissing('website_article_comments', ['id' => $comment->id]);
});

test('Atom Livewire reactions persist and open and close their modal', function (): void {
    setSetting('theme', 'atom');
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $article = WebsiteArticle::create(['user_id' => $author->id, 'title' => 'Grand opening', 'short_story' => 'We are open!', 'full_story' => '<p>Welcome.</p>', 'image' => 'articles/opening.png', 'can_comment' => true]);

    $component = Livewire::actingAs($reader)->test(ArticleReactions::class, ['article' => $article]);
    $component->call('openModal')->assertSee('Insert Reaction');
    $component->call('closeModal')->assertDontSee('Insert Reaction');
    $component->call('toggleReaction', 'like')->assertSee('1');

    $this->assertDatabaseHas('website_article_reactions', ['article_id' => $article->id, 'user_id' => $reader->id, 'reaction' => 'like', 'active' => true]);
});

test('Atom keeps every view component qualified and every required view inside its own root', function (): void {
    $viewRoot = resource_path('themes/atom/views');
    $componentRoot = $viewRoot . DIRECTORY_SEPARATOR . 'components';
    $violations = [];

    foreach (File::allFiles($viewRoot) as $file) {
        $contents = File::get($file->getPathname());
        if (str_contains($contents, "setting('theme'") || str_contains($contents, 'setting("theme"')) {
            $violations[] = $file->getPathname() . ': reads setting(theme)';
        }

        preg_match_all('/<\\/?x-([A-Za-z0-9_.:-]+)/', $contents, $matches);
        foreach ($matches[1] as $tag) {
            if (in_array($tag, ['app-layout', 'turnstile', 'turnstile.scripts'], true) || str_starts_with($tag, 'slot')) {
                continue;
            }

            if (str_starts_with($tag, 'core::')) {
                $root = resource_path('views/core/components');
                $name = substr($tag, strlen('core::'));
            } elseif (str_starts_with($tag, 'theme-atom::')) {
                $root = $componentRoot;
                $name = substr($tag, strlen('theme-atom::'));
            } else {
                $violations[] = $file->getPathname() . ': ' . $tag;

                continue;
            }

            $component = $root . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.blade.php';
            if (! File::isFile($component)) {
                $violations[] = $file->getPathname() . ': missing ' . $component;
            }
        }
    }

    expect($violations)->toBe([]);
});

test('Atom resolves every required presentation view inside its own namespace', function (): void {
    setSetting('theme', 'atom');
    $root = realpath(resource_path('themes/atom/views'));
    $service = app(ThemeService::class);

    foreach (ThemeValidator::REQUIRED_VIEWS as $key) {
        $path = realpath($service->view($key)->getPath());
        $inside = PHP_OS_FAMILY === 'Windows'
            ? strncasecmp($path, $root . DIRECTORY_SEPARATOR, strlen($root . DIRECTORY_SEPARATOR)) === 0
            : str_starts_with($path, $root . DIRECTORY_SEPARATOR);

        expect($inside)->toBeTrue();
    }
});

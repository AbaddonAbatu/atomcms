<?php

use App\Models\Miscellaneous\WebsiteInstallation;
use App\Models\Miscellaneous\WebsiteSetting;
use App\Services\InstallationService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;

beforeEach(function (): void {
    $this->installationOriginalPublicPath = public_path();
    $this->installationOriginalStoragePath = storage_path();
    $this->installationTemporaryPath = sys_get_temp_dir() . '/atom-installation-' . bin2hex(random_bytes(12));
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath($this->installationTemporaryPath);
    File::ensureDirectoryExists(storage_path('vite'));
});

afterEach(function (): void {
    app()->usePublicPath($this->installationOriginalPublicPath);
    app()->useStoragePath($this->installationOriginalStoragePath);
    File::deleteDirectory($this->installationTemporaryPath);
});

/**
 * Puts the hotel into a fresh, not-yet-installed state. The test database
 * seeds a completed installation, so rewind it and drop every cache layer
 * that remembers completion.
 */
function startFreshInstallation(): WebsiteInstallation
{
    $installation = WebsiteInstallation::first();
    $installation->update([
        'step' => 0,
        'completed' => false,
        'installation_key' => 'test-install-key',
        'user_ip' => null,
    ]);

    resetInstallationCaches();

    return $installation->fresh();
}

/**
 * Feature tests reuse one app instance across requests, so both the cache
 * and the singleton's in-memory memo must be cleared between steps.
 */
function resetInstallationCaches(): void
{
    Cache::flush();
    app()->forgetInstance(InstallationService::class);
    app()->forgetInstance(SettingsService::class);
}

/**
 * The wizard splits every setting, in id order, into SETTING_STEPS chunks; this
 * returns the keys of the chunk shown on the given step.
 *
 * @return list<string>
 */
function installationStepKeys(int $step): array
{
    $keys = WebsiteSetting::query()->orderBy('id')->pluck('key');
    $chunkSize = max(1, (int) ceil($keys->count() / WebsiteInstallation::SETTING_STEPS));

    return $keys->chunk($chunkSize)->values()->get($step - 1)?->values()->all() ?? [];
}

function installationStepFor(string $key): int
{
    foreach (range(WebsiteInstallation::FIRST_STEP, WebsiteInstallation::SETTING_STEPS) as $step) {
        if (in_array($key, installationStepKeys($step), true)) {
            return $step;
        }
    }

    throw new RuntimeException("No installation step shows the [{$key}] setting.");
}

/** Puts the wizard on the step that shows the given setting, as the installing visitor. */
function openInstallationStepFor(string $key): int
{
    $step = installationStepFor($key);

    startFreshInstallation()->update(['step' => $step, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    return $step;
}

test('the wizard walks from key entry to the hotel without falling back', function () {
    installHotel();
    startFreshInstallation();

    // Step 0: an uninstalled hotel sends visitors to the wizard.
    $this->get('/')->assertRedirect(route('installation.index'));
    $this->get(route('installation.index'))->assertOk();

    // Enter the installation key.
    $this->post(route('installation.start-installation'), ['installation_key' => 'test-install-key'])
        ->assertRedirect(route('installation.show-step', 1));

    resetInstallationCaches();

    // Walk the four settings steps.
    foreach (range(1, 4) as $step) {
        $this->get(route('installation.show-step', $step))->assertOk();

        $this->post(route('installation.save-step'), ['hotel_name' => 'Testaria'])
            ->assertRedirect(route('installation.show-step', $step + 1));

        resetInstallationCaches();
    }

    expect(WebsiteInstallation::first()->step)->toBe(5);

    $this->get(route('installation.show-step', 5))->assertOk();

    // "Take me to the hotel".
    $this->post(route('installation.complete'))->assertRedirect(route('welcome'));

    resetInstallationCaches();

    expect((bool) WebsiteInstallation::first()->completed)->toBeTrue();

    // The hotel now serves visitors instead of bouncing them to the wizard.
    $this->get('/')->assertOk();
    $this->get(route('installation.index'))->assertRedirect(route('welcome'));
});

test('completion works even when the first-visit race left duplicate rows', function () {
    installHotel();
    $installation = startFreshInstallation();

    // Concurrent first-ever requests each pass the "no row yet" check in the
    // middleware and create their own record - reproduce that aftermath.
    WebsiteInstallation::create([
        'step' => 0,
        'completed' => false,
        'installation_key' => 'straggler-key',
        'user_ip' => null,
    ]);
    WebsiteInstallation::whereKey(WebsiteInstallation::max('id'))->update(['created_at' => now()->addMinute()]);

    // The wizard progressed on the first row up to the final step.
    $installation->update(['step' => 5, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    // "Take me to the hotel".
    $this->post(route('installation.complete'))->assertRedirect(route('welcome'));

    resetInstallationCaches();

    // The hotel must open - not bounce back into the wizard.
    $this->get('/')->assertOk();
});

test('a wrong installation key is rejected', function () {
    installHotel();
    startFreshInstallation();

    $this->post(route('installation.start-installation'), ['installation_key' => 'wrong'])
        ->assertSessionHasErrors('installation_key');

    expect(WebsiteInstallation::first()->step)->toBe(0);
});

test('optional settings are not blocked by browser required validation', function () {
    installHotel();

    foreach (WebsiteSetting::OPTIONAL_INSTALLATION_KEYS as $key) {
        $setting = WebsiteSetting::query()->where('key', $key)->firstOrFail();
        $content = view('core::installation.partials.setting-input', [
            'setting' => $setting,
            'errors' => new ViewErrorBag,
        ])->render();
        preg_match('/<input\b[^>]*\bname="' . preg_quote($key, '/') . '"[^>]*>/i', $content, $matches);

        expect($matches)->toHaveCount(1)
            ->and($matches[0])->not->toContain(' required');
    }

    $setting = WebsiteSetting::query()->where('key', 'hotel_name')->firstOrFail();
    $content = view('core::installation.partials.setting-input', [
        'setting' => $setting,
        'errors' => new ViewErrorBag,
    ])->render();
    preg_match('/<input\b[^>]*\bname="hotel_name"[^>]*>/i', $content, $matches);

    expect($matches)->toHaveCount(1)
        ->and($matches[0])->toContain(' required');
});

test('every setting without a default value is optional during installation', function () {
    $settingsWithoutDefault = WebsiteSetting::query()->where('value', '')->get();

    expect($settingsWithoutDefault)->not->toBeEmpty();

    foreach ($settingsWithoutDefault as $setting) {
        expect($setting->isRequiredDuringInstallation())->toBeFalse("{$setting->key} has no default value");
    }
});

test('visiting a later step than reached redirects back to the current step', function () {
    installHotel();
    $installation = startFreshInstallation();
    $installation->update(['step' => 2, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    $this->get(route('installation.show-step', 4))
        ->assertRedirect(route('installation.show-step', 2));
});

test('another visitor cannot hijack an installation in progress', function () {
    installHotel();
    $installation = startFreshInstallation();
    $installation->update(['step' => 3, 'user_ip' => '203.0.113.9']);
    resetInstallationCaches();

    $this->get(route('installation.show-step', 3))->assertForbidden();
});

test('a step past the end of the wizard shows the last step instead of an exception', function () {
    installHotel();
    $installation = startFreshInstallation();

    // Older builds let a resubmitted form increment the step past the end,
    // and every later request then died on "Step does not exist".
    WebsiteInstallation::query()->update(['step' => 9, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    expect(WebsiteInstallation::first()->step)->toBe(WebsiteInstallation::LAST_STEP);

    $this->get(route('installation.show-step', WebsiteInstallation::LAST_STEP))->assertOk();
    $this->get(route('installation.show-step', 9))
        ->assertRedirect(route('installation.show-step', WebsiteInstallation::LAST_STEP));

    $installation->refresh();
});

test('saving past the last step stays on it rather than running off the end', function () {
    installHotel();
    startFreshInstallation();
    WebsiteInstallation::query()->update(['step' => WebsiteInstallation::LAST_STEP, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    $this->post(route('installation.save-step'))
        ->assertRedirect(route('installation.show-step', WebsiteInstallation::LAST_STEP));

    expect(WebsiteInstallation::first()->step)->toBe(WebsiteInstallation::LAST_STEP);
});

test('going back from the first step stays on it', function () {
    installHotel();
    startFreshInstallation();
    WebsiteInstallation::query()->update(['step' => 1, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    $this->post(route('installation.previous-step'))
        ->assertRedirect(route('installation.show-step', 1));

    expect(WebsiteInstallation::first()->step)->toBe(1);
});

test('unknown installation steps are bounced back to the current step', function () {
    installHotel();
    $installation = startFreshInstallation();
    $installation->update(['step' => 1, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    // The global installation middleware bounces both before routing; the
    // route's whereNumber constraint and the controller's 404 guard remain
    // as defense in depth should the middleware ever let one through.
    $this->get(route('installation.show-step', 99))
        ->assertRedirect(route('installation.show-step', 1));

    $this->get('/installation/step/not-a-step')
        ->assertRedirect(route('installation.show-step', 1));
});

test('saving a step only writes the settings that belong to it', function () {
    installHotel();
    $installation = startFreshInstallation();
    $installation->update(['step' => 1, 'user_ip' => '127.0.0.1']);
    resetInstallationCaches();

    $keys = WebsiteSetting::query()->orderBy('id')->pluck('key');
    $chunkSize = max(1, (int) ceil($keys->count() / WebsiteInstallation::SETTING_STEPS));
    $chunks = $keys->chunk($chunkSize)->values();

    $stepOneKey = $chunks->get(0)->first();
    $laterStepKey = $chunks->get(3)->last();
    $laterStepValue = WebsiteSetting::where('key', $laterStepKey)->value('value');

    $this->post(route('installation.save-step'), [
        $stepOneKey => 'legit step one value',
        $laterStepKey => 'smuggled value',
        'not_a_real_setting' => 'ignored',
        'installation_key' => ['array' => 'values are not settings'],
    ])->assertRedirect(route('installation.show-step', 2));

    expect(WebsiteSetting::where('key', $stepOneKey)->value('value'))->toBe('legit step one value')
        ->and(WebsiteSetting::where('key', $laterStepKey)->value('value'))->toBe($laterStepValue);
});

test('the theme step rejects a theme that is not installed and keeps the stored one', function () {
    installHotel();
    setSetting('theme', 'atom');
    $step = openInstallationStepFor('theme');

    $this->from(route('installation.show-step', $step))
        ->post(route('installation.save-step'), ['theme' => 'missing'])
        ->assertRedirect(route('installation.show-step', $step))
        ->assertSessionHasErrors('theme');

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('atom')
        ->and(setting('theme'))->toBe('atom')
        ->and(WebsiteInstallation::first()->step)->toBe($step);
});

test('the theme step saves an installed theme and the new value is visible straight away', function () {
    installHotel();
    setSetting('theme', 'atom');
    $step = openInstallationStepFor('theme');

    expect(setting('theme'))->toBe('atom');

    $this->post(route('installation.save-step'), ['theme' => 'dusk'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('installation.show-step', $step + 1));

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('dusk')
        ->and(setting('theme'))->toBe('dusk');
});

test('restarting the installation resets the theme to the configured default', function () {
    installHotel();
    config(['theme.default' => 'dusk']);
    setSetting('theme', 'atom');
    openInstallationStepFor('theme');

    expect(setting('theme'))->toBe('atom');

    $this->post(route('installation.restart'))->assertRedirect(route('installation.index'));

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('dusk')
        ->and(setting('theme'))->toBe('dusk');
});

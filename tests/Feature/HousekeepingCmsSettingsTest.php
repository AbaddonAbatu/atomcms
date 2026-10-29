<?php

use App\Filament\Resources\Atom\CmsSettings\Pages\ManageCmsSettings;
use App\Models\Miscellaneous\WebsiteSetting;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->cmsSettingsOriginalPublicPath = public_path();
    $this->cmsSettingsOriginalStoragePath = storage_path();
    $this->cmsSettingsTemporaryPath = sys_get_temp_dir() . '/atom-cms-settings-' . bin2hex(random_bytes(12));
    app()->usePublicPath(base_path('tests/Fixtures/vite/manifest'));
    app()->useStoragePath($this->cmsSettingsTemporaryPath);
    File::ensureDirectoryExists(storage_path('vite'));
    installHotel();
    grantHousekeepingPermission('can_access_housekeeping', 6);
    grantHousekeepingPermission('manage_website_settings', 6);
    $this->actingAs(User::factory()->create(['rank' => 6]));
    Filament::setCurrentPanel(Filament::getPanel('housekeeping'));
});

afterEach(function (): void {
    app()->usePublicPath($this->cmsSettingsOriginalPublicPath);
    app()->useStoragePath($this->cmsSettingsOriginalStoragePath);
    File::deleteDirectory($this->cmsSettingsTemporaryPath);
});

/**
 * The form clears an empty comment to null, which the column refuses, so the
 * record needs a comment like the seeded settings have.
 */
function storeCmsSetting(string $key, string $value): void
{
    WebsiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'comment' => 'Seeded comment']);
}

function editCmsSetting(string $key, string $value): Testable
{
    $record = WebsiteSetting::query()->where('key', $key)->firstOrFail();

    return Livewire::test(ManageCmsSettings::class)
        ->callAction(TestAction::make(EditAction::class)->table($record), data: ['value' => $value]);
}

test('housekeeping rejects a theme that is not installed and keeps the stored one', function () {
    storeCmsSetting('theme', 'atom');

    editCmsSetting('theme', 'missing')->assertHasActionErrors(['value']);

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('atom')
        ->and(setting('theme'))->toBe('atom');
});

test('housekeeping rejects a non-string theme ID without changing the stored theme', function (): void {
    storeCmsSetting('theme', 'atom');
    $record = WebsiteSetting::query()->where('key', 'theme')->firstOrFail();

    $component = Livewire::test(ManageCmsSettings::class)
        ->callAction(TestAction::make(EditAction::class)->table($record), data: ['value' => 2])
        ->assertHasActionErrors(['value']);

    expect($component->errors()->all())->toContain('This theme cannot be activated: The theme ID must be a string.');
    expect($record->refresh()->value)->toBe('atom');
    expect(setting('theme'))->toBe('atom');
});

test('housekeeping saves an installed theme and refreshes the settings', function () {
    storeCmsSetting('theme', 'atom');

    expect(setting('theme'))->toBe('atom');

    editCmsSetting('theme', 'dusk')->assertHasNoActionErrors();

    expect(WebsiteSetting::where('key', 'theme')->value('value'))->toBe('dusk')
        ->and(setting('theme'))->toBe('dusk');
});

test('housekeeping still accepts any value for other settings', function () {
    storeCmsSetting('hotel_name', 'Testaria');

    editCmsSetting('hotel_name', 'missing')->assertHasNoActionErrors();

    expect(setting('hotel_name'))->toBe('missing');
});

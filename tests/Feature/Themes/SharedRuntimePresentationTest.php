<?php

use App\Models\User;

beforeEach(function (): void {
    installHotel();
    setSetting('force_staff_2fa', '0');
});

dataset('runtime presentation themes', ['atom' => 'atom', 'dusk' => 'dusk']);

test('the badge page renders its translations, GIF dependency and purchase controller once in order', function (string $theme): void {
    setSetting('theme', $theme);
    setSetting('drawbadge_currency_value', '275');
    setSetting('drawbadge_currency_type', 'duckets');
    setSetting('badge_path_filesystem', storage_path('framework/testing/missing-badges'));

    $response = $this->actingAs(User::factory()->create())->get(route('draw-badge'));

    $response->assertSeeInOrder(['const translations =', 'x-data="badgeDrawer({ cost: 275, currencyType:', 'js/gif/gif.js', 'function badgeDrawer(', 'async buyBadge()'], false)
        ->assertSee(['js/gif/gif.worker.js', route('badge.buy'), 'repeating-linear-gradient', "'#1a1a1a'"], false);
    foreach (['const translations =', 'js/gif/gif.js', 'function badgeDrawer(', 'async buyBadge()', '#FF4500'] as $script) {
        expect(substr_count($response->getContent(), $script))->toBe(1);
    }
})->with('runtime presentation themes');

test('the logo page renders html2canvas before its generator once', function (string $theme): void {
    setSetting('theme', $theme);

    $response = $this->actingAs(User::factory()->create(['rank' => 7]))->get(route('logo-generator.index'));

    $response->assertSeeInOrder(['id="logoContainer"', 'html2canvas/1.4.1/html2canvas.min.js', 'function logoGenerator()', 'async uploadLogo(canvas)'], false)
        ->assertSee(['width: 15px;', route('store.generated-logo')], false);
    foreach (['html2canvas/1.4.1/html2canvas.min.js', 'function logoGenerator()', 'async uploadLogo(canvas)', 'get generateLogoHtml()', '/assets/images/logo-generator/${this.fontType}/${letter}.png'] as $script) {
        expect(substr_count($response->getContent(), $script))->toBe(1);
    }
})->with('runtime presentation themes');

test('the Flash page renders the launch dependencies, styles and member configuration in order once', function (string $theme): void {
    setSetting('theme', $theme);
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get(route('flash-client'));

    $response->assertSeeInOrder(['assets/js/flashclient.js', 'assets/js/swfobject.js', 'assets/css/clientnew.css', 'assets/css/no-flash.css', 'var flashvars =', 'swfobject.embedSWF(', '<div id="client">'], false)
        ->assertSee('"sso.ticket": \'' . $member->fresh()->auth_ticket . '\'', false);
    foreach (['assets/js/flashclient.js', 'assets/js/swfobject.js', 'var flashvars =', 'swfobject.embedSWF('] as $script) {
        expect(substr_count($response->getContent(), $script))->toBe(1);
    }
})->with('runtime presentation themes');

test('the Nitro page renders its helpers, polling and bridge once after the iframe', function (string $theme): void {
    setSetting('theme', $theme);
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get(route('nitro-client'));

    $response->assertSeeInOrder(['<iframe id="nitro"', 'function toggleFullscreen()', 'function reloadClient()', 'function getOnlineUserCount()', 'assets/js/atom.js'], false)
        ->assertSee(['index.html?sso=' . $member->fresh()->auth_ticket, route('api.online-count'), '}, 15000);'], false);
    foreach (['function toggleFullscreen()', 'function reloadClient()', 'function getOnlineUserCount()', 'assets/js/atom.js'] as $script) {
        expect(substr_count($response->getContent(), $script))->toBe(1);
    }
})->with('runtime presentation themes');

test('the welcome page renders the flashed queue and toast bridge once', function (string $theme): void {
    setSetting('theme', $theme);

    $response = $this->withSession(['message' => 'Runtime toast'])->get(route('welcome'));

    $response->assertSee(['Runtime toast', 'x-data="toastHub(', "new CustomEvent('toast'", 'window.Toast =', 'initialToasts.forEach', 'this.toasts.filter'], false);
    foreach (['window.toast =', 'window.Toast =', 'window.toastHub ='] as $script) {
        expect(substr_count($response->getContent(), $script))->toBe(1);
    }
})->with('runtime presentation themes');

test('the ticket form renders the configured TinyMCE CDN and initializes the editor once after its textarea', function (string $theme): void {
    setSetting('theme', $theme);
    setSetting('tinymce_api_key', 'runtime-editor-key');

    $response = $this->actingAs(User::factory()->create())->get(route('help-center.ticket.create'));

    $response->assertSeeInOrder(['<textarea name="content" id="editor">', 'https://cdn.tiny.cloud/1/runtime-editor-key/tinymce/7/tinymce.min.js', 'tinymce.init({'], false)
        ->assertSee(["selector: 'textarea#editor'", "plugins: 'lists image'", "toolbar: 'undo redo | blocks| bold italic | bullist numlist checklist | code | table'"], false);
    foreach (['tinymce/7/tinymce.min.js', 'tinymce.init({'] as $script) {
        expect(substr_count($response->getContent(), $script))->toBe(1);
    }
})->with('runtime presentation themes');

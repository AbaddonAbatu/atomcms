<?php

use App\Support\FooterCredits;

test('footer credits preserve contributor names and roles in order', function (): void {
    app()->setLocale('en');

    expect(FooterCredits::contributors())->toBe([
        ['Kasja', 'Design, ideas & GFX'],
        ['Nicollas', 'Dark mode, Turbolinks, performance, article reactions, user sessions, layout & PT-BR translations'],
        ['Dominic', 'Performance improvements & user sessions'],
        ['EntenKoeniq', 'Automatic language registration, rooms page, profile tweaks & shop additions'],
        ['MisterDeen', 'Custom Discord widget, bugfixes & tweaks'],
        ['Kani', 'RCON base & FindRetros API'],
        ['Beny', 'FindRetros API & Cloudflare fixes'],
        ['Oliver', 'Profile page additions & Finnish translations'],
        ['Live', 'French translations, bugfixes & tweaks'],
        ['DamienJolly', 'Bugfixes'],
        ['Danbo', 'Bugfixes'],
        ['Diddy/Josh', 'Code readability improvements'],
    ]);
});

test('footer credit roles use the active locale on every call', function (): void {
    app()->setLocale('en');
    expect(FooterCredits::contributors()[0])->toBe(['Kasja', 'Design, ideas & GFX']);

    app()->setLocale('de');
    expect(FooterCredits::contributors()[0])->toBe(['Kasja', 'Design, Ideen & GFX']);
});

test('footer credits preserve translators in order, keyed by the localized language name', function (): void {
    app()->setLocale('en');

    expect(FooterCredits::translators())->toBe([
        'German' => 'Damue & EntenKoeniq',
        'Turkish' => 'Talion',
        'Swedish' => 'CentralCee, Rille & Tuborgs',
        'Dutch' => 'Yannick',
        'Spanish' => 'Gedomi',
        'Italian' => 'Lorenzune',
        'Norwegian' => 'Twana & Zaruzet',
        'French' => 'Plow & Live',
        'Finnish' => 'Oliver',
        'Portuguese (BR)' => 'Nicollas',
    ]);

    app()->setLocale('de');
    expect(FooterCredits::translators())->toHaveKey('Deutsch', 'Damue & EntenKoeniq')
        ->toHaveKey('Portugiesisch (BR)', 'Nicollas');
});

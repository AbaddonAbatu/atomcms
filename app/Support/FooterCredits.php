<?php

namespace App\Support;

final class FooterCredits
{
    /**
     * @return list<array{string, string}>
     */
    public static function contributors(): array
    {
        return [
            ['Kasja', __('Design, ideas & GFX')],
            ['Nicollas', __('Dark mode, Turbolinks, performance, article reactions, user sessions, layout & PT-BR translations')],
            ['Dominic', __('Performance improvements & user sessions')],
            ['EntenKoeniq', __('Automatic language registration, rooms page, profile tweaks & shop additions')],
            ['MisterDeen', __('Custom Discord widget, bugfixes & tweaks')],
            ['Kani', __('RCON base & FindRetros API')],
            ['Beny', __('FindRetros API & Cloudflare fixes')],
            ['Oliver', __('Profile page additions & Finnish translations')],
            ['Live', __('French translations, bugfixes & tweaks')],
            ['DamienJolly', __('Bugfixes')],
            ['Danbo', __('Bugfixes')],
            ['Diddy/Josh', __('Code readability improvements')],
        ];
    }

    /**
     * Translators keyed by the language name in the active locale.
     *
     * @return array<string, string>
     */
    public static function translators(): array
    {
        return [
            __('German') => 'Damue & EntenKoeniq',
            __('Turkish') => 'Talion',
            __('Swedish') => 'CentralCee, Rille & Tuborgs',
            __('Dutch') => 'Yannick',
            __('Spanish') => 'Gedomi',
            __('Italian') => 'Lorenzune',
            __('Norwegian') => 'Twana & Zaruzet',
            __('French') => 'Plow & Live',
            __('Finnish') => 'Oliver',
            __('Portuguese (BR)') => 'Nicollas',
        ];
    }
}

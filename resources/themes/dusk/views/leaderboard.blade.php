<x-app-layout>
    @push('title', __('Leaderboard'))

    <div class="col-span-12">
        <x-theme-dusk::page-header>
            <x-slot:icon>
                <img src="{{ asset('/assets/images/dusk/leaderboard_icon.png') }}" alt="">
            </x-slot:icon>

            {{ __('Leaderboards') }}
        </x-theme-dusk::page-header>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-3 mt-4">
            <x-theme-dusk::leaderboard-card title="{{ __('Top credits') }}" icon="credits.png" :data="$credits" valueType="Credits" />
            <x-theme-dusk::leaderboard-card title="{{ __('Top duckets') }}" icon="duckets.png" :data="$duckets" valueType="Duckets" />
            <x-theme-dusk::leaderboard-card title="{{ __('Top diamonds') }}" icon="diamond.png" :data="$diamonds" valueType="Diamonds" />
            @if ($mostOnline !== null)
                <x-theme-dusk::leaderboard-card title="{{ __('Hours online') }}" icon="clock.gif" :data="$mostOnline" valueType="Hours online" :formatValue="fn($value) => round($value / 3600)" />
            @endif
            <x-theme-dusk::leaderboard-card title="{{ __('Respects received') }}" icon="heart.gif" :data="$respectsReceived" valueType="Respect received" />
            <x-theme-dusk::leaderboard-card title="{{ __('Achievement score') }}" icon="star.gif" :data="$achievementScores" valueType="Achievement points" />
        </div>
    </div>
</x-app-layout>

<x-theme-aurora::shell title="Leaderboard">
    <section class="aurora-card">
        <h1>Leaderboard</h1>
        @foreach (['credits' => $credits, 'duckets' => $duckets, 'diamonds' => $diamonds, 'respects' => $respectsReceived, 'achievement scores' => $achievementScores] as $label => $entries)
            <h2>{{ $label }}</h2>
            <ol class="aurora-list">
                @forelse ($entries as $entry)
                    <li>{{ $entry->username ?? $entry->user->username ?? 'Unknown' }} — {{ $entry->value ?? $entry->amount ?? $entry->score ?? 0 }}</li>
                @empty
                    <li class="aurora-muted">No entries.</li>
                @endforelse
            </ol>
        @endforeach
        @if ($mostOnline)
            <h2>Most online</h2><p>{{ count($mostOnline) }} members listed.</p>
        @endif
    </section>
</x-theme-aurora::shell>

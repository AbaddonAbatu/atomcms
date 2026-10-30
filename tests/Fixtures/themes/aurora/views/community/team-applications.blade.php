<x-theme-aurora::shell title="Team applications">
    <section class="aurora-card">
        <h1>Open team positions</h1>
        <ul class="aurora-list">
            @forelse ($positions as $position)
                @continue(!$position->team)
                @php($status = auth()->check() ? ($userAppStatuses[$position->team->id] ?? null) : null)
                <li>
                    <strong>{{ $position->team->rank_name }}</strong> — {{ $position->description }}
                    @if ($status)
                        <span class="aurora-muted">{{ ucfirst($status) }}</span>
                    @else
                        <a href="{{ route('team-applications.show', $position) }}">Apply</a>
                    @endif
                </li>
            @empty
                <li class="aurora-muted">No positions are open.</li>
            @endforelse
        </ul>
    </section>
</x-theme-aurora::shell>

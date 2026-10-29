<x-theme-aurora::shell title="Staff applications">
    <section class="aurora-card">
        <h1>Open staff positions</h1>
        <ul class="aurora-list">
            @forelse ($positions as $position)
                @if ($position->permission)
                    <li>
                        <strong>{{ $position->permission->rank_name }}</strong> — {{ $position->description }}
                        @if (auth()->check() && ($position->permission->id ?? null) && auth()->user()->hasAppliedForPosition($position->permission->id))
                            <span class="aurora-muted">Application already submitted</span>
                        @else
                            <a href="{{ route('staff-applications.show', $position) }}">Apply</a>
                        @endif
                    </li>
                @endif
            @empty
                <li class="aurora-muted">No positions are open.</li>
            @endforelse
        </ul>
    </section>
</x-theme-aurora::shell>

<x-theme-aurora::shell title="Session logs">
    <section class="aurora-card">
        <h1>Session logs</h1>
        <ul class="aurora-list">
            @forelse ($logs as $log)
                <li>{{ $log->agent['is_desktop'] ? 'Desktop' : 'Mobile' }} — {{ $log->agent['platform'] }} — {{ $log->agent['browser'] }} — {{ $log->ipAddress }} — {{ $log->lastActive }} @if ($log->isCurrentDevice) (current device) @endif</li>
            @empty
                <li class="aurora-muted">No session logs.</li>
            @endforelse
        </ul>
    </section>
</x-theme-aurora::shell>

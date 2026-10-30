<x-theme-aurora::shell title="Account notice">
    <section class="aurora-card">
        <h1>Access restricted</h1>
        @if ($ban)
            <p>Type: {{ $ban->type }}</p>
            <p>Reason: {{ $ban->ban_reason }}</p>
            <p>Expires: {{ $ban->ban_expire === null ? 'Never' : date('Y-m-d H:i:s', $ban->ban_expire) }}</p>
        @else
            <p class="aurora-muted">The ban details are no longer available.</p>
        @endif
    </section>
</x-theme-aurora::shell>

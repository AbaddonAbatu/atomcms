<x-theme-aurora::shell :title="$value->name">
    <section class="aurora-card">
        <h1>{{ $value->name }}</h1>
        <ul class="aurora-list">
            @forelse ($items as $item)
                <li>{{ $item['user']?->username ?? 'Unknown owner' }} — {{ $item['item_count'] ?? 0 }} owned</li>
            @empty
                <li class="aurora-muted">Nobody owns this rare yet.</li>
            @endforelse
        </ul>
    </section>
</x-theme-aurora::shell>

<x-theme-aurora::shell title="Photos">
    <section class="aurora-card">
        <h1>Community photos</h1>
        <ul class="aurora-list">
            @forelse ($photos as $photo)
                <li>{{ $photo->title ?? 'Photo' }} — {{ $photo->user?->username ?? 'Unknown photographer' }}</li>
            @empty
                <li class="aurora-muted">No photos are available.</li>
            @endforelse
        </ul>
        {{ $photos->links() }}
    </section>
</x-theme-aurora::shell>

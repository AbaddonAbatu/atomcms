<x-theme-aurora::shell title="Help center">
    <section class="aurora-card">
        <h1>Help center</h1>
        <ul class="aurora-list">
            @forelse ($categories as $category)
                <li>
                    <strong>{{ $category->name }}</strong> — {{ $category->content }}
                    <a href="{{ $category->button_url ?? '#' }}">{{ $category->button_text ?? 'Read more' }}</a>
                </li>
            @empty
                <li class="aurora-muted">No help categories are available.</li>
            @endforelse
        </ul>
        <a href="{{ route('help-center.ticket.create') }}">Open a ticket</a>
    </section>
</x-theme-aurora::shell>

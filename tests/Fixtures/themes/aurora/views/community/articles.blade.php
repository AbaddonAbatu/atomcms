<x-theme-aurora::shell title="Articles">
    <section class="aurora-card">
        <h1>Community articles</h1>
        <ul class="aurora-list">
            @forelse ($articles as $article)
                <li><a href="{{ route('article.show', $article->slug) }}">{{ $article->title }}</a><span class="aurora-muted"> by {{ $article->user?->username ?? 'Unknown author' }}</span><p>{{ $article->short_story }}</p></li>
            @empty
                <li class="aurora-muted">No articles found.</li>
            @endforelse
        </ul>
        {{ $articles->links() }}
    </section>
</x-theme-aurora::shell>

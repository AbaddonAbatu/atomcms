<x-theme-aurora::shell :title="$article->title">
    <article class="aurora-card">
        <h1>{{ $article->title }}</h1>
        <p class="aurora-muted">By {{ $article->user?->username ?? 'Unknown author' }}</p>
        <p>{{ $article->short_story }}</p>
        <div>{!! $article->full_story !!}</div>
    </article>
    <section class="aurora-card">
        <h2>Other articles</h2>
        <ul class="aurora-list">
            @forelse ($otherArticles as $otherArticle)
                <li><a href="{{ route('article.show', $otherArticle->slug) }}">{{ $otherArticle->title }}</a></li>
            @empty
                <li>No other articles are available.</li>
            @endforelse
        </ul>
    </section>
    <livewire:article-reactions :article="$article" />
    <livewire:article-comments :article="$article" />
</x-theme-aurora::shell>

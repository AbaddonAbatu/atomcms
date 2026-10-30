<x-theme-aurora::shell title="Welcome">
    <section class="aurora-card">
        <h1>Welcome to Aurora</h1>
        <p class="aurora-muted">A small, independent presentation of the public home page.</p>
        @guest
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="aurora-field"><label for="welcome-username">Username</label><input id="welcome-username" name="username" data-aurora-focus value="{{ old('username') }}"></div>
                <div class="aurora-field"><label for="welcome-password">Password</label><input id="welcome-password" name="password" type="password"></div>
                <button class="aurora-button" type="submit">Log in</button>
            </form>
        @endguest
    </section>
    <section class="aurora-card">
        <h2>Latest articles</h2>
        <ul class="aurora-list">
            @forelse ($articles as $article)
                <li><a href="{{ route('article.show', $article->slug) }}">{{ $article->title }}</a> — {{ $article->user?->username ?? 'Unknown author' }}</li>
            @empty
                <li class="aurora-muted">No articles are available yet.</li>
            @endforelse
        </ul>
    </section>
    <section class="aurora-card">
        <h2>Recent photos</h2>
        <ul class="aurora-list">
            @forelse ($photos as $photo)
                <li>{{ $photo->title ?? 'Community photo' }} — {{ $photo->user?->username ?? 'Unknown photographer' }}</li>
            @empty
                <li class="aurora-muted">Photos are not available on this emulator.</li>
            @endforelse
        </ul>
    </section>
</x-theme-aurora::shell>

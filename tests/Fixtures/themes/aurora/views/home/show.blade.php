<x-theme-aurora::shell :title="$user->username">
    <section class="aurora-card">
        <h1>{{ $user->username }}'s home</h1>
        <p>{{ $user->motto }}</p>
        @if ($isMe)<a href="{{ route('home.inventory', $user) }}">Edit home</a>@endif
    </section>
</x-theme-aurora::shell>

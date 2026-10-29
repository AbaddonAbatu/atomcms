<x-theme-aurora::shell title="Rules">
    <section class="aurora-card">
        <h1>Rules</h1>
        @forelse ($categories as $category)
            <section><h2>{{ $category->name }}</h2><ul class="aurora-list">@forelse ($category->rules as $rule)<li>{{ $rule->title ?? $rule->name }} — {{ $rule->description ?? $rule->content }}</li>@empty<li>No rules in this category.</li>@endforelse</ul></section>
        @empty
            <p class="aurora-muted">No rules are available.</p>
        @endforelse
    </section>
</x-theme-aurora::shell>

<x-theme-aurora::shell title="Rare values">
    <section class="aurora-card">
        <h1>Rare values</h1>
        <form action="{{ route('values.search') }}" method="POST">
            @csrf
            <div class="aurora-field"><label for="search">Search for a rare</label><input id="search" name="search" value="{{ old('search') }}"></div>
            <button class="aurora-button" type="submit">Search</button>
        </form>
        @if (isset($categories->name))
            <h2>{{ $categories->name }}</h2>
            <ul class="aurora-list">@foreach ($categories->furniture as $rare)<li><a href="{{ route('values.value', $rare) }}">{{ $rare->name }}</a></li>@endforeach</ul>
        @else
            @forelse ($categories as $category)
                <h2><a href="{{ route('values.category', $category->id) }}">{{ $category->name }}</a></h2>
                <ul class="aurora-list">@forelse ($category->furniture as $rare)<li>{{ $rare->name }}</li>@empty<li>No rares in this category.</li>@endforelse</ul>
            @empty
                <p class="aurora-muted">No rare categories are available.</p>
            @endforelse
        @endif
    </section>
</x-theme-aurora::shell>

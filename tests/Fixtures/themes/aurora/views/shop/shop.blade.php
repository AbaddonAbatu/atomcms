<x-theme-aurora::shell title="Shop">
    <section class="aurora-card">
        <h1>Shop</h1>
        <nav class="aurora-nav"><a href="{{ route('shop.index') }}">All packages</a>@foreach ($categories as $category)<a href="{{ route('shop.index', $category->slug) }}">{{ $category->name }}</a>@endforeach</nav>
    </section>
    <section class="aurora-card">
        <ul class="aurora-list">
            @forelse ($shopPackages as $package)
                <li>
                    <strong>{{ $package->name }}</strong> — {{ $package->description }}
                    <form action="{{ route('shop.buy-package', $package) }}" method="POST">
                        @csrf
                        <div class="aurora-field"><label for="receiver-{{ $package->id }}">Recipient username</label><input id="receiver-{{ $package->id }}" name="receiver" value="{{ auth()->user()->username }}"></div>
                        <button class="aurora-button" type="submit">Purchase</button>
                    </form>
                </li>
            @empty
                <li class="aurora-muted">No packages are available.</li>
            @endforelse
        </ul>
    </section>
    <section class="aurora-card">
        <h2>Use a voucher</h2>
        <form action="{{ route('shop.use-voucher') }}" method="POST">
            @csrf
            <div class="aurora-field"><label for="code">Voucher code</label><input id="code" name="code" type="text"></div>
            <button class="aurora-button" type="submit">Use voucher</button>
        </form>
    </section>
</x-theme-aurora::shell>

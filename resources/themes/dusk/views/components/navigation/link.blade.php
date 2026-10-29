@props(['route', 'icon', 'routeGroup'])

<a href="{{ $route }}" @class(['flex flex-col gap-1 lg:items-center transition ease-in-out hover:text-[#ac93da]', 'active' => request()->routeIs($routeGroup)])>
    <img class="icon hidden lg:block" src="{{ theme_asset('images/' . $icon) }}" alt="">
    {{ $slot }}
</a>

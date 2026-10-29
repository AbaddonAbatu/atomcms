<x-app-layout>
    @push('title', __('Articles'))

    <div class="col-span-12">
        <x-theme-dusk::page-header>
            <x-slot:icon>
                <img src="{{ asset('/assets/images/news.png') }}" alt="">
            </x-slot:icon>

            {{ __('News') }}
        </x-theme-dusk::page-header>

        <div class=" grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-4">
            @forelse($articles as $article)
                <div class="h-[250px]">
                    <x-theme-dusk::article-card :article="$article" />
                </div>
            @empty
                <x-theme-dusk::filler-article-card />
            @endforelse

        </div>

        <div class="mt-4">
            {{ $articles->links() }}
        </div>
    </div>
</x-app-layout>

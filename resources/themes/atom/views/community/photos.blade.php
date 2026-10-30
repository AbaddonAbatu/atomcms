<x-app-layout>
    @push('title', __('Photos'))

    <div class="col-span-12">
        <x-theme-atom::content.content-card icon="camera-icon">
            <x-slot:title>
                {{ __('Latest Photos') }}
            </x-slot:title>

            <x-slot:under-title>
                {{ __('Have a look at some of the great moments captured by users around the hotel.') }}
            </x-slot:under-title>

            <x-theme-atom::photos :photos="$photos" />
        </x-theme-atom::content.content-card>

        {{ $photos->links() }}
    </div>

    <x-core::fancybox-assets />
</x-app-layout>

@props(['languages'])

<x-theme-dusk::navigation.dropdown classes="!border-none" childClasses="w-[50px] -ml-2 flex items-center" :show-chevron="true" :flex-col="false">
    {{ $slot }}

    <x-slot:children>
        @foreach ($languages as $lang)
            <x-theme-dusk::navigation.dropdown-child :route="route('language.select', $lang->country_code)" classes="transition ease-in-out duration-300 hover:scale-110 flex justify-center">
                <img src="/assets/images/icons/flags/{{ $lang->country_code }}.png" alt="{{ $lang->country_code }}">
            </x-theme-dusk::navigation.dropdown-child>
        @endforeach
    </x-slot:children>
</x-theme-dusk::navigation.dropdown>

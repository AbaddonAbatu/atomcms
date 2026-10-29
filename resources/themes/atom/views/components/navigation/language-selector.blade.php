@props(['languages'])

<x-theme-atom::navigation.dropdown classes="!border-none" childClasses="min-w-[50px] -ml-4">
    {{ $slot }}

    <x-slot:children>
        @foreach ($languages as $lang)
            <x-theme-atom::navigation.dropdown-child :route="route('language.select', $lang->country_code)" :turbolink="false">
                <img src="/assets/images/icons/flags/{{ $lang->country_code }}.png" alt="{{ $lang->country_code }}">
            </x-theme-atom::navigation.dropdown-child>
        @endforeach
    </x-slot:children>
</x-theme-atom::navigation.dropdown>

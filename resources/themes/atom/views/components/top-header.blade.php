<div class="max-w-7xl min-h-[60px] px-4 md:flex md:items-center md:justify-between md:mx-auto">
    <div class="flex gap-x-6">
        <x-theme-atom::top-header-currency icon="nav-credit-icon">
            <x-slot:currency>
                {{ auth()->user()->credits }}
            </x-slot:currency>

            {{ __('Credits') }}
        </x-theme-atom::top-header-currency>

        <x-theme-atom::top-header-currency icon="nav-ducket-icon">
            <x-slot:currency>
                {{ auth()->user()->currency('duckets') }}
            </x-slot:currency>

            {{ __('Duckets') }}
        </x-theme-atom::top-header-currency>

        <x-theme-atom::top-header-currency icon="nav-diamond-icon">
            <x-slot:currency>
                {{ auth()->user()->currency('diamonds') }}
            </x-slot:currency>

            {{ __('Diamonds') }}
        </x-theme-atom::top-header-currency>
    </div>

    <div class="flex gap-x-3">
        @if(hasPermission('housekeeping_access') || hasPermission('generate_logo'))
            <x-theme-atom::navigation.dropdown classes="!text-red-700 !border-none">
                {{ __('Administration') }}

                <x-slot:children>
                    @if (hasPermission('generate_logo'))
                        <x-theme-atom::navigation.dropdown-child route="{{ route('logo-generator.index') }}" :turbolink="false" target="_blank">
                            {{ __('Logo generator') }}
                        </x-theme-atom::navigation.dropdown-child>
                    @endif

                    @if(hasPermission('housekeeping_access'))
                        <a data-turbolinks="false" href="{{ setting('housekeeping_url') }}" target="_blank" class="dropdown-item dark:text-gray-200 dark:hover:bg-gray-700">
                            {{ __('Housekeeping') }}
                        </a>
                    @endif
                </x-slot:children>
            </x-theme-atom::navigation.dropdown>
        @endif

        <x-theme-atom::navigation.dropdown classes="!border-none">
            <div @class([
                    '!bg-no-repeat !bg-center',
                    'w-[54px] h-[62px]' => Str::contains(setting('avatar_imager'), 'www.habbo.com'),
                    'w-[64px] h-[110px]' => !Str::contains(setting('avatar_imager'), 'www.habbo.com'),
                ])
                style="background: url({{ setting('avatar_imager') }}{{ auth()->user()->look }}&direction=2&headonly=1&head_direction=2&gesture=sml)">
            </div>

            <span class="-ml-2">{{ auth()->user()->username }}</span>

            <x-slot:children>
                <x-theme-atom::navigation.dropdown-child :route="route('settings.account.show')">
                    {{ __('User settings') }}
                </x-theme-atom::navigation.dropdown-child>


                <button class="py-2! px-4! dark:text-gray-200! dark:hover:bg-gray-700! hover:bg-gray-100! w-full text-left" @click.prevent="document.getElementById('logout-form').submit();">
                    {{ __('Logout') }}
                </button>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </x-slot:children>
        </x-theme-atom::navigation.dropdown>
    </div>
</div>

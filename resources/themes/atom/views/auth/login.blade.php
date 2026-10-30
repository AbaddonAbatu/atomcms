<x-app-layout>
    @push('title', __('Create account'))

    <!-- Validation Errors -->
    <x-theme-atom::messages.flash-messages />

    <div class="col-span-12">
        <div class="lg:px-[250px]">
            <x-theme-atom::content.content-card icon="hotel-icon" classes="flex flex-col">
                <x-slot:title>
                    {{ __('Login to :hotel', ['hotel' => setting('hotel_name')]) }}
                </x-slot:title>

                <x-slot:under-title>
                    {{ __('Login to :hotel and take part in the most wonderful online world!', ['hotel' => setting('hotel_name')]) }}
                </x-slot:under-title>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div>
                        <div class="flex flex-col gap-y-2">
                            <x-theme-atom::form.label for="username">
                                {{ __('Username') }}
                            </x-theme-atom::form.label>
                        </div>

                        <x-theme-atom::form.input error-bag="register" name="username" type="text" value="{{ old('username') }}"
                            placeholder="{{ __('Username') }}" :autofocus="true" />
                    </div>

                    <div class="mt-4">
                        <x-theme-atom::form.label for="password">
                            {{ __('Password') }}
                        </x-theme-atom::form.label>

                        <x-theme-atom::form.input error-bag="register" name="password" type="password"
                            placeholder="{{ __('Password') }}" />
                    </div>

                    @if (setting('google_recaptcha_enabled'))
                        <div class="mt-4 g-recaptcha" data-sitekey="{{ config('habbo.site.recaptcha_site_key') }}">
                        </div>
                    @endif

                    @if (setting('cloudflare_turnstile_enabled'))
                        <x-turnstile />
                    @endif

                    <div class="mt-4">
                        <x-theme-atom::form.primary-button>
                            {{ __('Login') }}
                        </x-theme-atom::form.primary-button>
                    </div>
                </form>
            </x-theme-atom::content.content-card>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    @push('title', __('Two factor'))

    <div class="col-span-12 flex flex-col gap-y-3 md:col-span-3">
        <x-theme-dusk::user.settings.settings-navigation />
    </div>

    <div class="col-span-12 flex flex-col gap-y-3 md:col-span-9">
        <x-theme-dusk::content.content-card icon="hotel-icon" classes="border dark:border-gray-900">
            <x-slot:title>
                {{ __('Confirm your password') }}
            </x-slot:title>

            <x-slot:under-title>
                {{ __('You must confirm your password to continue') }}
            </x-slot:under-title>

            <form method="POST" action="/user/confirm-password">
                @csrf

                <!-- Password -->
                <div class="flex flex-col gap-y-2 mb-3">
                    <div>
                        <x-theme-dusk::form.label for="password">
                            {{ __('Password') }}

                            <x-slot:info>
                                {{ __('You must confirm your current password before being able to toggle 2FA.') }}
                            </x-slot:info>
                        </x-theme-dusk::form.label>

                        <x-theme-dusk::form.input name="password" type="password"
                            placeholder="{{ __('Enter your current password') }}" />
                    </div>
                </div>

                <div class="mt-4">
                    <x-core::captcha.widgets :turnstile="false" />
                </div>

                <x-core::captcha.widgets :recaptcha="false" />

                <div class="mt-4">
                    <x-theme-dusk::form.primary-button>
                        {{ __('Confirm password') }}
                    </x-theme-dusk::form.primary-button>
                </div>
            </form>
        </x-theme-dusk::content.content-card>
    </div>
</x-app-layout>

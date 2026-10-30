<x-app-layout>
    @push('title', __('Reset Password'))

    <div class="col-span-12">
        <x-theme-atom::content.content-card icon="hotel-icon" classes="max-w-[640px] mx-auto">
            <x-slot:title>
                {{ __('Reset Password') }}
            </x-slot:title>

            <x-slot:under-title>
                {{ __('Choose a new password for your Account.') }}
            </x-slot:under-title>

            <form method="POST" action="{{ route('reset.password.post', $token) }}">
                @csrf

                <!-- Password -->
                <div class="bg-[#efefef] rounded-md p-3 flex flex-col gap-y-6 dark:bg-gray-900">
                    <div>
                        <x-theme-atom::form.label for="password">
                            {{ __('Password') }}

                            <x-slot:info>
                                {{ __('Your password must contain atleast 8 characters. Make sure to use a unique & secure password.') }}
                            </x-slot:info>
                        </x-theme-atom::form.label>

                        <x-theme-atom::form.input name="password" type="password" placeholder="{{ __('Choose a secure password') }}"/>
                    </div>
                    <hr class="dark:border-gray-700">

                    <!-- Confirm Password -->
                    <div>
                        <x-theme-atom::form.label for="password_confirmation">
                            {{ __('Repeat Password') }}
                        </x-theme-atom::form.label>

                        <x-theme-atom::form.input name="password_confirmation" type="password" placeholder="{{ __('Repeat your chosen password') }}"/>
                    </div>
                </div>

                <div class="mt-4">
                    <x-theme-atom::form.primary-button>
                        {{ __('Reset Password') }}
                    </x-theme-atom::form.primary-button>
                </div>
            </form>
        </x-theme-atom::content.content-card>
    </div>
</x-app-layout>

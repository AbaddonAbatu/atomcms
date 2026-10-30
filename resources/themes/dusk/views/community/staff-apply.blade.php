<x-app-layout>
    @push('title', __('Staff'))

    <div class="col-span-12 lg:col-span-9 lg:w-[96%]">
        <x-theme-dusk::content.staff-content-section :badge="$position->permission->badge" :color="$position->permission->staff_color">
            <x-slot:title>
                {{ __('You are applying for :position', ['position' => $position->permission->rank_name]) }}
            </x-slot:title>

            <x-slot:under-title>
                {{ __('Please fill out the fields below to apply for :position', ['position' => $position->permission->rank_name]) }}
            </x-slot:under-title>

            <form class="flex flex-col gap-y-3" action="{{ route('staff-applications.store', $position) }}" method="POST">
                @csrf

                <div>
                    <x-theme-dusk::form.label for="username" disabled>{{ __('Username') }}</x-theme-dusk::form.label>
                    <x-theme-dusk::form.input name="username" value="{{ auth()->user()->username }}" :readonly="true" />
                </div>

                <div>
                    <x-theme-dusk::form.label for="content" disabled>{{ __('About you') }}</x-theme-dusk::form.label>
                    <textarea name="content" class="focus:ring-0 border-4 border-gray-200 rounded dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 focus:border-[#eeb425] w-full min-h-[180px]">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <x-core::captcha.widgets />

                <x-theme-dusk::form.primary-button>
                    {{ __('Apply for :position', ['position' => $position->permission->rank_name]) }}
                </x-theme-dusk::form.primary-button>
            </form>
        </x-theme-dusk::content.staff-content-section>
    </div>

    <div class="col-span-12 lg:col-span-3 lg:w-[110%] space-y-4 lg:-ml-[32px]">
        <x-theme-dusk::content.content-card icon="hotel-icon" classes="border dark:border-gray-900">
            <x-slot:title>{{ __('Applying for :position', ['position' => $position->permission->rank_name]) }}</x-slot:title>
            <x-slot:under-title>{{ __('Read before applying') }}</x-slot:under-title>
            <div class="px-2 text-sm space-y-4 dark:text-gray-200">
                <p>
                    {{ __('Please fill out all the fields to apply for :position. Be honest and transparent. Providing incorrect information may lead to removal if hired.', ['position' => $position->permission->rank_name]) }}
                </p>
            </div>
        </x-theme-dusk::content.content-card>
    </div>
</x-app-layout>

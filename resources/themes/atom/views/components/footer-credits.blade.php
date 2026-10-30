@php
    $contributors = \App\Support\FooterCredits::contributors();
    $translators = \App\Support\FooterCredits::translators();
@endphp

<x-theme-atom::ui.modal name="atom-credits" :title="setting('hotel_name')" maxWidth="xl">
    <div class="space-y-6 text-sm text-gray-600 dark:text-gray-300">
        <p>
            {{ __('Thank you for playing :hotel. We have put a lot of effort into making the hotel what it is, and we truly appreciate you being here', ['hotel' => setting('hotel_name')]) }} ❤️
        </p>

        <p>
            {{ __(':hotel is driven by Atom CMS made by:', ['hotel' => setting('hotel_name')]) }}
            <a
                class="font-semibold text-blue-500 hover:underline"
                href="https://devbest.com/threads/atom-cms-a-multi-theme-cms.93034/"
                target="_blank"
                rel="noopener"
            >Object</a>
        </p>

        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {{ __('Credits:') }}
            </h4>

            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($contributors as [$name, $contribution])
                    <li class="flex flex-col gap-0.5 py-2 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                        <span class="shrink-0 font-semibold text-gray-800 dark:text-gray-100">{{ $name }}</span>
                        <span class="text-gray-500 dark:text-gray-400 sm:text-right">{{ $contribution }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {{ __('Translations') }}
            </h4>

            <ul class="grid grid-cols-1 gap-x-6 gap-y-1.5 sm:grid-cols-2">
                @foreach ($translators as $language => $names)
                    <li class="flex items-baseline justify-between gap-3">
                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $language }}</span>
                        <span class="text-right text-gray-500 dark:text-gray-400">{{ $names }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-theme-atom::ui.modal>

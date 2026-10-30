<x-app-layout>
    @push('title', __('Generator'))

    <div class="col-span-12">
        <x-theme-dusk::content.content-card icon="hotel-icon" classes="border border-gray-900">
            <x-slot:title>
                {{ __('Logo generator') }}
            </x-slot:title>

            <x-slot:under-title>
                {{ __('Generate your very own logo') }}
            </x-slot:under-title>

            <div class="px-2 text-sm text-gray-200">
                <div x-data="logoGenerator()" class="mt-4">
                    <div class="grid grid-cols-6 gap-3">
                        <div x-bind:class="{'bg-gray-200 bg-gray-900 ring-2 ring-emerald-700': fontType === 'atom'}" class="h-24 rounded border border-gray-700 p-2 flex gap-2 justify-center items-center transition duration-300 ease-in-out hover:bg-gray-200 hover:bg-gray-900 cursor-pointer" x-on:click="selectFont('atom')">
                            <img src="{{ asset('/assets/images/logo-generator/atom/a.png') }}" alt="Letter a">
                            <img src="{{ asset('/assets/images/logo-generator/atom/b.png') }}" alt="Letter b">
                            <img src="{{ asset('/assets/images/logo-generator/atom/c.png') }}" alt="Letter c">
                        </div>

                        <div x-bind:class="{'bg-gray-200 bg-gray-900 ring-2 ring-emerald-700': fontType === 'sunrise'}" class="h-24 rounded border border-gray-700 p-2 flex gap-2 justify-center items-center transition duration-300 ease-in-out hover:bg-gray-200 hover:bg-gray-900 cursor-pointer" x-on:click="selectFont('sunrise')">
                            <img src="{{ asset('/assets/images/logo-generator/sunrise/a.png') }}" alt="Letter a">
                            <img src="{{ asset('/assets/images/logo-generator/sunrise/b.png') }}" alt="Letter b">
                            <img src="{{ asset('/assets/images/logo-generator/sunrise/c.png') }}" alt="Letter c">
                        </div>

                        <div x-bind:class="{'bg-gray-200 bg-gray-900 ring-2 ring-emerald-700': fontType === 'marine'}" class="h-24 rounded border border-gray-700 p-2 flex gap-2 justify-center items-center transition duration-300 ease-in-out hover:bg-gray-200 hover:bg-gray-900 cursor-pointer" x-on:click="selectFont('marine')">
                            <img src="{{ asset('/assets/images/logo-generator/marine/a.png') }}" alt="Letter a">
                            <img src="{{ asset('/assets/images/logo-generator/marine/b.png') }}" alt="Letter b">
                            <img src="{{ asset('/assets/images/logo-generator/marine/c.png') }}" alt="Letter c">
                        </div>

                        <div x-bind:class="{'bg-gray-200 bg-gray-900 ring-2 ring-emerald-700': fontType === 'danlie'}" class="h-24 rounded border border-gray-700 p-2 flex gap-2 justify-center items-center transition duration-300 ease-in-out hover:bg-gray-200 hover:bg-gray-900 cursor-pointer" x-on:click="selectFont('danlie')">
                            <img src="{{ asset('/assets/images/logo-generator/danlie/a.png') }}" alt="Letter a">
                            <img src="{{ asset('/assets/images/logo-generator/danlie/b.png') }}" alt="Letter b">
                            <img src="{{ asset('/assets/images/logo-generator/danlie/c.png') }}" alt="Letter c">
                        </div>

                        <div x-bind:class="{'bg-gray-200 bg-gray-900 ring-2 ring-emerald-700': fontType === 'habton'}" class="h-24 rounded border border-gray-700 p-2 flex gap-2 justify-center items-center transition duration-300 ease-in-out hover:bg-gray-200 hover:bg-gray-900 cursor-pointer" x-on:click="selectFont('habton')">
                            <img src="{{ asset('/assets/images/logo-generator/habton/a.png') }}" alt="Letter a">
                            <img src="{{ asset('/assets/images/logo-generator/habton/b.png') }}" alt="Letter b">
                            <img src="{{ asset('/assets/images/logo-generator/habton/c.png') }}" alt="Letter c">
                        </div>

                        <div x-bind:class="{'bg-gray-200 bg-gray-900 ring-2 ring-emerald-700': fontType === 'habton_capitalized'}" class="h-24 rounded border border-gray-700 p-2 flex gap-2 justify-center items-center transition duration-300 ease-in-out hover:bg-gray-200 hover:bg-gray-900 cursor-pointer" x-on:click="selectFont('habton_capitalized')">
                            <img src="{{ asset('/assets/images/logo-generator/habton_capitalized/a.png') }}" alt="Letter a">
                            <img src="{{ asset('/assets/images/logo-generator/habton_capitalized/b.png') }}" alt="Letter b">
                            <img src="{{ asset('/assets/images/logo-generator/habton_capitalized/c.png') }}" alt="Letter c">
                        </div>

                        <div x-bind:class="{'bg-gray-200 bg-gray-900 ring-2 ring-emerald-700': fontType === 'habbo_modern'}" class="h-24 rounded border border-gray-700 p-2 flex gap-2 justify-center items-center transition duration-300 ease-in-out hover:bg-gray-200 hover:bg-gray-900 cursor-pointer" x-on:click="selectFont('habbo_modern')">
                            <img src="{{ asset('/assets/images/logo-generator/habbo_modern/a.png') }}" alt="Letter a">
                            <img src="{{ asset('/assets/images/logo-generator/habbo_modern/b.png') }}" alt="Letter b">
                            <img src="{{ asset('/assets/images/logo-generator/habbo_modern/c.png') }}" alt="Letter c">
                        </div>

                    </div>

                   <div class="mt-4">
                       <label for="text" class="font-bold"> {{ __('Logo text') }} </label>
                       <input x-model="text" class="mt-2 focus:ring-0 border-4  rounded bg-gray-800 border-gray-700 text-gray-200 focus:border-[#eeb425] w-full" id="text" type="text" name="text" placeholder="{{ __('Type here...') }}">
                       <div id="logoContainer" class="flex mt-4" :class="text !== '' ? 'mb-4' : ''" style="gap: 2px;" x-html="generateLogoHtml"></div>
                       <div class="flex gap-4 justify-between">
                           <button @click="generateCanvas('download')" class="w-full rounded bg-[#eeb425] text-white p-2 border-2 border-yellow-400 transition ease-in-out duration-200 hover:bg-[#d49f1c] font-semibold"> {{ __('Download logo') }} </button>
                           <button @click="generateCanvas('use')" class="w-full rounded bg-green-600 hover:bg-green-700 text-white p-2 border-2 border-green-500 transition ease-in-out duration-150 font-semibold"> {{ __('Use logo') }} </button>
                       </div>
                   </div>
                </div>
            </div>
        </x-theme-dusk::content.content-card>
    </div>

    <x-core::logo-generator-script />
</x-app-layout>


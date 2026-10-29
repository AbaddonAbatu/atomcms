@props(['placement' => 'left', 'classes' => ''])

<div
    @class([
        'w-full flex mt-3',
        'justify-start' => $placement === 'left',
        'justify-center' => $placement === 'center',
        'justify-end' => $placement === 'right',
    ])
    class="{{ $classes }}"
>
    <div class="mt-4">
        <x-core::captcha.widgets :turnstile="false" />
    </div>

    <x-core::captcha.widgets :recaptcha="false" />
</div>

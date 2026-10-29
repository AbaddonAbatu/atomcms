@props(['recaptcha' => true, 'turnstile' => true])

@if ($recaptcha && (int) setting('google_recaptcha_enabled'))
    <div class="g-recaptcha" data-sitekey="{{ config('habbo.site.recaptcha_site_key') }}"></div>
@endif

@if ($turnstile && (int) setting('cloudflare_turnstile_enabled') && ! blank(config('turnstile.turnstile_secret_key')))
    <x-turnstile />
@endif

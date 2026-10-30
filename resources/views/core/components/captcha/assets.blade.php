@props(['recaptcha' => true, 'turnstile' => true])

@if ($recaptcha && (int) setting('google_recaptcha_enabled'))
    @once
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endonce
@endif

@if ($turnstile && (int) setting('cloudflare_turnstile_enabled') && ! blank(config('turnstile.turnstile_secret_key')))
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif

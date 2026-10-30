<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body>
<h1>{{ __('Reset your password') }}</h1>

<p>
    {{ __('Click the link to reset your password:') }}
    <a href="{{ $resetUrl }}">{{ __('Reset Password') }}</a>
</p>

<p>{{ __('If you did not request this, you can ignore this email.') }}</p>
</body>
</html>

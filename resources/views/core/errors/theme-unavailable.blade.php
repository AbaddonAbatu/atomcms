<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>{{ __('Service Unavailable') }}</title>
</head>

<body style="margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #1f2937; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;">
<main style="max-width: 32rem; padding: 2rem; text-align: center;">
    <h1 style="margin: 0 0 0.75rem; font-size: 1.5rem;">{{ __('Service Unavailable') }}</h1>

    <p style="margin: 0;">{{ __('The site is temporarily unavailable. Please try again later.') }}</p>
</main>
</body>
</html>

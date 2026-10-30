<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Nitro client</title></head>
<body data-theme="aurora"><main class="aurora-frame"><h1>Nitro client</h1><p>Preparing the client session.</p><iframe title="Nitro client" src="{{ sprintf('%s/index.html?sso=%s', setting('nitro_path'), $sso) }}"></iframe></main></body>
</html>

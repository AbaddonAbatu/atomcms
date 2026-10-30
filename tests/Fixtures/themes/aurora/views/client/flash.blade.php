<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Flash client</title></head>
<body data-theme="aurora"><main class="aurora-frame"><h1>Flash client</h1><p>Preparing the client session.</p><script>var flashvars = {\"sso.ticket\": @js($sso)};</script></main></body>
</html>

@props(['title' => 'Aurora'])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    {{ theme_vite() }}
    @livewireStyles
</head>
<body class="aurora-page" data-theme="aurora">
    <header class="aurora-frame">
        <nav class="aurora-nav" aria-label="Primary navigation">
            <a href="{{ route('welcome') }}">Aurora</a>
            <a href="{{ route('article.index') }}">Articles</a>
            <a href="{{ route('help-center.index') }}">Help</a>
            <a href="{{ route('help-center.rules.index') }}">Rules</a>
            @auth
                <a href="{{ route('me.show') }}">My home</a>
                <a href="{{ route('shop.index') }}">Shop</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="aurora-button" type="submit">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}">Log in</a>
            @endauth
        </nav>
    </header>

    <main class="aurora-frame">
        @if (session('success'))
            <p class="aurora-card" role="status">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p class="aurora-card" role="alert">{{ session('error') }}</p>
        @endif
        @if ($errors->any())
            <div class="aurora-card" role="alert">
                <ul class="aurora-list">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>

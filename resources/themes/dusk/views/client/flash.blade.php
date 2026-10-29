<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />

    <title>{{ setting('hotel_name') }} - {{ __('Client') }}</title>

    <x-core::client.flash-runtime :sso="$sso">

    <link rel="stylesheet" href="{{ asset('assets/css/clientnew.css') }}" type="text/css">
    <link rel="stylesheet" href="{{ asset('assets/css/no-flash.css') }}" type="text/css">

    </x-core::client.flash-runtime>
</head>

<body>
    <div id="client">
        <habbo-client-error>
            <div class="client-error__background-frank">
                <div class="client-error__text-contents">
                    <h1 class="client-error__title">{{ __('You are nearly in Habbo!') }}</h1>
                    <p>{{ __('Click the yellow Hotel button below, then click on run flash` when prompted to. See you in the Hotel!') }}
                    </p>
                </div>
                <div class="client-error__hotel-button-div">
                    <a href="https://www.adobe.com/go/getflashplayer" target="_blank" rel="noopener noreferrer"
                        class="hotel-button">
                        <span class="hotel-button__text">{{ __('Get flash') }}</span>
                    </a>
                </div>
            </div>
        </habbo-client-error>
    </div>
</body>

</html>

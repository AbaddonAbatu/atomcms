@props(['sso'])

<script src="{{ asset('assets/js/flashclient.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/js/swfobject.js') }}"></script>

{{-- Keep the theme's stylesheets between the dependencies and startup. --}}
{{ $slot }}

<script type="text/javascript">
        var flashvars = {
            "connection.info.host": @js((string) config('habbo.flash.host')),
            "connection.info.port": @js((string) config('habbo.flash.port')),
            "site.url": "",
            "url.prefix": "",
            "client.reload.url": "",
            "client.fatal.error.url": "",
            "client.connection.failed.url": "",
            "logout.url": "",
            "client.starting": "Please wait! Habbo is starting up.",
            "client.starting.revolving": "For science, you monster\/Loading funny message\u2026please wait.\/Would you like fries with that?\/Follow the yellow duck.\/Time is just an illusion.\/Are we there yet?!\/I like your t-shirt.\/Look left. Look right. Blink twice. Ta da!\/It\'s not you, it\'s me.\/Shhh! I\'m trying to think here.\/Loading pixel universe.",
            "client.notify.cross.domain": "1",
            "client.allow.cross.domain": "1",
            "flash.client.origin": "popup",
            "processlog.enabled": "0",
            "sso.ticket": @js($sso),
            "productdata.load.url": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_productdata'))),
            "furnidata.load.url": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_furnidata'))),
            "external.texts.txt": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_texts'))),
            "external.variables.txt": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_variables'))),
            "external.figurepartlist.txt": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_figuredata'))),
            "flash.dynamic.avatar.download.configuration": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_figuremap'))),
            "external.override.texts.txt": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_override_texts'))),
            "external.override.variables.txt": @js(sprintf('%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.external_override_variables'))),
            "flash.client.url": @js(sprintf('%s/%s/%s/', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.production_folder'))),
        };

        window.FlashExternalInterface.disconnect = function() {
            window.location.href = @js(route('me.show'));
        };

        var params = {
            "base": @js(sprintf('%s/%s/%s/', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.production_folder'))),
            "allowScriptAccess": "always",
            "menu": "false",
            "wmode": "opaque"
        };

        swfobject.embedSWF(
            @js(sprintf('%s/%s/%s/%s', config('habbo.site.site_url'), config('habbo.flash.swf_base_path'), config('habbo.flash.production_folder'), config('habbo.flash.habbo_swf'))),
            'client', '100%', '100%', '11.1.0', @js(asset('assets/js/expressInstall.swf')), flashvars, params, null,
            null);
    </script>

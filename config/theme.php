<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Administrative Default Theme
    |--------------------------------------------------------------------------
    |
    | Used when the theme website setting is empty or holds the legacy value 1.
    | This administrative default is never a fallback for missing views.
    */
    'default' => 'atom',

    /*
    |--------------------------------------------------------------------------
    | Qirolab Themer Activation
    |--------------------------------------------------------------------------
    |
    | Must stay null. Themes render only through their own theme-<id>::
    | namespaces; an active or parent theme here would add the legacy search
    | paths that let views fall back to another theme or the root view tree.
    */
    'active' => null,

    'parent' => null,

    /*
    |--------------------------------------------------------------------------
    | Base Path
    |--------------------------------------------------------------------------
    |
    | The base path where all the themes are located.
    */
    'base_path' => base_path('resources/themes'),
];

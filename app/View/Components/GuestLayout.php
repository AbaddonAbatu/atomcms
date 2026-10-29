<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Unused by the bundled pages and not part of the presentation contract: a
 * theme without layouts.guest fails clearly instead of borrowing another's.
 */
class GuestLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return theme_view('layouts.guest');
    }
}

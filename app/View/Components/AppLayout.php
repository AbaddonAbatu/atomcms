<?php

namespace App\View\Components;

use App\Models\Miscellaneous\WebsiteLanguage;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return theme_view('layouts.app', [
            'languages' => WebsiteLanguage::query()->get(),
        ]);
    }
}

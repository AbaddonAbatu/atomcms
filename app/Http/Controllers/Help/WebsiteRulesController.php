<?php

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Services\Help\TicketService;
use Illuminate\Contracts\View\View;

class WebsiteRulesController extends Controller
{
    public function __invoke(): View
    {
        return theme_view('rules', [
            'categories' => app(TicketService::class)->rules(),
        ]);
    }
}

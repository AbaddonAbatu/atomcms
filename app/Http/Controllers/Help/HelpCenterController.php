<?php

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Services\Help\TicketService;
use Illuminate\Contracts\View\View;

class HelpCenterController extends Controller
{
    public function __invoke(): View
    {
        return theme_view('help-center.index', [
            'categories' => app(TicketService::class)->categories(),
        ]);
    }
}

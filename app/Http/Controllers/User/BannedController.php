<?php

namespace App\Http\Controllers\User;

use App\Emulator\Contracts\BanRepository;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class BannedController extends Controller
{
    public function __invoke(BanRepository $bans): View
    {
        $user = Auth::user();

        $ban = $bans->activeIpBan((string) request()->ip())
            ?? ($user !== null ? $bans->activeAccountBan($user) : null);

        return theme_view('banned', [
            'ban' => $ban,
        ]);
    }
}

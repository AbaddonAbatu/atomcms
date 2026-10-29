<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthPageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class UserReferralController extends Controller
{
    public function __invoke(User $user, AuthPageService $authPages): View|RedirectResponse
    {
        if (setting('disable_registration') === '1') {
            return to_route('welcome')->withErrors(['register' => __('Registration is currently disabled.')]);
        }

        return theme_view('auth.register', $authPages->registrationData($user->referral_code));
    }
}

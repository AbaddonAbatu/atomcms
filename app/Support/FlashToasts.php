<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\ViewErrorBag;

final class FlashToasts
{
    /**
     * @return list<array{title: string, icon: string}>
     */
    public static function from(Session $session, ViewErrorBag $errors): array
    {
        $toasts = [];

        if ($session->has('message')) {
            $toasts[] = ['title' => $session->get('message'), 'icon' => 'error'];
        }

        foreach ($errors->all() as $error) {
            $toasts[] = ['title' => $error, 'icon' => 'error'];
        }

        if ($errors->hasBag('login')) {
            foreach ($errors->getBag('login')->all() as $error) {
                $toasts[] = ['title' => $error, 'icon' => 'error'];
            }
        }

        if ($session->has('success')) {
            $toasts[] = ['title' => $session->get('success'), 'icon' => 'success'];
        }

        return $toasts;
    }
}

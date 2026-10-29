<?php

namespace App\Services\Auth;

use App\Models\Articles\WebsiteArticle;
use App\Models\Miscellaneous\CameraWeb;
use App\Services\Community\CameraService;
use Illuminate\Database\Eloquent\Collection;

class AuthPageService
{
    public function __construct(private readonly CameraService $camera) {}

    /**
     * The view data of the login page.
     *
     * @return array{articles: Collection<int, WebsiteArticle>, photos: Collection<int, CameraWeb>}
     */
    public function loginData(): array
    {
        return $this->sidebarData(4, 4);
    }

    /**
     * The view data of the registration page, with the referral code the form carries.
     *
     * @return array{referral_code: string|null, articles: Collection<int, WebsiteArticle>, photos: Collection<int, CameraWeb>}
     */
    public function registrationData(?string $referralCode): array
    {
        return [
            'referral_code' => $referralCode,
            ...$this->sidebarData(4, 2),
        ];
    }

    /**
     * Latest articles and camera photos shown alongside the auth forms.
     *
     * @return array{articles: Collection<int, WebsiteArticle>, photos: Collection<int, CameraWeb>}
     */
    private function sidebarData(int $articles, int $photos): array
    {
        return [
            'articles' => WebsiteArticle::latest('id')->take($articles)->has('user')->with('user:id,username,look')->get(),
            'photos' => $this->camera->latestPhotos($photos),
        ];
    }
}

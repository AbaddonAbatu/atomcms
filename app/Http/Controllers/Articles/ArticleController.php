<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleReactionFormRequest;
use App\Models\Articles\WebsiteArticle;
use App\Models\User;
use App\Services\Articles\ArticleService;
use App\Services\Articles\ReactionService;
use App\Support\AuthenticatedUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articlesService,
        private readonly ReactionService $reactionService,
    ) {}

    public function index(): View
    {
        $articles = $this->articlesService->getArticles(true);

        return theme_view('community.articles', [
            'articles' => $articles,
        ]);
    }

    public function show(WebsiteArticle $article): View
    {
        $user = Auth::user();
        $article = $this->articlesService->loadForDisplay($article);

        return theme_view('community.article', [
            'article' => $article,
            'authorHome' => $this->articlesService->authorHome($article),
            'otherArticles' => $this->articlesService->otherArticles($article),
            'myReactions' => $user instanceof User ? $this->reactionService->reactionsFor($article, $user) : collect(),
            'articleReactions' => $this->reactionService->countsFor($article),
        ]);
    }

    public function toggleReaction(WebsiteArticle $article, ToggleReactionFormRequest $request): JsonResponse
    {
        $response = $this->reactionService->toggleReaction(
            $article,
            AuthenticatedUser::from($request),
            $request->validated('reaction'),
        );

        return response()->json($response);
    }
}

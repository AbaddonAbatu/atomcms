<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\RareSearchFormRequest;
use App\Models\Community\RareValue\WebsiteRareValue;
use App\Models\Community\RareValue\WebsiteRareValueCategory;
use App\Services\Community\RareValues\RareValueCategoriesService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class WebsiteRareValuesController extends Controller
{
    public function __construct(
        private readonly RareValueCategoriesService $valueCategoriesService,
    ) {}

    public function index(): View
    {
        return theme_view('rare-values', [
            'categories' => $this->valueCategoriesService->fetchCategoriesByPriority(),
            'categoriesNav' => $this->valueCategoriesService->fetchAllCategories(),
        ]);
    }

    public function category(WebsiteRareValueCategory $category): View
    {
        $category->load('furniture');

        return theme_view('rare-values', [
            'categories' => $category,
            'categoriesNav' => $this->valueCategoriesService->fetchAllCategories(),
        ]);
    }

    public function search(RareSearchFormRequest $request): View|RedirectResponse
    {
        $searchTerm = $request->input('search');

        $categories = $this->valueCategoriesService->searchCategories($searchTerm);

        if ($categories->isEmpty()) {
            return redirect()->back()->withErrors([
                'message' => __('It seems like there were no rares matching your search input'),
            ]);
        }

        return theme_view('rare-values', [
            'categories' => $categories,
            'categoriesNav' => WebsiteRareValueCategory::has('furniture')->get(),
        ]);
    }

    public function value(WebsiteRareValue $value): View
    {
        return theme_view('value', [
            'value' => $value,
            'items' => $this->valueCategoriesService->itemsPerUser($value),
        ]);
    }
}

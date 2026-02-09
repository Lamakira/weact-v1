<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleCategoryRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\Admin\ArticleService;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService
    ) {}

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $article = $this->articleService->createArticle(
            $request->user(),
            $request->validated(),
            $request->file('featured_image')
        );

        $article->load('admin');

        return response()->json([
            'data' => new ArticleResource($article),
            'message' => 'Article créé avec succès',
            'meta' => [],
        ], 201);
    }

    public function updateCategory(UpdateArticleCategoryRequest $request, Article $article): JsonResponse
    {
        $article = $this->articleService->updateCategory(
            $article,
            $request->validated()['category']
        );

        $article->load('admin');

        return response()->json([
            'data' => new ArticleResource($article),
            'message' => "Catégorie de l'article mise à jour avec succès",
            'meta' => [],
        ], 200);
    }
}

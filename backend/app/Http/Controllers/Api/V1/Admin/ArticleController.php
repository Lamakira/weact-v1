<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexArticleRequest;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleCategoryRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Http\Requests\Admin\UpdateArticleStatusRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Admin;
use App\Models\Article;
use App\Services\Admin\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService
    ) {}

    /**
     * List all articles with pagination, search, and filters.
     */
    public function index(IndexArticleRequest $request): JsonResponse
    {
        $articles = $this->articleService->listArticles($request->validated());

        return response()->json([
            'data' => ArticleResource::collection($articles),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
            'message' => 'Articles récupérés avec succès',
        ]);
    }

    /**
     * Show a single article.
     */
    public function show(Request $request, Article $article): JsonResponse
    {
        if (! $request->user() instanceof Admin) {
            abort(403);
        }

        $article->load('admin');

        return response()->json([
            'data' => new ArticleResource($article),
            'message' => 'Article récupéré avec succès',
        ]);
    }

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

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $article = $this->articleService->updateArticle(
            $article,
            $request->validated(),
            $request->file('featured_image')
        );

        $article->load('admin');

        return response()->json([
            'data' => new ArticleResource($article),
            'message' => 'Article mis à jour avec succès',
            'meta' => [],
        ], 200);
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

    public function destroy(Request $request, Article $article): JsonResponse
    {
        if (! $request->user() instanceof Admin) {
            abort(403);
        }

        $this->articleService->deleteArticle($article);

        return response()->json([
            'message' => 'Article supprimé avec succès',
        ], 200);
    }

    public function updateStatus(UpdateArticleStatusRequest $request, Article $article): JsonResponse
    {
        $article = $this->articleService->updateStatus(
            $article,
            $request->validated()['status']
        );

        $article->load('admin');

        return response()->json([
            'data' => new ArticleResource($article),
            'message' => "Statut de l'article mis à jour avec succès",
            'meta' => [],
        ], 200);
    }
}

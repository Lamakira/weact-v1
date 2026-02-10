<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListPublicArticlesRequest;
use App\Http\Resources\PublicArticleDetailResource;
use App\Http\Resources\PublicArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    /**
     * Display a paginated list of public Articles.
     *
     * No authentication required - this is a PUBLIC endpoint.
     * Only returns articles with status = published.
     */
    public function index(ListPublicArticlesRequest $request): JsonResponse
    {
        $perPage = $request->getPerPage();

        $articles = Article::published()
            ->with('admin')
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => PublicArticleResource::collection($articles),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
            'message' => 'Articles retrieved successfully',
        ]);
    }

    /**
     * Display a single public Article detail.
     *
     * No authentication required - this is a PUBLIC endpoint.
     * Only returns articles with status = published.
     * Draft articles return 404.
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::published()
            ->with('admin')
            ->where('slug', $slug)
            ->first();

        if (! $article) {
            return response()->json([
                'error' => [
                    'code' => 'ARTICLE_NOT_FOUND',
                    'message' => 'Article non trouvé',
                ],
            ], 404);
        }

        return response()->json([
            'data' => new PublicArticleDetailResource($article),
            'message' => 'Article retrieved successfully',
        ]);
    }
}

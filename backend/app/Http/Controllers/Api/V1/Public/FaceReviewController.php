<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Face;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FaceReviewController extends Controller
{
    /**
     * List reviews for a Face.
     *
     * Returns paginated list of reviews received by the Face,
     * ordered by most recent first.
     */
    public function index(int $id): AnonymousResourceCollection
    {
        $face = Face::findOrFail($id);

        $reviews = $face->ratingsReceived()
            ->with('rater.userable')
            ->orderByDesc('created_at')
            ->paginate(10);

        return ReviewResource::collection($reviews);
    }
}

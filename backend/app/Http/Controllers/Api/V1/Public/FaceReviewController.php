<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class FaceReviewController extends Controller
{
    /**
     * List reviews for a Face.
     *
     * Merges candidature ratings (ratings table) and booking ratings
     * (booking_ratings table), ordered by most recent first.
     */
    public function index(Request $request, int $id): AnonymousResourceCollection
    {
        $face = Face::findOrFail($id);
        $perPage = 10;
        $page = $request->integer('page', 1);

        $candidatureRatings = $face->ratingsReceived()->with('rater.userable')->get();
        $bookingRatings = $face->bookingRatingsReceived()->with('rater.userable')->get();

        $all = $candidatureRatings->merge($bookingRatings)
            ->sortByDesc('created_at')
            ->values();

        $paginator = new LengthAwarePaginator(
            $all->forPage($page, $perPage),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return ReviewResource::collection($paginator);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Producer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProducerReviewController extends Controller
{
    /**
     * List reviews for a Producer.
     *
     * Returns paginated list of reviews received by the Producer,
     * ordered by most recent first.
     */
    public function index(Producer $producer): AnonymousResourceCollection
    {
        // Same visibility rule as the profile endpoint: a deactivated account's
        // reviews are no longer public.
        abort_unless((bool) data_get($producer->loadMissing('user'), 'user.is_active'), 404);

        $reviews = $producer->ratingsReceived()
            ->with('rater.userable')
            ->orderByDesc('created_at')
            ->paginate(10);

        return ReviewResource::collection($reviews);
    }
}

<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Query-time rating aggregates for Face and Producer.
 *
 * The rating accessors (average_rating, ratings_count) combine two sources:
 * candidature ratings (ratingsReceived) and booking ratings
 * (bookingRatingsReceived). Computed lazily they cost 2 queries each per model;
 * on a listing that is an N+1. withRatingAggregates() pulls the four numbers in
 * the listing query, and the accessors read them when present
 * (same formula, same rounding), falling back to the lazy queries otherwise.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasRatingAggregates
{
    /**
     * Add the four rating aggregates to the query.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithRatingAggregates(Builder $query): void
    {
        $query
            ->withSum('ratingsReceived as rating_candidature_sum', 'score')
            ->withCount('ratingsReceived as rating_candidature_count')
            ->withSum('bookingRatingsReceived as rating_booking_sum', 'score')
            ->withCount('bookingRatingsReceived as rating_booking_count');
    }

    /**
     * Whether the aggregates were pre-loaded.
     */
    protected function hasRatingAggregates(): bool
    {
        return array_key_exists('rating_candidature_count', $this->attributes)
            && array_key_exists('rating_booking_count', $this->attributes);
    }

    /**
     * Raw score sum and count across both rating sources.
     *
     * @return array{sum: float, count: int}
     */
    protected function ratingTotals(): array
    {
        if ($this->hasRatingAggregates()) {
            return [
                'sum' => (float) ($this->attributes['rating_candidature_sum'] ?? 0)
                    + (float) ($this->attributes['rating_booking_sum'] ?? 0),
                'count' => (int) $this->attributes['rating_candidature_count']
                    + (int) $this->attributes['rating_booking_count'],
            ];
        }

        $candidatureRatings = $this->ratingsReceived()->selectRaw('COALESCE(SUM(score), 0) as score_sum, COUNT(*) as score_count')->first();
        $bookingRatings = $this->bookingRatingsReceived()->selectRaw('COALESCE(SUM(score), 0) as score_sum, COUNT(*) as score_count')->groupBy('users.userable_id')->first();

        return [
            'sum' => (float) data_get($candidatureRatings, 'score_sum', 0.0)
                + (float) data_get($bookingRatings, 'score_sum', 0.0),
            'count' => (int) data_get($candidatureRatings, 'score_count', 0)
                + (int) data_get($bookingRatings, 'score_count', 0),
        ];
    }

    /**
     * Number of ratings per score (5 down to 1), over the same two sources as
     * ratingTotals() so the distribution always sums to ratings_count.
     * Two grouped queries, whatever the volume.
     *
     * @return array<int, int>
     */
    public function ratingDistribution(): array
    {
        $candidatureCounts = $this->ratingsReceived()
            ->selectRaw('ratings.score as score, COUNT(*) as score_count')
            ->groupBy('ratings.score')
            ->pluck('score_count', 'score');

        $bookingCounts = $this->bookingRatingsReceived()
            ->selectRaw('booking_ratings.score as score, COUNT(*) as score_count')
            ->groupBy('booking_ratings.score')
            ->pluck('score_count', 'score');

        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $score) {
            $distribution[$score] = (int) ($candidatureCounts[$score] ?? 0) + (int) ($bookingCounts[$score] ?? 0);
        }

        return $distribution;
    }
}

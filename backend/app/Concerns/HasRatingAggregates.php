<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query-time rating aggregates for Face and Producer.
 *
 * The rating accessors (average_rating, ratings_count) combine two sources:
 * candidature ratings (ratingsReceived) and booking ratings
 * (bookingRatingsReceived). Computed lazily they cost 2 queries each per model;
 * on a listing that is an N+1. withRatingAggregates() / loadRatingAggregates()
 * pull the four numbers in bulk, and the accessors read them when present
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
     * Load the four rating aggregates on models already fetched (single
     * homogeneous collection of this model). No-op on an empty collection.
     *
     * @param  Collection<int, static>  $models
     */
    public static function loadRatingAggregates(Collection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $models->loadSum('ratingsReceived as rating_candidature_sum', 'score');
        $models->loadCount('ratingsReceived as rating_candidature_count');
        $models->loadSum('bookingRatingsReceived as rating_booking_sum', 'score');
        $models->loadCount('bookingRatingsReceived as rating_booking_count');
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
}

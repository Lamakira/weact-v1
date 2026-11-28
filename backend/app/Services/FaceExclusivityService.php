<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceSubscriptionTier;
use App\Models\Face;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Exclusivité Élite (ADR 0001) : l'admin accorde au plus MAX_SLOTS
 * exclusivités, uniquement à des Faces Élite en direct.
 */
class FaceExclusivityService
{
    public const MAX_SLOTS = 4;

    /**
     * Global mutex: every grant is serialized, whatever the number of rows
     * already flagged. A row lock on the flagged Faces alone would not cover
     * the row being added (two concurrent grants would both count 3).
     */
    public const LOCK_KEY = 'face_exclusivity_grant';

    private const LOCK_TTL_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 2;

    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * Number of Faces currently carrying the flag (effective or not): this is
     * what the MAX_SLOTS limit counts.
     */
    public function slotsTaken(): int
    {
        return Face::query()->exclusive()->count();
    }

    /**
     * The one rule deciding whether an exclusivity is effective: the Face is
     * Élite LIVE (active subscription), whatever the flag says.
     */
    public function isEliteLive(Face $face): bool
    {
        return $this->entitlement->capabilities($face)->tier === FaceSubscriptionTier::Elite;
    }

    /**
     * Effective exclusivities: flagged Faces that are Élite LIVE. A flag left
     * on a Face that lost the Élite tier is ignored here until the hourly job
     * drops it. Read by the nightly ranking AND by every carousel tick (a
     * small query: at most a handful of flagged rows).
     *
     * @return list<int> face IDs, ascending
     */
    public function effectiveExclusiveIds(): array
    {
        return Face::query()
            ->exclusive()
            ->with('activeSubscription')
            ->orderBy('id')
            ->get(['id'])
            ->filter(fn (Face $face): bool => $this->isEliteLive($face))
            ->map(fn (Face $face): int => (int) $face->id)
            ->values()
            ->all();
    }

    /**
     * Drop the flag from every Face that is no longer Élite live.
     *
     * Conditional write: a concurrent run or admin edit that already cleared
     * the flag affects 0 rows, so that Face is not reported (no duplicate
     * mail from the caller).
     *
     * @return list<array{face: Face, tier: FaceSubscriptionTier}> the Faces actually released, with their live tier
     */
    public function releaseLostExclusivities(): array
    {
        $released = [];

        Face::query()
            ->exclusive()
            ->orderBy('id')
            ->each(function (Face $face) use (&$released): void {
                if ($this->isEliteLive($face)) {
                    return;
                }

                $cleared = Face::query()
                    ->whereKey($face->id)
                    ->exclusive()
                    ->update(['is_exclusive' => false]);

                if ($cleared === 0) {
                    return;
                }

                $tier = $this->entitlement->capabilities($face)->tier;
                Log::info('Face exclusivity released: no longer Elite', [
                    'face_id' => $face->id,
                    'tier' => $tier->value,
                ]);
                $released[] = ['face' => $face, 'tier' => $tier];
            });

        return $released;
    }

    /**
     * Apply an admin update. Any request setting the exclusivity to true runs
     * under the global lock, in the same transaction as the write; the Élite
     * and capacity checks apply unless the Face is already exclusive (read
     * under the lock). Any other update (including revoking) is written as is.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException when the grant is refused
     */
    public function updateFace(Face $face, array $validated): void
    {
        // The `boolean` rule also accepts 1 / "1" without casting: normalize,
        // so every "true" request goes through the lock and the checks.
        $requestsTrue = array_key_exists('is_exclusive', $validated)
            && filter_var($validated['is_exclusive'], FILTER_VALIDATE_BOOLEAN);

        if (! $requestsTrue) {
            $face->update($validated);

            return;
        }

        try {
            Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                fn () => DB::transaction(function () use ($face, $validated): void {
                    /** @var Face $locked */
                    $locked = Face::query()->lockForUpdate()->findOrFail($face->id);

                    // Only the state read under the lock may skip the checks:
                    // the route-bound model can be stale.
                    if (! $locked->is_exclusive) {
                        $this->assertCanGrant($locked);
                    }

                    $locked->update(['is_exclusive' => true] + $validated);
                }),
            );
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'is_exclusive' => 'Une autre exclusivité Élite est en cours d\'attribution. Réessayez dans un instant.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertCanGrant(Face $face): void
    {
        if (! $this->isEliteLive($face)) {
            throw ValidationException::withMessages([
                'is_exclusive' => 'L\'exclusivité Élite est réservée aux Faces Élite.',
            ]);
        }

        $taken = Face::query()->exclusive()->lockForUpdate()->count();

        if ($taken >= self::MAX_SLOTS) {
            throw ValidationException::withMessages([
                'is_exclusive' => 'Les '.self::MAX_SLOTS.' places d\'exclusivité Élite sont prises. Retirez d\'abord une exclusivité.',
            ]);
        }
    }
}

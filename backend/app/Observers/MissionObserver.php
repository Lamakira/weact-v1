<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\MissionStatus;
use App\Events\MissionPublished;
use App\Models\Mission;
use WeakMap;

/**
 * Detects the FIRST publication of a mission whatever the code path
 * (service, payment webhook, reconcile command): status becomes Published while
 * `published_at` is still null. `published_at` is set in `saving` (same write,
 * no extra save, no loop) and the event is dispatched once the save succeeded.
 */
class MissionObserver
{
    /**
     * Static : Laravel resolves a fresh observer instance per model event, so the
     * `saving` -> `saved` hand-off cannot live on the instance.
     *
     * @var WeakMap<Mission, true>|null
     */
    private static ?WeakMap $firstPublication = null;

    public function saving(Mission $mission): void
    {
        if ($mission->status === MissionStatus::Published && $mission->published_at === null) {
            $mission->published_at = now();
            $pending = self::pending();
            $pending[$mission] = true;
        }
    }

    public function saved(Mission $mission): void
    {
        $pending = self::pending();
        if (isset($pending[$mission])) {
            unset($pending[$mission]);
            MissionPublished::dispatch($mission);
        }
    }

    /** @return WeakMap<Mission, true> */
    private static function pending(): WeakMap
    {
        return self::$firstPublication ??= new WeakMap;
    }
}

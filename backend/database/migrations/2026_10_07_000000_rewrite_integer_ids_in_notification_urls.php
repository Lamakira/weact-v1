<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Route binding is uuid-only now (HasRouteUuid no longer falls back to the
 * integer primary key). Stored in-app notifications created before that carry
 * links such as `/producer/missions/42/candidatures`: rewrite the integer id to
 * the uuid so they keep working.
 *
 * Idempotent (a uuid segment never matches `\d+`) and chunked. An id that no
 * longer maps to a row is left untouched.
 */
return new class extends Migration
{
    private const CHUNK_SIZE = 200;

    /**
     * Segment of the SPA url => table holding the uuid.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'missions' => 'missions',
        'bookings' => 'bookings',
        'candidatures' => 'candidatures',
    ];

    public function up(): void
    {
        DB::table('notifications')
            ->where(function ($query): void {
                foreach (array_keys(self::TABLES) as $segment) {
                    $query->orWhere('data', 'like', "%{$segment}%");
                }
            })
            ->chunkById(self::CHUNK_SIZE, function ($notifications): void {
                foreach ($notifications as $notification) {
                    $data = json_decode((string) $notification->data, true);

                    if (! is_array($data) || ! is_string($data['url'] ?? null)) {
                        continue;
                    }

                    $rewritten = $this->rewrite($data['url']);

                    if ($rewritten === $data['url']) {
                        continue;
                    }

                    $data['url'] = $rewritten;

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible on purpose: the integer form no longer resolves.
    }

    private function rewrite(string $url): string
    {
        if (preg_match('#^/(producer|face)/(missions|bookings|candidatures)/(\d+)(/.*)?$#', $url, $m) !== 1) {
            return $url;
        }

        $uuid = DB::table(self::TABLES[$m[2]])->where('id', (int) $m[3])->value('uuid');

        if (! is_string($uuid) || $uuid === '') {
            return $url;
        }

        return "/{$m[1]}/{$m[2]}/{$uuid}".($m[4] ?? '');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Some Face notifications (shooting-day reminder, mission closed) linked to
 * `/face/candidatures/{candidature uuid}`, a route the SPA does not have. Rewrite
 * the stored links to the mission page `/face/missions/{mission uuid}`.
 *
 * Idempotent (a rewritten url no longer matches) and chunked. A candidature that
 * no longer exists falls back to the candidatures list `/face/candidatures`.
 */
return new class extends Migration
{
    private const CHUNK_SIZE = 200;

    public function up(): void
    {
        DB::table('notifications')
            ->where('data', 'like', '%/face/candidatures/%')
            ->chunkById(self::CHUNK_SIZE, function ($notifications): void {
                foreach ($notifications as $notification) {
                    $data = json_decode((string) $notification->data, true);

                    if (! is_array($data) || ! is_string($data['url'] ?? null)) {
                        continue;
                    }

                    if (preg_match('#^/face/candidatures/([0-9a-fA-F-]{36})$#', $data['url'], $m) !== 1) {
                        continue;
                    }

                    $missionUuid = DB::table('candidatures')
                        ->join('missions', 'missions.id', '=', 'candidatures.mission_id')
                        ->where('candidatures.uuid', $m[1])
                        ->value('missions.uuid');

                    $data['url'] = $missionUuid !== null
                        ? "/face/missions/{$missionUuid}"
                        : '/face/candidatures';

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Irréversible et sans objet : l'ancien lien ne menait à aucune page.
    }
};

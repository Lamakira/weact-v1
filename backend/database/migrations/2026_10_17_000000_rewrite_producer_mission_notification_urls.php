<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il n'existe pas de page détail Producteur : `/producer/missions/{uuid}` est
 * redirigé vers l'édition. Les notifications déjà stockées avec ce lien (ex.
 * `mission_completed_producer`) sont réécrites vers la page des candidatures.
 *
 * Idempotent (le nouveau lien ne correspond plus au motif) et chunké.
 */
return new class extends Migration
{
    private const CHUNK_SIZE = 200;

    public function up(): void
    {
        DB::table('notifications')
            ->where('data', 'like', '%producer%missions%')
            ->chunkById(self::CHUNK_SIZE, function ($notifications): void {
                foreach ($notifications as $notification) {
                    $data = json_decode((string) $notification->data, true);

                    if (! is_array($data) || ! is_string($data['url'] ?? null)) {
                        continue;
                    }

                    if (preg_match('#^/producer/missions/([0-9a-fA-F-]{36})$#', $data['url'], $m) !== 1) {
                        continue;
                    }

                    $data['url'] = "/producer/missions/{$m[1]}/candidatures";

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Irréversible volontairement : l'ancien lien redirigeait vers l'édition.
    }
};

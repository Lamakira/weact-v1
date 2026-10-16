<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\Candidature;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `/face/candidatures/{uuid}` n'est pas une route de la SPA : les notifications Face
 * pointent vers la page de la mission, et les liens déjà stockés sont réécrits.
 */
class FaceCandidatureNotificationUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_rewrites_candidature_links_to_the_mission_page(): void
    {
        $candidature = Candidature::factory()->create();
        $mission = $candidature->mission;
        $userId = User::factory()->create()->id;

        $make = fn (string $url): int => Notification::query()->create([
            'user_id' => $userId,
            'type' => 'shooting_day_reminder',
            'data' => ['message' => 'x', 'url' => $url],
        ])->id;

        $known = $make("/face/candidatures/{$candidature->uuid}");
        $orphan = $make('/face/candidatures/'.Str::uuid()->toString());
        $list = $make('/face/candidatures');
        $other = $make("/face/missions/{$mission->uuid}");

        $migration = require database_path('migrations/2026_10_17_000001_rewrite_face_candidature_notification_urls.php');
        $migration->up();
        $migration->up();

        $url = fn (int $id): string => Notification::query()->findOrFail($id)->data['url'];

        $this->assertSame("/face/missions/{$mission->uuid}", $url($known));
        $this->assertSame('/face/candidatures', $url($orphan));
        $this->assertSame('/face/candidatures', $url($list));
        $this->assertSame("/face/missions/{$mission->uuid}", $url($other));
    }
}

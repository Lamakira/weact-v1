<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerMissionNotificationUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_notification_links_to_candidatures_page(): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        $mission = Mission::factory()->closed()->create(['producer_id' => $producer->id]);

        app(MissionService::class)->notifyProducerOnCompletion($mission);

        $notification = Notification::where('user_id', $user->id)->where('type', 'mission_completed_producer')->firstOrFail();
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=candidatures", $notification->data['url']);
        // Lot C8 : la notification invite (sans relance) à noter les Faces, de façon facultative.
        $this->assertStringContainsString('noter vos Faces', $notification->data['message']);
        $this->assertStringContainsString('facultatif', $notification->data['message']);
    }

    public function test_migration_rewrites_bare_mission_urls_only(): void
    {
        $user = User::factory()->create();
        $uuid = '11111111-1111-4111-8111-111111111111';

        $make = fn (string $url): int => Notification::create([
            'user_id' => $user->id,
            'type' => 'x',
            'data' => ['message' => 'm', 'url' => $url],
        ])->id;

        $bare = $make("/producer/missions/{$uuid}");
        $edit = $make("/producer/missions/{$uuid}/edit");
        $cand = $make("/producer/missions/{$uuid}/candidatures");
        $list = $make('/producer/missions');
        $face = $make("/face/missions/{$uuid}");

        $migration = require database_path('migrations/2026_10_17_000000_rewrite_producer_mission_notification_urls.php');
        $migration->up();
        $migration->up();

        $url = fn (int $id): string => Notification::findOrFail($id)->data['url'];

        $this->assertSame("/producer/missions/{$uuid}/candidatures", $url($bare));
        $this->assertSame("/producer/missions/{$uuid}/edit", $url($edit));
        $this->assertSame("/producer/missions/{$uuid}/candidatures", $url($cand));
        $this->assertSame('/producer/missions', $url($list));
        $this->assertSame("/face/missions/{$uuid}", $url($face));
    }
}

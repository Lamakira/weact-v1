<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Events\BookingMessageSent;
use App\Events\UgcMissionDealAccepted;
use App\Http\Resources\BookingMessageResource;
use App\Http\Resources\UserResource;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonalDataExposureWave2Test extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create([
            'nom' => 'Kouassi',
            'prenom' => 'Aïcha',
            'username' => 'aichak',
            'show_age' => false,
            'date_naissance' => now()->subYears(16)->format('Y-m-d'),
        ]);
        $this->faceUser = User::factory()->create([
            'email' => 'face.secret@example.test',
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'email' => 'producer.secret@example.test',
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    // === 2 : recherche publique sur le nom de famille ===

    public function test_public_search_does_not_match_on_last_name(): void
    {
        $this->getJson('/api/v1/public/faces?search=Kouassi')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/public/faces?search=Aïcha')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // === 3 : URLs construites avec un id entier ===

    public function test_candidature_notification_urls_use_the_mission_uuid(): void
    {
        $mission = Mission::factory()->published()->forAll()->create([
            'producer_id' => $this->producer->id,
            'date_limite_candidature' => now()->addDays(7),
        ]);
        $this->face->update(['date_naissance' => now()->subYears(30)->format('Y-m-d')]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply", ['message_motivation' => 'Motivée'])
            ->assertCreated();

        $notification = Notification::query()->where('user_id', $this->producerUser->id)->where('type', 'new_candidature')->firstOrFail();
        $this->assertSame("/producer/missions/{$mission->uuid}/candidatures", $notification->data['url']);
    }

    public function test_ugc_deal_accepted_notification_url_uses_the_mission_uuid(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->create(['face_id' => $this->face->id, 'mission_id' => $mission->id]);

        event(new UgcMissionDealAccepted($candidature));

        $notification = Notification::query()->where('type', 'ugc_deal_accepted')->firstOrFail();
        $this->assertSame("/producer/missions/{$mission->uuid}/candidatures", $notification->data['url']);
    }

    public function test_admin_dashboard_links_use_uuids(): void
    {
        $admin = Admin::factory()->create();
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $article = Article::factory()->published()->create(['admin_id' => $admin->id]);

        $links = collect(
            $this->withToken($admin->createToken('t', [Admin::ABILITY_TWO_FACTOR])->plainTextToken)
                ->getJson('/api/v1/admin/dashboard/recent-activity')
                ->assertOk()
                ->json('data')
        )->pluck('link')->all();

        $this->assertContains("/admin/missions/{$mission->uuid}", $links);
        $this->assertContains("/admin/articles/{$article->uuid}/edit", $links);
        $this->assertTrue(
            collect($links)->contains("/admin/faces/{$this->face->uuid}") || collect($links)->contains("/admin/producers/{$this->producer->uuid}"),
        );
        foreach ($links as $link) {
            $this->assertDoesNotMatchRegularExpression('#/\d+(/|$)#', $link);
        }
    }

    public function test_notification_migration_rewrites_integer_ids_to_uuids_idempotently(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->create(['face_id' => $this->face->id, 'mission_id' => $mission->id]);
        $booking = Booking::factory()->pending()->create(['face_id' => $this->faceUser->id, 'producer_id' => $this->producerUser->id]);

        $make = fn (string $url): int => Notification::create([
            'user_id' => $this->producerUser->id,
            'type' => 'x',
            'data' => ['message' => 'm', 'url' => $url, 'mission_id' => $mission->id],
        ])->id;

        $a = $make("/producer/missions/{$mission->id}/candidatures");
        $b = $make("/face/bookings/{$booking->id}");
        $c = $make("/face/candidatures/{$candidature->id}");
        $unknown = $make('/producer/missions/99999/candidatures');
        $already = $make("/producer/missions/{$mission->uuid}/candidatures");
        $static = $make('/face/candidatures');

        $migration = require database_path('migrations/2026_10_07_000000_rewrite_integer_ids_in_notification_urls.php');
        $migration->up();
        $migration->up();

        $url = fn (int $id): string => Notification::query()->findOrFail($id)->data['url'];

        $this->assertSame("/producer/missions/{$mission->uuid}/candidatures", $url($a));
        $this->assertSame("/face/bookings/{$booking->uuid}", $url($b));
        $this->assertSame("/face/candidatures/{$candidature->uuid}", $url($c));
        $this->assertSame('/producer/missions/99999/candidatures', $url($unknown));
        $this->assertSame("/producer/missions/{$mission->uuid}/candidatures", $url($already));
        $this->assertSame('/face/candidatures', $url($static));
        $this->assertSame($mission->id, Notification::query()->findOrFail($a)->data['mission_id']);
        $this->assertSame(6, DB::table('notifications')->count());
    }

    // === 4 : nom d'expéditeur du chat ===

    public function test_chat_sender_name_never_falls_back_to_email(): void
    {
        $producerUser = User::factory()->create(['email' => 'nameless@example.test', 'userable_type' => null, 'userable_id' => null]);
        $booking = Booking::factory()->pending()->create(['face_id' => $this->faceUser->id, 'producer_id' => $this->producerUser->id]);
        $message = BookingMessage::factory()->create(['booking_id' => $booking->id, 'sender_id' => $producerUser->id]);
        $message->load('sender');

        $resource = (new BookingMessageResource($message))->toArray(request());
        $this->assertSame('Utilisateur', $resource['sender_name']);

        $broadcast = (new BookingMessageSent($message))->broadcastWith();
        $this->assertSame('Utilisateur', $broadcast['sender_name']);
        $this->assertStringNotContainsString('nameless@example.test', json_encode([$resource, $broadcast]));
    }

    // === 5 : contexte propriétaire propagé à la ressource imbriquée ===

    public function test_owner_context_reaches_the_nested_profile(): void
    {
        $this->faceUser->load('userable');
        $request = request();
        $request->setUserResolver(fn () => null);

        $nested = UserResource::forOwner($this->faceUser)->toArray($request)['userable'];
        $this->assertSame(16, $nested->resolve($request)['age']);

        $notOwner = (new UserResource($this->faceUser))->toArray($request)['userable'];
        $this->assertNull($notOwner->resolve($request)['age']);

        $this->producerUser->load('userable');
        $this->producerUser->userable->update(['whatsapp_number' => '+22961000000']);
        $ownerProducer = UserResource::forOwner($this->producerUser->fresh('userable'))->toArray($request)['userable'];
        $this->assertArrayHasKey('whatsapp_number', $ownerProducer->resolve($request));
    }

    public function test_register_response_keeps_owner_only_fields_of_the_nested_profile(): void
    {
        $response = $this->postJson('/api/v1/auth/register/face', [
            'nom' => 'Doe',
            'prenom' => 'Junior',
            'email' => 'junior@example.com',
            'date_naissance' => now()->subYears(20)->format('Y-m-d'),
            'password' => 'Password123',
            'accept_cgu' => true,
        ])->assertCreated();

        $response->assertJsonPath('data.user.userable.age', 20);
        $response->assertJsonPath('data.user.userable.nom', 'Doe');
    }

    // === 6 : signalement par id entier ===

    public function test_report_rejects_sequential_integer_ids(): void
    {
        $mission = Mission::factory()->create();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/reports', ['reportable_type' => 'mission', 'reportable_id' => $mission->id, 'reason' => 'autre'])
            ->assertNotFound();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/reports', ['reportable_type' => 'face', 'reportable_id' => $this->face->id, 'reason' => 'autre'])
            ->assertNotFound();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/reports', ['reportable_type' => 'mission', 'reportable_id' => $mission->uuid, 'reason' => 'autre'])
            ->assertCreated();
    }

    // === 7 : fiche candidat d'une Face désactivée ===

    public function test_producer_cannot_view_a_deactivated_face(): void
    {
        $url = "/api/v1/producer/candidates/{$this->face->uuid}";

        $this->actingAs($this->producerUser)->getJson($url)->assertOk();

        $this->faceUser->update(['is_active' => false]);

        $this->actingAs($this->producerUser)->getJson($url)->assertNotFound();
    }
}

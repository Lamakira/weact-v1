<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot C6 : la page booking affiche l'autre partie à chaque rôle. Ces tests verrouillent
 * les champs nécessaires (carte « autre partie ») et l'absence de N+1.
 */
class BookingOtherPartyPayloadTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $face = Face::factory()->create(['prenom' => 'Adjoua', 'nom' => 'Kouassi', 'ville' => 'Cotonou']);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $this->booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
    }

    public function test_producer_sees_the_face_card_fields(): void
    {
        $face = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertOk()
            ->json('data.face.userable');

        $this->assertSame('Adjoua', $face['prenom']);
        $this->assertSame('Kouassi', $face['nom']);
        $this->assertSame('Cotonou', $face['ville']);
        $this->assertArrayHasKey('thumbnail_url', $face);
        $this->assertArrayHasKey('average_rating', $face);
        $this->assertArrayHasKey('ratings_count', $face);
        $this->assertArrayHasKey('has_elite_badge', $face);
        // PII et palier commercial : jamais exposés à la partie adverse.
        $this->assertArrayNotHasKey('whatsapp_number', array_filter($face, fn ($v) => $v !== null));
        $this->assertArrayNotHasKey('subscription_tier', $face);
    }

    public function test_face_sees_the_producer_card_fields(): void
    {
        $producer = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertOk()
            ->json('data.producer.userable');

        $this->assertArrayHasKey('display_name', $producer);
        $this->assertArrayHasKey('type', $producer);
        $this->assertArrayHasKey('average_rating', $producer);
        $this->assertArrayHasKey('ratings_count', $producer);
        $this->assertArrayHasKey('thumbnail_url', $producer);
        $this->assertArrayNotHasKey('whatsapp_number', $producer);
    }

    public function test_auto_completion_at_is_exposed_only_where_the_backend_computes_it(): void
    {
        $paid = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_fin' => now()->subDays(2)->startOfDay(),
            'completion_reminder_sent_at' => null,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$paid->uuid}")
            ->assertOk()
            ->assertJsonPath('data.auto_completion_at', null);

        $paid->update(['completion_reminder_sent_at' => now()]);

        $due = $paid->fresh()->silentAutoCompleteDueAt();
        $this->assertNotNull($due);
        $this->getJson("/api/v1/bookings/{$paid->uuid}")
            ->assertOk()
            ->assertJsonPath('data.auto_completion_at', $due->toIso8601String());

        $this->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.auto_completion_at', null);
    }

    public function test_show_query_count_does_not_grow_with_other_bookings(): void
    {
        $this->actingAs($this->producerUser);

        DB::enableQueryLog();
        $this->getJson("/api/v1/bookings/{$this->booking->uuid}")->assertOk();
        $baseline = count(DB::getQueryLog());

        // Autres bookings, autres Faces : la requête du show ne doit pas bouger.
        for ($i = 0; $i < 4; $i++) {
            $otherFace = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => Face::factory()->create()->id,
            ]);
            Booking::factory()->pending()->create([
                'face_id' => $otherFace->id,
                'producer_id' => $this->producerUser->id,
            ]);
        }

        DB::flushQueryLog();
        $this->getJson("/api/v1/bookings/{$this->booking->uuid}")->assertOk();
        $this->assertSame($baseline, count(DB::getQueryLog()));
    }
}

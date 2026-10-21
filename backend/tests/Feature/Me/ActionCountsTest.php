<?php

declare(strict_types=1);

namespace Tests\Feature\Me;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot D3 : GET /api/v1/me/action-counts. Chaque compteur doit être égal au nombre d'éléments du
 * filtre « À traiter » de la page qu'il désigne (mêmes fixtures, lues via les endpoints de liste).
 * Instant figé : mardi 20/10/2026 10:00 UTC.
 */
class ActionCountsTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Face $otherFace;

    private User $otherFaceUser;

    private Producer $producer;

    private User $producerUser;

    private Producer $otherProducer;

    private User $otherProducerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $this->face->id]);
        $this->otherFace = Face::factory()->create();
        $this->otherFaceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $this->otherFace->id]);
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $this->producer->id]);
        $this->otherProducer = Producer::factory()->create();
        $this->otherProducerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $this->otherProducer->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function booking(User $face, User $producer, array $attrs = []): Booking
    {
        $createdAt = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);

        $booking = Booking::factory()->create(array_merge([
            'face_id' => $face->id,
            'producer_id' => $producer->id,
            'type_contenu' => 'Publicité',
            'date_debut' => now()->addDays(10)->startOfDay(),
            'date_fin' => now()->addDays(10)->startOfDay(),
        ], $attrs));

        if ($createdAt !== null) {
            DB::table('bookings')->where('id', $booking->id)->update(['created_at' => $createdAt]);
        }

        return $booking->fresh();
    }

    private function ugcBooking(User $face, User $producer, BookingStatus $status, ?UgcTunnelStatus $tunnel): Booking
    {
        $booking = $this->booking($face, $producer, [
            'status' => $status,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'nom_produit' => 'Sérum',
        ]);

        if ($tunnel !== null) {
            $this->shipment($booking, $tunnel);
        }

        return $booking->fresh();
    }

    private function shipment(Booking|Candidature $owner, UgcTunnelStatus $tunnel): void
    {
        $owner->shipment()->create([
            'transporteur' => 'DHL',
            'numero_suivi' => 'TRK123',
            'tunnel_status' => $tunnel,
            'shipped_at' => now()->subDay(),
            'destinataire_nom' => 'Face Test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $missionAttrs
     */
    private function candidature(Producer $producer, Face $face, CandidatureStatus $status, ?Carbon $acceptedAt, array $missionAttrs = []): Candidature
    {
        $mission = Mission::factory()->create(array_merge([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
            'type_mission' => MissionType::Publicite,
            'date_tournage' => '2026-11-15',
            'date_limite_candidature' => '2026-10-25',
        ], $missionAttrs));

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
            'accepted_at' => $acceptedAt,
        ]);
    }

    private function unreadMessage(Candidature $candidature, User $sender): void
    {
        $conversation = Conversation::factory()->create(['candidature_id' => $candidature->id]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => User::class,
            'read_at' => null,
        ]);
    }

    /**
     * Fixtures communes : ce que la Face (et son Producteur) doit traiter, plus du bruit hors filtre.
     */
    private function seedScenario(): void
    {
        // Bookings Face : 2 à traiter (demande cash fraîche, UGC reçu), 2 hors filtre (demande échue, UGC non expédié).
        $this->booking($this->faceUser, $this->producerUser, ['status' => BookingStatus::Pending, 'created_at' => now()->subHours(1)]);
        $this->booking($this->faceUser, $this->producerUser, ['status' => BookingStatus::Pending, 'created_at' => now()->subHours(80)]);
        $this->ugcBooking($this->faceUser, $this->producerUser, BookingStatus::Accepted, UgcTunnelStatus::Received);
        $this->ugcBooking($this->faceUser, $this->producerUser, BookingStatus::Accepted, UgcTunnelStatus::UnboxingInReview);

        // Bookings Producteur uniquement : cash accepté à payer, UGC sans colis déclaré.
        $this->booking($this->otherFaceUser, $this->producerUser, [
            'status' => BookingStatus::Accepted,
            'accepted_at' => now()->subHours(2),
        ]);
        $this->ugcBooking($this->otherFaceUser, $this->producerUser, BookingStatus::Accepted, null);

        // Bruit d'un autre couple Face / Producteur (isolation).
        $this->booking($this->otherFaceUser, $this->otherProducerUser, ['status' => BookingStatus::Pending, 'created_at' => now()->subHours(1)]);
        $this->ugcBooking($this->otherFaceUser, $this->otherProducerUser, BookingStatus::Accepted, UgcTunnelStatus::AvisPending);

        // Candidatures Face : 1 à reconfirmer, 1 échue, 1 UGC confirmée à l'étape « avis attendu » (livrable).
        $this->candidature($this->producer, $this->face, CandidatureStatus::Accepted, now()->subHours(10));
        $this->candidature($this->producer, $this->face, CandidatureStatus::Accepted, now()->subHours(60));
        $ugc = $this->candidature($this->producer, $this->face, CandidatureStatus::Confirmed, now()->subDays(3), [
            'type_mission' => MissionType::Ugc,
            'date_tournage' => null,
        ]);
        $this->shipment($ugc, UgcTunnelStatus::AvisPending);

        // Candidature d'une autre Face (isolation) + candidature UGC dont la vidéo est déjà en revue (pas à envoyer).
        $this->candidature($this->otherProducer, $this->otherFace, CandidatureStatus::Accepted, now()->subHours(10));
        $inReview = $this->candidature($this->producer, $this->otherFace, CandidatureStatus::Confirmed, now()->subDays(3), [
            'type_mission' => MissionType::Ugc,
            'date_tournage' => null,
        ]);
        $this->shipment($inReview, UgcTunnelStatus::UnboxingInReview);
        Deliverable::factory()->unboxing()->create([
            'owner_type' => $inReview->getMorphClass(),
            'owner_id' => $inReview->id,
        ]);

        // Livrable de booking en revue pour le Producteur.
        $reviewBooking = $this->ugcBooking($this->faceUser, $this->producerUser, BookingStatus::Accepted, UgcTunnelStatus::AvisInReview);
        Deliverable::factory()->avis()->create([
            'owner_type' => $reviewBooking->getMorphClass(),
            'owner_id' => $reviewBooking->id,
        ]);

        // Messages non lus : une conversation pour chaque rôle.
        $this->unreadMessage($ugc, $this->producerUser);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/action-counts')->assertUnauthorized();
    }

    public function test_face_counters_match_the_to_process_filters_of_their_pages(): void
    {
        $this->seedScenario();

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/me/action-counts')->assertOk();

        $bookingsTotal = $this->actingAs($this->faceUser)->getJson('/api/v1/bookings?status=to_process')->json('meta.total');
        $candidaturesTotal = $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures?status=to_process')->json('meta.total');
        $unread = $this->actingAs($this->faceUser)->getJson('/api/v1/face/conversations/unread-count')->json('data.count');

        $this->assertSame(['bookings', 'candidatures', 'livrables', 'messages'], array_keys($response->json('data')));
        $this->assertSame(2, $bookingsTotal);
        // Reconfirmation à faire + candidature UGC dont la vidéo est à envoyer.
        $this->assertSame(2, $candidaturesTotal);
        $this->assertSame(1, $unread);
        $response->assertJsonPath('data.bookings', $bookingsTotal)
            ->assertJsonPath('data.candidatures', $candidaturesTotal)
            ->assertJsonPath('data.messages', $unread)
            // Vidéos à envoyer : booking UGC reçu + candidature UGC « avis attendu » (l'UGC en revue est exclu).
            ->assertJsonPath('data.livrables', 2);
    }

    public function test_producer_counters_match_the_to_process_filters_of_their_pages(): void
    {
        $this->seedScenario();
        // Mission publiée avec une candidature en attente de réponse : à traiter côté Producteur.
        $published = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'type_mission' => MissionType::Publicite,
            'date_tournage' => '2026-11-15',
            'date_limite_candidature' => '2026-10-25',
        ]);
        Candidature::factory()->create([
            'mission_id' => $published->id,
            'face_id' => $this->otherFace->id,
            'status' => CandidatureStatus::Pending,
        ]);

        // Mission UGC payée dont une Face confirmée n'a pas de colis : envoi du produit à confirmer.
        $toShip = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'type_mission' => MissionType::Ugc,
            'date_tournage' => null,
            'commission_paid_at' => now()->subDay(),
        ]);
        Candidature::factory()->create([
            'mission_id' => $toShip->id,
            'face_id' => $this->otherFace->id,
            'status' => CandidatureStatus::Confirmed,
            'accepted_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->producerUser)->getJson('/api/v1/me/action-counts')->assertOk();
        $listed = collect($this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions?status=to_process')->json('data'))->pluck('id');
        $this->assertContains($toShip->uuid, $listed);

        $bookingsTotal = $this->actingAs($this->producerUser)->getJson('/api/v1/bookings?status=to_process')->json('meta.total');
        $missionsTotal = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions?status=to_process&per_page=50')->json('meta.total');
        $inbox = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/deliverables')->json('data');
        $unread = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/conversations/unread-count')->json('data.count');

        $this->assertSame(['bookings', 'missions', 'livrables', 'messages'], array_keys($response->json('data')));
        $this->assertGreaterThan(0, $bookingsTotal);
        $this->assertGreaterThan(0, $missionsTotal);
        $this->assertCount(2, $inbox);
        $response->assertJsonPath('data.bookings', $bookingsTotal)
            ->assertJsonPath('data.missions', $missionsTotal)
            ->assertJsonPath('data.livrables', count($inbox))
            ->assertJsonPath('data.messages', $unread);
    }

    public function test_counters_are_isolated_per_user(): void
    {
        $this->seedScenario();

        $nobody = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
        $this->actingAs($nobody)->getJson('/api/v1/me/action-counts')
            ->assertOk()
            ->assertExactJson(['data' => ['bookings' => 0, 'candidatures' => 0, 'livrables' => 0, 'messages' => 0]]);

        $idleProducer = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);
        $this->actingAs($idleProducer)->getJson('/api/v1/me/action-counts')
            ->assertOk()
            ->assertExactJson(['data' => ['bookings' => 0, 'missions' => 0, 'livrables' => 0, 'messages' => 0]]);
    }

    public function test_number_of_queries_does_not_grow_with_the_data(): void
    {
        $face = $this->queryCount($this->faceUser);
        $producer = $this->queryCount($this->producerUser);

        $this->seedScenario();

        $this->assertSame($face, $this->queryCount($this->faceUser));
        $this->assertSame($producer, $this->queryCount($this->producerUser));
        $this->assertLessThanOrEqual(12, $face);
        $this->assertLessThanOrEqual(12, $producer);
    }

    private function queryCount(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson('/api/v1/me/action-counts')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}

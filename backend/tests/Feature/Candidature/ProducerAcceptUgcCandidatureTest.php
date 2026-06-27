<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Mail\CandidatureAcceptedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ugc-8-2 — le Producteur accepte explicitement une candidature UGC produit-seul
 * (pending→accepted, GRATUIT), remplaçant l'auto-acceptation par la Face. Bornage
 * capacité au Producteur-accept (engagé = accepted|confirmed|in_progress|completed,
 * D-8.2.b), auto-close Published→Closed, notif Face in-app + email (non-fatal).
 * L'hybride est différé à ugc-8-4 (422).
 */
class ProducerAcceptUgcCandidatureTest extends TestCase
{
    use RefreshDatabase;

    // ===================================================================
    // Happy path produit-seul (AC2, AC3)
    // ===================================================================

    public function test_producer_accepts_product_only_candidature_for_free(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        $response = $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept");

        $response->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('message', 'Candidature acceptée');

        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'accepted',
        ]);

        // Acceptation GRATUITE : aucun MissionPayment / escrow / FedaPay.
        $this->assertDatabaseCount('mission_payments', 0);

        // Conversation provisionnée dès Accepted (chat débloqué).
        $this->assertDatabaseHas('conversations', ['candidature_id' => $candidature->id]);
    }

    public function test_accept_notifies_face_in_app_and_email(): void
    {
        Mail::fake();

        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $faceUser->id,
            'type' => 'candidature_accepted',
        ]);
        Mail::assertQueued(CandidatureAcceptedMail::class);
    }

    public function test_email_failure_does_not_rollback_accept(): void
    {
        // AC3 / NFR6 : un échec de l'email post-commit ne rollback PAS l'acceptation.
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        Log::spy();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail down'));

        $response = $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept");

        $response->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'accepted',
        ]);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'CandidatureAcceptedMail queue failed'))
            ->once();
    }

    // ===================================================================
    // Capacité + auto-clôture (AC5, D-8.2.b)
    // ===================================================================

    public function test_second_accept_closes_mission_and_third_is_full(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        $mission = $this->makePublishedUgcMission($producer, ['nombre_faces_voulu' => 2]);
        [$f1] = $this->makeFaceWithUser();
        [$f2] = $this->makeFaceWithUser();
        [$f3] = $this->makeFaceWithUser();
        $c1 = $this->makePendingCandidature($mission, $f1);
        $c2 = $this->makePendingCandidature($mission, $f2);
        $c3 = $this->makePendingCandidature($mission, $f3);

        // 1er accept : capacité 1/2, mission reste Published.
        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$c1->uuid}/accept")
            ->assertOk();
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);

        // 2e accept : capacité atteinte → mission Closed.
        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$c2->uuid}/accept")
            ->assertOk();
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);

        // 3e accept : refusé MISSION_FULL.
        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$c3->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MISSION_FULL');
    }

    public function test_pending_and_cancelled_candidatures_do_not_consume_capacity(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        $mission = $this->makePublishedUgcMission($producer, ['nombre_faces_voulu' => 1]);
        [$fPending] = $this->makeFaceWithUser();
        [$fCancelled] = $this->makeFaceWithUser();
        [$fTarget] = $this->makeFaceWithUser();

        // 1 pending + 1 cancelled : ni l'une ni l'autre ne consomme la capacité.
        $this->makePendingCandidature($mission, $fPending);
        Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $fCancelled->id,
            'status' => CandidatureStatus::Cancelled,
        ]);
        $target = $this->makePendingCandidature($mission, $fTarget);

        // La cible est acceptable malgré le pending + le cancelled (capacité 1 libre).
        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$target->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }

    // ===================================================================
    // Gardes (AC4)
    // ===================================================================

    public function test_accept_on_hybrid_mission_initiates_payment(): void
    {
        // ugc-8-4 (D-8.4.e) : l'hybride n'est plus refusé (8-2) — l'accept initie le règlement
        // FedaPay (cash + 10 %) et renvoie le checkout_url ; la candidature reste pending
        // jusqu'au webhook approved (couverture complète : UgcMissionHybridSettlementTest).
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer, [
            'type_compensation' => CompensationType::Hybrid->value,
            'montant_remuneration' => 50000,
            'commission_ugc' => null,
            'commission_paid_at' => null,
        ]);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForUgcMissionCandidature')
                ->once()
                ->andReturn(['fedapay_transaction_id' => 940, 'checkout_url' => 'https://fedapay.test/hybride']);
        });

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://fedapay.test/hybride')
            ->assertJsonPath('data.status', 'pending');

        // L'escrow par-Face est créé Pending (parentless) ; la candidature reste pending
        // (l'acceptation effective vient du webhook).
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'candidature_id' => $candidature->id,
            'mission_payment_id' => null,
            'escrow_status' => 'pending',
            'fedapay_transaction_id' => '940',
        ]);
        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'pending',
        ]);
    }

    public function test_accept_on_standard_mission_is_rejected(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        // MissionFactory crée une mission STANDARD (exclut l'UGC).
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_accept_on_non_pending_candidature_is_rejected(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer, ['nombre_faces_voulu' => 5]);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted]);

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    // ===================================================================
    // Re-validation d'éligibilité UGC à l'accept (revue 2026-06-26, D-2.4.c restauré)
    // ===================================================================

    public function test_accept_rejects_face_without_active_ugc_subscription(): void
    {
        // L'éligibilité UGC est re-vérifiée AU MOMENT de l'accept : une Face dont
        // l'abonnement a expiré entre apply et accept est refusée (403). Prouve la
        // restauration de D-2.4.c (échouerait sans le re-check canAccessUgc).
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeUnsubscribedFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'UGC_SUBSCRIPTION_REQUIRED');

        // Refus ⇒ la candidature reste pending (aucun engagement, capacité intacte).
        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'pending',
        ]);
    }

    public function test_accept_rejects_face_whose_gender_does_not_match(): void
    {
        // Re-check genre à l'accept : une mission genre_voulu=homme refuse une Face femme.
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser(['sexe' => 'femme']);
        $mission = $this->makePublishedUgcMission($producer, ['genre_voulu' => 'homme']);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'pending',
        ]);
    }

    public function test_other_producer_cannot_accept_candidature(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [, $otherProducerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->actingAs($otherProducerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertForbidden();
    }

    public function test_non_producer_cannot_accept_candidature(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->actingAs($faceUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertForbidden();
    }

    // ===================================================================
    // Fixtures (S8) — ni MissionFactory ni les factories User/Face/Producer
    // ne couvrent l'UGC + le User userable : tout construit à la main.
    // ===================================================================

    /** @return array{0: Producer, 1: User} */
    private function makeProducerWithUser(): array
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return [$producer, $user];
    }

    /** @return array{0: Face, 1: User} */
    private function makeFaceWithUser(array $faceOverrides = []): array
    {
        $face = Face::factory()->create($faceOverrides);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        // ugc-8-2 (revue, D-2.4.c) : l'accept re-vérifie canAccessUgc ⇒ Face abonnée
        // Starter active (sinon 403). Calque le S8 makeSubscribedFace de la story.
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $face->id]);

        return [$face, $user];
    }

    /**
     * Face SANS abonnement actif ⇒ canAccessUgc() = false (abonnement expiré entre
     * apply et accept). Utilisée par le test de re-validation d'éligibilité.
     *
     * @return array{0: Face, 1: User}
     */
    private function makeUnsubscribedFaceWithUser(): array
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return [$face, $user];
    }

    private function makePublishedUgcMission(Producer $producer, array $overrides = []): Mission
    {
        return $producer->missions()->create(array_merge([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'Brief',
            'date_tournage' => null,
            'lieu' => null,
            'duree' => null,
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => CompensationType::Product->value,
            'nom_produit' => 'Sneakers',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ], $overrides));
    }

    private function makePendingCandidature(Mission $mission, Face $face): Candidature
    {
        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Pending,
        ]);
    }
}

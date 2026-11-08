<?php

declare(strict_types=1);

namespace Tests\Feature\DateChange;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DateChangeReason;
use App\Enums\DateChangeStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionType;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\DateChangeRequest;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Socle des tests du changement de date. Horloge figée : mardi 10/11/2026 09:00 UTC
 * (10:00 au Bénin). Fuseau métier Africa/Porto-Novo (UTC+1, sans heure d'été).
 */
abstract class DateChangeTestCase extends TestCase
{
    use RefreshDatabase;

    protected const NOW = '2026-11-10 09:00:00';

    protected Producer $producer;

    protected User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow(Carbon::parse(self::NOW, 'UTC'));

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
            'balance' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Mission cash payée et clôturée, une candidature par statut demandé (entry Locked, budget_par_face 100 000).
     *
     * @param  list<CandidatureStatus>  $statuses
     * @param  array<string, mixed>  $missionAttributes
     * @return array{0: Mission, 1: MissionPayment, 2: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    protected function paidMission(array $statuses, string $date = '2026-11-20', array $missionAttributes = []): array
    {
        /** @var Mission $mission */
        $mission = Mission::factory()->closed()->create(array_merge([
            'producer_id' => $this->producer->id,
            'titre' => 'Spot test',
            'type_mission' => MissionType::Publicite,
            'date_limite_candidature' => '2026-11-05',
            'date_tournage' => $date,
            'nombre_faces_voulu' => count($statuses),
            'shooting_reminder_sent_at' => Carbon::parse(self::NOW, 'UTC')->subDay(),
        ], $missionAttributes));

        $count = count($statuses);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $count,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $count,
            'commission_producteur' => 10000 * $count,
            'montant_total_producteur' => 110000 * $count,
            'commission_faces_total' => 10000 * $count,
            'montant_total_faces' => 90000 * $count,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now()->subDays(3),
        ]);

        $faces = [];

        foreach ($statuses as $status) {
            $faces[] = $this->selectedFace($mission, $payment, $status);
        }

        return [$mission, $payment, $faces];
    }

    /**
     * @return array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}
     */
    protected function selectedFace(Mission $mission, MissionPayment $payment, CandidatureStatus $status): array
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
            'accepted_at' => now()->subDays(2),
        ]);
        $entry = MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now()->subDays(3),
        ]);

        return compact('candidature', 'entry', 'faceUser', 'face');
    }

    /**
     * Engage une Face existante sur une mission (candidature + séquestre).
     *
     * @return array{candidature: Candidature, entry: MissionPaymentCandidature}
     */
    protected function selectedFaceFor(User $faceUser, Mission $mission, MissionPayment $payment, CandidatureStatus $status): array
    {
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $faceUser->userable_id,
            'status' => $status,
            'accepted_at' => now()->subDay(),
        ]);
        $entry = MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $faceUser->userable_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now()->subDays(3),
        ]);

        return ['candidature' => $candidature, 'entry' => $entry];
    }

    /** Candidature non retenue d'une Face abonnée : rend la place libérable (remplacement possible). */
    protected function replacementCandidate(Mission $mission): Candidature
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $face->id]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Rejected,
        ]);
    }

    protected function faceUser(): User
    {
        $face = Face::factory()->create();

        return User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
    }

    /**
     * Booking cash accepté entre une Face et le Producteur de la classe de test.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Booking, 1: User} le booking et l'utilisateur Face
     */
    protected function cashBooking(array $attributes = [], ?User $faceUser = null): array
    {
        $faceUser ??= $this->faceUser();

        /** @var Booking $booking */
        $booking = Booking::factory()->create(array_merge([
            'face_id' => $faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Accepted,
            'accepted_at' => now()->subHours(2),
            'type_contenu' => 'Publicité',
            'date_debut' => '2026-11-20 09:00:00',
            'date_fin' => '2026-11-21 17:00:00',
            'duree_heures' => 10,
            'tarif_base' => 100000,
            'montant_total_producteur' => 110000,
            'montant_face_recoit' => 90000,
            'shooting_reminder_sent_at' => now()->subDay(),
            'completion_reminder_sent_at' => now()->subDay(),
        ], $attributes));

        return [$booking, $faceUser];
    }

    /**
     * Demande de changement de date déjà présente en base (quota, statut).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function existingRequest(Mission|Booking $subject, DateChangeStatus $status, array $attributes = []): DateChangeRequest
    {
        return DateChangeRequest::query()->create(array_merge([
            $subject instanceof Mission ? 'mission_id' : 'booking_id' => $subject->id,
            'requested_by_user_id' => $this->producerUser->id,
            'requested_by_role' => 'producer',
            'old_date' => '2026-11-20',
            'new_date' => '2026-11-22',
            'reason' => DateChangeReason::Meteo,
            'status' => $status,
            'respond_by' => now()->addHours(10),
        ], $attributes));
    }

    protected function requestMissionChange(Mission $mission, string $newDate = '2026-11-25', string $reason = 'meteo', ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/date-change", [
                'new_date' => $newDate,
                'reason' => $reason,
            ]);
    }

    protected function respondAsFace(User $faceUser, Candidature $candidature, string $decision): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/date-change/respond", ['decision' => $decision]);
    }

    /** 422 sous la clé `date`, avec le motif attendu (évite un test vert pour la mauvaise raison). */
    protected function assertDateError(\Illuminate\Testing\TestResponse $response, string $fragment): void
    {
        $response->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertStringContainsString($fragment, (string) $response->json('errors.date.0'));
    }
}

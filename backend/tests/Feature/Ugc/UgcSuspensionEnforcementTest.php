<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionType;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\UgcSuspension;
use App\Models\User;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retrait automatique des candidatures UGC en attente d'une Face suspendue et levée
 * automatique à J+`ugc.suspension_days` (commande `ugc:enforce-suspensions`).
 */
class UgcSuspensionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function mission(MissionType $type = MissionType::Ugc): Mission
    {
        return Mission::factory()->published()->forAll()->create([
            'producer_id' => $this->producer->id,
            'type_mission' => $type,
            'titre' => 'Mission Sneakers',
        ]);
    }

    private function candidature(Mission $mission, CandidatureStatus $status = CandidatureStatus::Pending): Candidature
    {
        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->id,
            'status' => $status,
        ]);
    }

    private function suspend(?\DateTimeInterface $at = null): UgcSuspension
    {
        return UgcSuspension::create([
            'face_id' => $this->face->id,
            'shipment_id' => null,
            'reason' => UgcSuspensionReason::AvisDeadlineMissed,
            'appeal_status' => UgcSuspensionAppealStatus::None,
            'suspended_at' => $at ?? now(),
        ]);
    }

    private function inFlightEntry(Candidature $candidature, EscrowStatus $status): MissionPaymentCandidature
    {
        $payment = MissionPayment::create([
            'mission_id' => $candidature->mission_id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Pending,
        ]);

        return MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $this->face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => $status,
            'attendance_status' => 'pending',
        ]);
    }

    public function test_withdraw_cancels_pending_ugc_only_and_notifies_producer_neutrally(): void
    {
        $ugcMission = $this->mission();
        $pendingUgc = $this->candidature($ugcMission);
        $standardPending = $this->candidature($this->mission(MissionType::Publicite));
        $acceptedUgc = $this->candidature($this->mission(), CandidatureStatus::Accepted);
        $inFlight = $this->candidature($this->mission());
        $this->inFlightEntry($inFlight, EscrowStatus::Pending);

        $count = app(UgcSuspensionService::class)->withdrawPendingCandidatures($this->face->id);

        $this->assertSame(1, $count);
        $this->assertSame(CandidatureStatus::Cancelled, $pendingUgc->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $standardPending->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $acceptedUgc->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $inFlight->fresh()->status);

        $notification = Notification::where('user_id', $this->producerUser->id)->where('type', 'candidature_withdrawn_unavailable')->get();
        $this->assertCount(1, $notification);
        $this->assertSame("Une candidature à votre mission « Mission Sneakers » n'est plus disponible.", $notification->first()->data['message']);
        $this->assertSame("/producer/missions/{$ugcMission->uuid}?tab=candidatures", $notification->first()->data['url']);
        $this->assertStringNotContainsStringIgnoringCase('suspen', json_encode($notification->first()->data));
    }

    public function test_command_lifts_suspension_at_day_seven_but_not_before(): void
    {
        $recent = $this->suspend(now()->subDays(7)->addMinute()); // J+6 23:59
        $this->artisan('ugc:enforce-suspensions')->assertSuccessful();
        $this->assertNull($recent->fresh()->reactivated_at);

        $this->travel(2)->minutes(); // J+7 et 1 minute
        $this->artisan('ugc:enforce-suspensions')->assertSuccessful();
        $this->assertNotNull($recent->fresh()->reactivated_at);
    }

    public function test_command_withdraws_remaining_pending_candidatures_of_still_suspended_faces_and_is_idempotent(): void
    {
        $this->suspend(now()->subDay());
        $pending = $this->candidature($this->mission());

        $this->artisan('ugc:enforce-suspensions')->assertSuccessful();
        $this->assertSame(CandidatureStatus::Cancelled, $pending->fresh()->status);
        $this->assertSame(1, Notification::where('user_id', $this->producerUser->id)->where('type', 'candidature_withdrawn_unavailable')->count());

        $this->artisan('ugc:enforce-suspensions')->assertSuccessful();
        $this->assertSame(1, Notification::where('user_id', $this->producerUser->id)->where('type', 'candidature_withdrawn_unavailable')->count());
    }

    public function test_command_leaves_pending_candidatures_of_faces_without_active_suspension(): void
    {
        $pending = $this->candidature($this->mission());

        $this->artisan('ugc:enforce-suspensions')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Pending, $pending->fresh()->status);
    }
}

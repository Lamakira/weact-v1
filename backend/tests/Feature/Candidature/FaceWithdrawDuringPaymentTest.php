<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPendingMissionSelection;
use Tests\TestCase;

/**
 * Lot B2 : retrait d'une Face pendant le paiement de la sélection.
 */
class FaceWithdrawDuringPaymentTest extends TestCase
{
    use BuildsPendingMissionSelection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSelectionProducer();
    }

    private function cancel(array $face): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($face['faceUser'])
            ->postJson("/api/v1/face/candidatures/{$face['candidature']->uuid}/cancel");
    }

    public function test_non_selected_face_withdraws_and_selection_is_untouched(): void
    {
        [$mission, $payment, $faces] = $this->pendingSelection(2);
        // Candidature d'une Face non sélectionnée (aucune entry), sur la même mission.
        $outsiderFace = Face::factory()->create();
        $outsider = [
            'faceUser' => User::factory()->create(['userable_type' => Face::class, 'userable_id' => $outsiderFace->id]),
            'candidature' => Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $outsiderFace->id,
                'status' => CandidatureStatus::Pending,
            ]),
        ];
        $this->fedapayReports('pending');

        $this->cancel($outsider)->assertOk();

        $this->assertSame(CandidatureStatus::Cancelled, $outsider['candidature']->fresh()->status);
        $this->assertNotNull(MissionPayment::find($payment->id));
        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $faces[0]['candidature']->fresh()->status);
    }

    public function test_selected_face_withdraws_when_checkout_is_older_than_an_hour(): void
    {
        [$mission, $payment, $faces] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 2);
        $this->fedapayReports('pending', createdAt: now()->subHours(2));

        $this->cancel($faces[0])->assertOk();

        $this->assertNull(MissionPayment::find($payment->id));
        $this->assertSame(0, MissionPaymentCandidature::count());
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Cancelled, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $faces[1]['candidature']->fresh()->status);

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'mission_selection_reset')
            ->firstOrFail();
        $this->assertSame('face_withdrew_during_payment', $notification->data['reason']);
        $this->assertStringContainsString('Une Face a retiré sa candidature pendant le paiement', $notification->data['message']);
        $this->assertSame("/producer/missions/{$mission->uuid}/candidatures", $notification->data['url']);
    }

    public function test_selected_face_is_refused_while_checkout_was_resumed_recently(): void
    {
        [$mission, $payment, $faces] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 2, updatedMinutesAgo: 10);
        $this->fedapayReports('pending', createdAt: now()->subHours(2));

        $this->cancel($faces[0])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Le producteur est en train de payer sa sélection. Réessayez dans une heure.');

        $this->assertNotNull(MissionPayment::find($payment->id));
        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $faces[0]['candidature']->fresh()->status);
    }

    public function test_selected_face_is_refused_when_the_selection_is_still_pending_after_the_settle_step(): void
    {
        // Course : la sélection n'a pas pu être réinitialisée (reprise concurrente du checkout →
        // transaction différente, settle renvoie NOOP) : la candidature est toujours dans un
        // paiement en attente, son retrait doit être refusé (fail-closed).
        [$mission, $payment, $faces] = $this->pendingSelection(2);
        $this->partialMock(\App\Services\MissionPaymentService::class, function ($mock): void {
            $mock->shouldReceive('settleOrResetPendingSelection')
                ->andReturn(\App\Services\MissionPaymentService::SELECTION_NOOP);
        });

        $this->cancel($faces[0])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Le producteur est en train de payer sa sélection. Réessayez dans une heure.');

        $this->assertNotNull(MissionPayment::find($payment->id));
        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $faces[0]['candidature']->fresh()->status);
    }

    public function test_selected_face_is_told_the_selection_was_paid(): void
    {
        [, $payment, $faces] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 2);
        $this->fedapayReports('approved', createdAt: now()->subHours(2));

        $this->cancel($faces[0])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Le producteur vient de finaliser le paiement : votre candidature est retenue. Confirmez votre participation depuis vos candidatures.');

        $this->assertSame(CandidatureStatus::Accepted, $faces[0]['candidature']->fresh()->status);
    }

    public function test_selected_face_is_refused_when_fedapay_is_unavailable(): void
    {
        [, $payment, $faces] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 2);
        $this->mock(\App\Services\FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')->andThrow(new \RuntimeException('down'));
        });

        $this->cancel($faces[0])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Impossible de vérifier le paiement pour le moment, réessayez dans quelques minutes.');

        $this->assertNotNull(MissionPayment::find($payment->id));
        $this->assertSame(CandidatureStatus::Pending, $faces[0]['candidature']->fresh()->status);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Mail\MissionSelectionExpiredMail;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPendingMissionSelection;
use Tests\TestCase;

/**
 * Lot B1 : échéance de 24 h du paiement de sélection (reprise, « Modifier la sélection », payment-status).
 */
class MissionSelectionPaymentDeadlineTest extends TestCase
{
    use BuildsPendingMissionSelection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpSelectionProducer();
    }

    public function test_cancel_selection_resets_the_selection_without_notifying_the_producer(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->fedapayReports('pending');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/cancel-selection")
            ->assertOk()
            ->assertJsonPath('data.mission_status', MissionStatus::Published->value)
            ->assertJsonPath('message', 'Sélection annulée. Vous pouvez refaire votre sélection.');

        $this->assertSame(0, MissionPayment::where('mission_id', $mission->id)->count());
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertDatabaseHas('financial_events', ['type' => 'payment_detached', 'fedapay_ref' => '777001']);
        $this->assertDatabaseMissing('notifications', ['type' => 'mission_selection_reset']);
        Mail::assertNothingQueued();
    }

    public function test_cancel_selection_by_another_producer_is_forbidden(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $other = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);

        $this->actingAs($other)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/cancel-selection")
            ->assertForbidden();

        $this->assertSame(MissionPaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_cancel_selection_when_fedapay_already_approved_returns_409_and_settles(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->fedapayReports('approved');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/cancel-selection")
            ->assertStatus(409)
            ->assertJsonPath('error.message', 'Le paiement vient d\'être confirmé : la sélection ne peut plus être modifiée.');

        $this->assertSame(MissionPaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }

    public function test_cancel_selection_without_pending_payment_returns_422(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $payment->entries()->delete();
        $payment->delete();
        $mission->update(['status' => MissionStatus::Published]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/cancel-selection")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Aucun paiement en attente à annuler.');
    }

    public function test_cancel_selection_when_fedapay_is_down_returns_422_and_keeps_the_selection(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->mock(\App\Services\FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')->andThrow(new \RuntimeException('FedaPay down'));
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/cancel-selection")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Impossible de vérifier le paiement pour le moment, réessayez dans quelques minutes.');

        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
    }

    public function test_resume_after_24h_is_refused_and_the_selection_is_reset(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 25);
        $this->fedapayReports('pending', createdAt: now()->subHours(25));

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/confirm-selection", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.candidature_ids.0', 'Le délai de 24 h pour payer cette sélection est dépassé : elle a été annulée. Refaites votre sélection.');

        $this->assertSame(0, MissionPayment::where('mission_id', $mission->id)->count());
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->producerUser->id, 'type' => 'mission_selection_reset']);
        Mail::assertQueued(MissionSelectionExpiredMail::class);
    }

    public function test_resume_before_24h_still_returns_a_checkout_url(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 23);

        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = 'pending';
        $transaction->reference = null;
        $transaction->shouldReceive('generateToken')->once()->andReturn((object) ['url' => 'https://checkout.fedapay.com/resume-token']);
        $this->mock(\App\Services\FedapayService::class, function ($mock) use ($transaction): void {
            $mock->shouldReceive('retrieveTransaction')->with(777001)->andReturn($transaction);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/confirm-selection", [])
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/resume-token');

        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
    }

    public function test_payment_status_exposes_the_deadline(): void
    {
        [$mission, $payment] = $this->pendingSelection(2, transactionId: null);
        $this->agePayment($payment->id, 3);

        $expected = $payment->fresh()->created_at->copy()->addHours(24);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/payment-status")
            ->assertOk();

        $this->assertTrue($expected->equalTo(\Illuminate\Support\Carbon::parse($response->json('data.expires_at'))));
    }

    public function test_payment_status_has_no_deadline_without_payment(): void
    {
        [$mission, $payment] = $this->pendingSelection(2, transactionId: null);
        $payment->entries()->delete();
        $payment->delete();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.expires_at', null);
    }
}

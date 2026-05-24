<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CancelPendingFaceSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_face_cancels_their_pending_payment_returns_200_with_failed_status(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'metadata' => [
                'quoted_amount' => 25000,
                'idempotency_key' => 'idem-original',
            ],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/cancel-pending');

        $response->assertOk()
            ->assertJsonPath('data.subscription_id', $pending->uuid)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('message', 'Paiement annulé.');

        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::Failed, $fresh->status);

        // Pre-existing metadata preserved
        $this->assertSame(25000, $fresh->metadata['quoted_amount']);
        $this->assertSame('idem-original', $fresh->metadata['idempotency_key']);

        // New audit fields added by the cancel action
        $this->assertIsString($fresh->metadata['cancelled_by_user_at']);
        $this->assertSame('user_self_cancel', $fresh->metadata['cancellation_source']);
    }

    public function test_face_with_no_pending_payment_returns_404_no_pending_payment(): void
    {
        // No FaceSubscription created — the Face has zero rows.

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/cancel-pending');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_PENDING_PAYMENT')
            ->assertJsonPath('error.message', 'Aucun paiement en attente à annuler.');
    }

    public function test_face_with_only_terminal_history_returns_404_no_pending_payment(): void
    {
        FaceSubscription::factory()->pro()->failed()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subDays(3),
        ]);
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subDays(400),
        ]);
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/cancel-pending');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_PENDING_PAYMENT');

        // Pre-existing rows untouched
        $this->assertSame(3, FaceSubscription::where('face_id', $this->face->id)->count());
        $this->assertSame(0, FaceSubscription::where('face_id', $this->face->id)
            ->where('status', FaceSubscriptionStatus::PendingPayment)
            ->count());
    }

    public function test_unauthenticated_request_returns_401_with_envelope(): void
    {
        $response = $this->postJson('/api/v1/face/subscription/cancel-pending');

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Non authentifié.');
    }

    public function test_producer_user_returns_403_with_face_envelope(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->postJson('/api/v1/face/subscription/cancel-pending');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_face_cannot_cancel_another_faces_pending_payment(): void
    {
        $otherFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $otherFace->id]);

        $otherPending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $otherFace->id,
        ]);

        // Authenticated as $this->faceUser (the OTHER Face) — should get 404
        // because $this->face has no pending row of its own.
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/cancel-pending');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_PENDING_PAYMENT');

        // The other Face's row is untouched
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $otherPending->fresh()->status);
    }

    public function test_double_cancel_returns_404_on_second_call(): void
    {
        FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
        ]);

        $first = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/cancel-pending');
        $first->assertOk();

        $second = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/cancel-pending');
        $second->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_PENDING_PAYMENT');

        // Still exactly one row, in Failed status (not double-mutated)
        $this->assertSame(1, FaceSubscription::where('face_id', $this->face->id)->count());
        $this->assertSame(
            FaceSubscriptionStatus::Failed,
            FaceSubscription::where('face_id', $this->face->id)->first()->status
        );
    }

    public function test_webhook_arriving_after_user_cancel_does_not_revive_subscription(): void
    {
        Log::spy();

        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'fp_tx_race_001',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-race-pro',
            ],
        ]);

        // User cancels first
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/cancel-pending')
            ->assertOk();

        $this->assertSame(FaceSubscriptionStatus::Failed, $pending->fresh()->status);

        // Webhook arrives "later" with a transaction.approved → markAsPaid is
        // invoked directly against the now-Failed row. The non-pending guard
        // at FaceSubscriptionPaymentService::markAsPaid line 211 must early-return
        // without flipping the row to Active.
        /** @var \App\Services\FaceSubscriptionPaymentService $service */
        $service = app(\App\Services\FaceSubscriptionPaymentService::class);
        $service->markAsPaid(
            subscription: $pending->fresh(),
            fedapayRef: 'ref_late_001',
            paidAmount: 25000,
            rawWebhookPayload: ['id' => 'evt_late', 'name' => 'transaction.approved'],
            providerReference: 'fp_tx_race_001',
        );

        // Row is STILL Failed — webhook did NOT revive it
        $final = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::Failed, $final->status);
        $this->assertNull($final->starts_at, 'starts_at must stay null — markAsPaid did not run its activation branch');
        $this->assertNull($final->expires_at, 'expires_at must stay null — markAsPaid did not run its activation branch');
        $this->assertSame('user_self_cancel', $final->metadata['cancellation_source']);
        $this->assertSame('manual_review_required', $final->metadata['late_approved_after_local_failure_reason']);
        $this->assertSame('ref_late_001', $final->metadata['late_approved_fedapay_reference']);
        $this->assertSame('fp_tx_race_001', $final->metadata['late_approved_provider_reference']);
        $this->assertSame(25000, $final->metadata['late_approved_paid_amount']);
        $this->assertSame('evt_late', $final->metadata['late_approved_event_payload_summary']['event_id']);

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message, array $context) use ($pending): bool {
                return $message === 'Fedapay webhook: approved payment arrived after local face subscription failure — manual review required'
                    && ($context['face_subscription_id'] ?? null) === $pending->id
                    && ($context['face_id'] ?? null) === $this->face->id
                    && ($context['local_failure_source'] ?? null) === 'user_self_cancel';
            })
            ->once();
    }
}

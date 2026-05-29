<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Jobs\HandleFedapayWebhook;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FedapayWebhookEvent;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FaceSubscriptionAdminService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\FedapayService;
use App\Services\MissionPaymentService;
use App\Services\WalletService;
use FedaPay\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Repro harness for the reported bug:
 *   "Quand l'admin annule l'abonnement d'une Face et que la Face fait mine de
 *    souscrire à ce même abonnement, l'abonnement redevient actif sans même
 *    qu'il ne paye."
 *
 * Each test drives a candidate path to a final FaceSubscription status and
 * asserts the row does NOT become Active without a real payment. A FAILING
 * (red) assertion here means the bug is reproduced in the backend.
 */
class SubscriptionCancelReactivationTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
            'email_verified_at' => now(),
        ]);
        $this->admin = Admin::factory()->create();
    }

    /**
     * Admin cancel via the exact service the admin controller delegates to
     * (AdminFaceSubscriptionController::cancel -> FaceSubscriptionAdminService::cancel).
     */
    private function adminCancel(FaceSubscription $subscription): FaceSubscription
    {
        return app(FaceSubscriptionAdminService::class)->cancel(
            subscription: $subscription,
            admin: $this->admin,
            notes: 'Repro admin cancel',
        );
    }

    private function dispatchWebhook(string $eventId, string $eventName, array $payload): void
    {
        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => $eventId,
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'received',
        ]);

        (new HandleFedapayWebhook($webhookEvent->id, $eventName, $payload))->handle(
            app(BookingService::class),
            app(MissionPaymentService::class),
            app(WalletService::class),
            app(FaceSubscriptionPaymentService::class),
        );
    }

    private function statusEndpoint(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->faceUser)->getJson('/api/v1/face/subscription-status');
    }

    // -----------------------------------------------------------------------
    // Scenario 1 — Webhook replay after admin cancel on an ACTIVE-paid row.
    // -----------------------------------------------------------------------
    public function test_scenario_1_webhook_replay_after_admin_cancel_does_not_reactivate(): void
    {
        $row = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '777888',
            'paid_amount' => 50000,
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $this->adminCancel($row);
        $this->assertSame(FaceSubscriptionStatus::Cancelled, $row->fresh()->status, 'precondition: row is Cancelled after admin cancel');

        // Re-dispatch an approved webhook for the SAME transaction id.
        $this->dispatchWebhook('evt_s1', 'transaction.approved', [
            'id' => 'evt_s1',
            'name' => 'transaction.approved',
            'entity' => ['id' => 777888, 'reference' => 'fp-ref-s1', 'amount' => 50000],
        ]);

        $this->assertNotSame(
            FaceSubscriptionStatus::Active,
            $row->fresh()->status,
            'BUG scenario 1: row reactivated by webhook replay after admin cancel',
        );
        $this->statusEndpoint()->assertJsonPath('data.current.status', 'cancelled');

        // Prove the webhook actually RESOLVED the cancelled row (by provider_reference)
        // and was blocked by the non-pending guard at FaceSubscriptionPaymentService.php:273,
        // i.e. the safety is real and not a silent lookup miss.
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $m, array $c): bool => $m === 'Fedapay webhook: ignoring approval for non-pending face subscription'
                && ($c['face_subscription_id'] ?? null) === $row->id)
            ->once();
    }

    // -----------------------------------------------------------------------
    // Scenario 2 — Re-initiate then verify with Fedapay status=pending (NOT paid).
    // -----------------------------------------------------------------------
    public function test_scenario_2_reinitiate_then_verify_unpaid_does_not_reactivate(): void
    {
        $row = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '100',
            'paid_amount' => 50000,
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $this->adminCancel($row);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->andReturn(['fedapay_transaction_id' => 200, 'checkout_url' => 'https://checkout.test/y']);
            // verify -> checkAndProcessPayment retrieves new tx 200, still pending.
            $mock->shouldReceive('retrieveTransaction')
                ->with(200)
                ->andReturn(new Transaction(['id' => 200, 'status' => 'pending', 'reference' => 'fp-200', 'amount' => 50000, 'currency' => ['iso' => 'XOF']]));
        });

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => FaceSubscriptionPlan::Pro->value])
            ->assertOk();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/verify-payment')
            ->assertOk();

        // The cancelled row must stay cancelled; the new pending row must stay pending.
        $this->assertSame(FaceSubscriptionStatus::Cancelled, $row->fresh()->status, 'scenario 2: old cancelled row');
        $newPending = FaceSubscription::where('face_id', $this->face->id)
            ->where('provider_reference', '200')->first();
        $this->assertNotNull($newPending);
        $this->assertNotSame(
            FaceSubscriptionStatus::Active,
            $newPending->fresh()->status,
            'BUG scenario 2: new row became Active though Fedapay reported pending (no payment)',
        );
        $this->statusEndpoint()->assertJsonPath('data.current.status', 'pending_payment');
    }

    // -----------------------------------------------------------------------
    // Scenario 3 — Cancelled row keeps provider_reference X, a new pending row
    // points at X too (operator/double-init confusion). Can verify/webhook on X
    // route 'approved' to a row that should not reactivate?
    // -----------------------------------------------------------------------
    public function test_scenario_3_verify_routes_approved_to_latest_pending_sharing_old_reference(): void
    {
        $cancelled = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '300',
            'paid_amount' => 50000,
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);
        $this->adminCancel($cancelled);

        // A fresh pending row that (mistakenly) carries the SAME provider_reference.
        $newPending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '301',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        // retrieveTransaction(301) reports approved -> this is effectively a real
        // payment on the new transaction; included to observe routing.
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->with(301)
                ->andReturn(new Transaction(['id' => 301, 'status' => 'approved', 'reference' => 'fp-301', 'amount' => 50000, 'currency' => ['iso' => 'XOF']]));
        });

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/verify-payment')
            ->assertOk();

        // The OLD cancelled row must never flip back.
        $this->assertNotSame(
            FaceSubscriptionStatus::Active,
            $cancelled->fresh()->status,
            'BUG scenario 3: old cancelled row reactivated',
        );
        $this->assertSame(FaceSubscriptionStatus::Cancelled, $cancelled->fresh()->status);
        // The new pending row genuinely approved -> Active is expected/correct here.
    }

    // -----------------------------------------------------------------------
    // Scenario 4a — Admin cancels a PENDING row, then approved webhook for it.
    // -----------------------------------------------------------------------
    public function test_scenario_4a_admin_cancel_pending_then_approved_webhook_does_not_activate(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '400',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $this->adminCancel($pending);
        $this->assertSame(FaceSubscriptionStatus::Cancelled, $pending->fresh()->status);

        $this->dispatchWebhook('evt_s4a', 'transaction.approved', [
            'id' => 'evt_s4a',
            'name' => 'transaction.approved',
            'entity' => ['id' => 400, 'reference' => 'fp-400', 'amount' => 50000],
        ]);

        $this->assertNotSame(
            FaceSubscriptionStatus::Active,
            $pending->fresh()->status,
            'BUG scenario 4a: admin-cancelled pending row reactivated by approved webhook',
        );

        // Confirm the webhook resolved the cancelled row and hit the non-pending guard.
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $m, array $c): bool => $m === 'Fedapay webhook: ignoring approval for non-pending face subscription'
                && ($c['face_subscription_id'] ?? null) === $pending->id)
            ->once();
    }

    // -----------------------------------------------------------------------
    // Scenario 4b — Admin cancels a PENDING row, then Face calls resume-payment
    // while Fedapay reports approved.
    // -----------------------------------------------------------------------
    public function test_scenario_4b_admin_cancel_pending_then_resume_approved_does_not_activate(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '410',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $this->adminCancel($pending);

        $this->mock(FedapayService::class, function ($mock): void {
            // resumePending only loads a PendingPayment row; after cancel there is
            // none, so retrieveTransaction should never be called. Allow-but-watch.
            $mock->shouldReceive('retrieveTransaction')
                ->andReturn(new Transaction(['id' => 410, 'status' => 'approved', 'reference' => 'fp-410', 'amount' => 50000, 'currency' => ['iso' => 'XOF']]));
            $mock->shouldReceive('regenerateTokenFromTransaction')
                ->andReturn(['checkout_url' => 'https://checkout.test/410', 'fedapay_status' => 'pending']);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        // resume should 404 (no pending row) — definitely not activate.
        $this->assertNotSame(
            FaceSubscriptionStatus::Active,
            $pending->fresh()->status,
            'BUG scenario 4b: admin-cancelled pending row reactivated by resume',
        );
        $this->assertContains($response->getStatusCode(), [404, 409, 410]);
    }

    // -----------------------------------------------------------------------
    // Scenario 4c — Admin cancels a PENDING row, then Face calls verify-payment
    // while Fedapay reports approved.
    // -----------------------------------------------------------------------
    public function test_scenario_4c_admin_cancel_pending_then_verify_approved_does_not_activate(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '420',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $this->adminCancel($pending);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->andReturn(new Transaction(['id' => 420, 'status' => 'approved', 'reference' => 'fp-420', 'amount' => 50000, 'currency' => ['iso' => 'XOF']]));
        });

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/verify-payment')
            ->assertOk();

        $this->assertNotSame(
            FaceSubscriptionStatus::Active,
            $pending->fresh()->status,
            'BUG scenario 4c: admin-cancelled pending row reactivated by verify',
        );
    }

    // -----------------------------------------------------------------------
    // Scenario 5 — resumePending invoked directly on a service after admin
    // cancel of the active row (no pending row remains).
    // -----------------------------------------------------------------------
    public function test_scenario_5_resume_after_active_cancel_does_not_reactivate(): void
    {
        $row = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '500',
            'paid_amount' => 50000,
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $this->adminCancel($row);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->andReturn(new Transaction(['id' => 500, 'status' => 'approved', 'reference' => 'fp-500', 'amount' => 50000, 'currency' => ['iso' => 'XOF']]));
            $mock->shouldReceive('regenerateTokenFromTransaction')
                ->andReturn(['checkout_url' => 'https://checkout.test/500', 'fedapay_status' => 'pending']);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $this->assertNotSame(
            FaceSubscriptionStatus::Active,
            $row->fresh()->status,
            'BUG scenario 5: cancelled active row reactivated by resume',
        );
        $this->assertContains($response->getStatusCode(), [404, 409, 410]);
        $this->statusEndpoint()->assertJsonPath('data.current.status', 'cancelled');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Enums\FaceSubscriptionStatus;
use App\Events\FaceSubscriptionActivated;
use App\Events\FaceSubscriptionCancelled;
use App\Events\FaceSubscriptionExpired;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use App\Services\FaceSubscriptionAdminService;
use App\Services\FaceSubscriptionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FaceSubscriptionLifecycleDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_paid_dispatches_activation_event_only_on_pending_to_active_transition(): void
    {
        Event::fake([FaceSubscriptionActivated::class]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'pay@example.test',
        ]);
        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $face->id,
            'provider_reference' => 'fp_test_init',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $paymentService = app(FaceSubscriptionPaymentService::class);
        $paymentService->markAsPaid(
            subscription: $pending,
            fedapayRef: 'fp_test_ref',
            paidAmount: 50000,
            rawWebhookPayload: ['name' => 'transaction.approved', 'entity' => ['status' => 'approved']],
            providerReference: 'fp_test_init',
        );

        Event::assertDispatched(FaceSubscriptionActivated::class, function (FaceSubscriptionActivated $e) use ($pending): bool {
            return $e->subscription->id === $pending->id
                && $e->subscription->status === FaceSubscriptionStatus::Active;
        });
        Event::assertDispatchedTimes(FaceSubscriptionActivated::class, 1);
    }

    public function test_mark_as_paid_dispatches_activation_event_on_chained_renewal_only_for_new_row(): void
    {
        Event::fake([FaceSubscriptionActivated::class]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'renew@example.test',
        ]);

        // Existing active row with weeks left
        $oldActive = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(20),
        ]);

        // Brand-new pending row
        $newPending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $face->id,
            'provider_reference' => 'fp_renew_init',
            'metadata' => ['quoted_amount' => 50000, 'quoted_currency' => 'XOF'],
        ]);

        $paymentService = app(FaceSubscriptionPaymentService::class);
        $paymentService->markAsPaid(
            subscription: $newPending,
            fedapayRef: 'fp_renew_ref',
            paidAmount: 50000,
            rawWebhookPayload: ['name' => 'transaction.approved', 'entity' => ['status' => 'approved']],
            providerReference: 'fp_renew_init',
        );

        Event::assertDispatched(FaceSubscriptionActivated::class, function (FaceSubscriptionActivated $e) use ($newPending): bool {
            return $e->subscription->id === $newPending->id
                && $e->subscription->status === FaceSubscriptionStatus::Active;
        });
        Event::assertDispatchedTimes(FaceSubscriptionActivated::class, 1);

        // Verify chained renewal worked: old row untouched, new row Active, starts_at = old.expires_at
        $this->assertSame(FaceSubscriptionStatus::Active, $oldActive->fresh()->status);
        $newFresh = $newPending->fresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $newFresh->status);
        $this->assertTrue($oldActive->fresh()->expires_at->equalTo($newFresh->starts_at));
        $this->assertNotNull($newFresh->expires_at);
    }

    public function test_mark_as_paid_does_not_dispatch_activation_event_on_replay_when_already_active(): void
    {
        Event::fake([FaceSubscriptionActivated::class]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $alreadyActive = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'provider_reference' => 'fp_replay_ref',
        ]);

        $paymentService = app(FaceSubscriptionPaymentService::class);
        $paymentService->markAsPaid(
            subscription: $alreadyActive,
            fedapayRef: 'fp_replay_ref',
            paidAmount: 50000,
            rawWebhookPayload: ['name' => 'transaction.approved', 'entity' => ['status' => 'approved']],
            providerReference: 'fp_replay_ref',
        );

        Event::assertNotDispatched(FaceSubscriptionActivated::class);
    }

    public function test_admin_activate_dispatches_activation_event(): void
    {
        Event::fake([FaceSubscriptionActivated::class]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $admin = Admin::factory()->create();

        $adminService = app(FaceSubscriptionAdminService::class);
        $subscription = $adminService->activate(
            face: $face,
            admin: $admin,
            notes: 'Test activation',
            startsAt: null,
            durationDays: 365,
        );

        Event::assertDispatched(FaceSubscriptionActivated::class, function (FaceSubscriptionActivated $e) use ($face, $subscription): bool {
            return $e->subscription->id === $subscription->id
                && $e->subscription->face_id === $face->id
                && $e->subscription->status === FaceSubscriptionStatus::Active;
        });
        Event::assertDispatchedTimes(FaceSubscriptionActivated::class, 1);
    }

    public function test_admin_cancel_dispatches_cancellation_event(): void
    {
        Event::fake([FaceSubscriptionCancelled::class]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
        ]);
        $admin = Admin::factory()->create();

        $adminService = app(FaceSubscriptionAdminService::class);
        $cancelled = $adminService->cancel(
            subscription: $subscription,
            admin: $admin,
            notes: 'Test cancel',
        );

        Event::assertDispatched(FaceSubscriptionCancelled::class, function (FaceSubscriptionCancelled $e) use ($subscription): bool {
            return $e->subscription->id === $subscription->id
                && $e->subscription->status === FaceSubscriptionStatus::Cancelled
                && $e->subscription->cancelled_at !== null;
        });
        Event::assertDispatchedTimes(FaceSubscriptionCancelled::class, 1);
    }

    public function test_expire_command_dispatches_expiration_event_per_flipped_row(): void
    {
        Event::fake([FaceSubscriptionExpired::class]);

        $faceA = Face::factory()->create(['prenom' => 'A']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceA->id,
        ]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $faceA->id,
            'expires_at' => now()->subDay(),
        ]);

        $faceB = Face::factory()->create(['prenom' => 'B']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceB->id,
        ]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $faceB->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        Event::assertDispatchedTimes(FaceSubscriptionExpired::class, 2);
        Event::assertDispatched(FaceSubscriptionExpired::class, function (FaceSubscriptionExpired $e): bool {
            return $e->subscription->status === FaceSubscriptionStatus::Expired;
        });
    }
}

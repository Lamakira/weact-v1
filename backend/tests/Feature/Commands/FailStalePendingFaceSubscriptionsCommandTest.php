<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use App\Services\FaceSubscriptionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FailStalePendingFaceSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_summary_when_no_pending_rows_exist(): void
    {
        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 0 subscription(s) to auto-fail.')
            ->expectsOutputToContain('Done. Failed: 0, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);
    }

    public function test_command_does_not_touch_fresh_pending_row_within_ttl(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $fresh = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $face->id,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 0 subscription(s) to auto-fail.')
            ->expectsOutputToContain('Done. Failed: 0, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $fresh->fresh()->status);
        $this->assertNull($fresh->fresh()->metadata['stale_pending_at'] ?? null);
    }

    public function test_command_fails_pending_row_older_than_ttl(): void
    {
        $face = Face::factory()->create(['prenom' => 'Stuck Face']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $stale = FaceSubscription::factory()->elite()->pendingPayment()->create([
            'face_id' => $face->id,
            'created_at' => now()->subHours(72),
            'updated_at' => now()->subHours(72),
            'metadata' => [
                'quoted_amount' => 40000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-stuck-elite',
                'initiated_at' => now()->subHours(72)->toIso8601String(),
            ],
        ]);

        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 1 subscription(s) to auto-fail.')
            ->expectsOutputToContain("Auto-failed face subscription #{$stale->id} (face #{$face->id}, plan: elite")
            ->expectsOutputToContain('Done. Failed: 1, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $fresh = $stale->fresh();
        $this->assertSame(FaceSubscriptionStatus::Failed, $fresh->status);
        $this->assertSame('elite', $fresh->plan->value);

        // Pre-existing metadata preserved
        $this->assertSame(40000, $fresh->metadata['quoted_amount']);
        $this->assertSame('idem-stuck-elite', $fresh->metadata['idempotency_key']);

        // New audit fields added by the cron
        $this->assertIsString($fresh->metadata['stale_pending_at']);
        $this->assertSame('auto_failed_by_cron', $fresh->metadata['stale_pending_reason']);

        // ISO8601 format for the new timestamp
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $fresh->metadata['stale_pending_at']
        );
    }

    public function test_command_handles_multiple_rows_with_mixed_ages(): void
    {
        // Fresh row (1h old) — should NOT transition
        $faceFresh = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceFresh->id]);
        $fresh = FaceSubscription::factory()->starter()->pendingPayment()->create([
            'face_id' => $faceFresh->id,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        // Stale row 50h — should transition
        $faceStale1 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceStale1->id]);
        $stale1 = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $faceStale1->id,
            'created_at' => now()->subHours(50),
            'updated_at' => now()->subHours(50),
        ]);

        // Very stale row 200h — should transition
        $faceStale2 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceStale2->id]);
        $stale2 = FaceSubscription::factory()->elite()->pendingPayment()->create([
            'face_id' => $faceStale2->id,
            'created_at' => now()->subHours(200),
            'updated_at' => now()->subHours(200),
        ]);

        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 2 subscription(s) to auto-fail.')
            ->expectsOutputToContain('Done. Failed: 2, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $fresh->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Failed, $stale1->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Failed, $stale2->fresh()->status);
    }

    public function test_command_is_idempotent_on_repeated_runs(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $stale = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $face->id,
            'created_at' => now()->subHours(72),
            'updated_at' => now()->subHours(72),
        ]);

        // First run flips
        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Done. Failed: 1, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Failed, $stale->fresh()->status);
        $firstStalePendingAt = $stale->fresh()->metadata['stale_pending_at'];

        // Second run — row no longer matches the PendingPayment filter
        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 0 subscription(s) to auto-fail.')
            ->expectsOutputToContain('Done. Failed: 0, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        // Status + metadata unchanged by the no-op second run
        $this->assertSame(FaceSubscriptionStatus::Failed, $stale->fresh()->status);
        $this->assertSame($firstStalePendingAt, $stale->fresh()->metadata['stale_pending_at']);
    }

    public function test_late_approved_webhook_after_cron_failure_is_flagged_for_manual_review(): void
    {
        Log::spy();

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $stale = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $face->id,
            'provider_reference' => 'fp_tx_stale_late_001',
            'created_at' => now()->subHours(72),
            'updated_at' => now()->subHours(72),
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-stale-late',
            ],
        ]);

        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Done. Failed: 1, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $service = app(FaceSubscriptionPaymentService::class);
        $service->markAsPaid(
            subscription: $stale->fresh(),
            fedapayRef: 'ref_stale_late_001',
            paidAmount: 25000,
            rawWebhookPayload: ['id' => 'evt_stale_late', 'name' => 'transaction.approved'],
            providerReference: 'fp_tx_stale_late_001',
        );

        $final = $stale->fresh();
        $this->assertSame(FaceSubscriptionStatus::Failed, $final->status);
        $this->assertNull($final->starts_at);
        $this->assertNull($final->expires_at);
        $this->assertSame('auto_failed_by_cron', $final->metadata['stale_pending_reason']);
        $this->assertSame('manual_review_required', $final->metadata['late_approved_after_local_failure_reason']);
        $this->assertSame('ref_stale_late_001', $final->metadata['late_approved_fedapay_reference']);
        $this->assertSame('fp_tx_stale_late_001', $final->metadata['late_approved_provider_reference']);
        $this->assertSame(25000, $final->metadata['late_approved_paid_amount']);

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message, array $context) use ($stale, $face): bool {
                return $message === 'Fedapay webhook: approved payment arrived after local face subscription failure — manual review required'
                    && ($context['face_subscription_id'] ?? null) === $stale->id
                    && ($context['face_id'] ?? null) === $face->id
                    && ($context['local_failure_source'] ?? null) === 'auto_failed_by_cron';
            })
            ->once();
    }

    public function test_command_respects_configurable_ttl_boundary(): void
    {
        config(['face_subscription_tiers.stale_pending_max_hours' => 24]);

        // 23h old — below the 24h TTL — should NOT transition
        $faceBelow = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceBelow->id]);
        $below = FaceSubscription::factory()->starter()->pendingPayment()->create([
            'face_id' => $faceBelow->id,
            'created_at' => now()->subHours(23),
            'updated_at' => now()->subHours(23),
        ]);

        // 25h old — above the 24h TTL — should transition
        $faceAbove = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceAbove->id]);
        $above = FaceSubscription::factory()->starter()->pendingPayment()->create([
            'face_id' => $faceAbove->id,
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 1 subscription(s) to auto-fail.')
            ->expectsOutputToContain('Done. Failed: 1, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $below->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Failed, $above->fresh()->status);
    }

    public function test_command_does_not_touch_row_at_exact_ttl_boundary(): void
    {
        config(['face_subscription_tiers.stale_pending_max_hours' => 24]);

        $this->travelTo(now()->startOfSecond());

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        // created_at = now() - 24h exactly. cron's cutoff = now() - 24h.
        // Filter is `created_at < cutoff` (strict <), so a row at the exact
        // boundary is NOT stale yet — it gets "one more tick" of grace.
        $boundary = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $face->id,
            'created_at' => now()->subHours(24),
            'updated_at' => now()->subHours(24),
        ]);

        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 0 subscription(s) to auto-fail.')
            ->expectsOutputToContain('Done. Failed: 0, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $boundary->fresh()->status);
    }

    public function test_command_ignores_non_pending_rows_regardless_of_age(): void
    {
        $faceActive = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceActive->id]);
        $active = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $faceActive->id,
            'created_at' => now()->subHours(200),
            'updated_at' => now()->subHours(200),
        ]);

        $faceExpired = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceExpired->id]);
        $expired = FaceSubscription::factory()->starter()->expired()->create([
            'face_id' => $faceExpired->id,
            'created_at' => now()->subHours(200),
            'updated_at' => now()->subHours(200),
        ]);

        $faceCancelled = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceCancelled->id]);
        $cancelled = FaceSubscription::factory()->elite()->cancelled()->create([
            'face_id' => $faceCancelled->id,
            'created_at' => now()->subHours(200),
            'updated_at' => now()->subHours(200),
        ]);

        $faceFailed = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceFailed->id]);
        $failed = FaceSubscription::factory()->pro()->failed()->create([
            'face_id' => $faceFailed->id,
            'created_at' => now()->subHours(200),
            'updated_at' => now()->subHours(200),
        ]);

        $this->artisan('subscriptions:fail-stale-pending')
            ->expectsOutputToContain('Found 0 subscription(s) to auto-fail.')
            ->expectsOutputToContain('Done. Failed: 0, Skipped: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Active, $active->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Expired, $expired->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Failed, $failed->fresh()->status);
    }

    public function test_command_log_payload_contains_face_subscription_context_on_fail(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $stale = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $face->id,
            'created_at' => now()->subHours(72),
            'updated_at' => now()->subHours(72),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($stale, $face): bool {
                return $message === 'Face subscription auto-failed by stale-pending command'
                    && ($context['face_subscription_id'] ?? null) === $stale->id
                    && ($context['face_id'] ?? null) === $face->id
                    && ($context['plan'] ?? null) === 'pro'
                    && ($context['stale_hours_threshold'] ?? null) === 48
                    && array_key_exists('created_at', $context);
            });

        $this->artisan('subscriptions:fail-stale-pending')->assertExitCode(0);
    }
}

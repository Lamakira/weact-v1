<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Enums\FaceSubscriptionTier;
use App\Events\FaceSubscriptionExpired;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceSubscriptionAudit;
use App\Models\FaceVideo;
use App\Models\User;
use App\Services\FaceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpireFaceSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_active_subscription_whose_expires_at_is_in_the_past(): void
    {
        $face = Face::factory()->create(['prenom' => 'Stale Active']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame(FaceSubscriptionStatus::Active, $subscription->fresh()->status);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 1 subscription(s) to expire.')
            ->expectsOutputToContain("Expired face subscription #{$subscription->id} (face #{$face->id}, plan: pro)")
            ->expectsOutputToContain('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $subscription->fresh()->status);
    }

    public function test_command_does_not_touch_active_subscription_in_future_window(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
        ]);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 0 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_command_expires_active_subscription_at_exact_boundary_second(): void
    {
        $this->travelTo(now()->startOfSecond());

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear(),
            'expires_at' => now(),
        ]);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 1 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $subscription->fresh()->status);
    }

    public function test_command_outputs_summary_when_no_eligible_rows_exist(): void
    {
        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 0 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);
    }

    public function test_command_is_idempotent_across_consecutive_runs(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $expiresAtBefore = $subscription->expires_at?->toIso8601String();

        // First run — expires the row.
        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 1 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $subscription->fresh()->status);

        // Second run — no-op because the row is no longer Active.
        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 0 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $subscription->fresh()->status);
        $this->assertSame($expiresAtBefore, $subscription->fresh()->expires_at?->toIso8601String());
    }

    public function test_command_counts_skipped_when_row_becomes_ineligible_after_outer_query(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $mutated = false;
        FaceSubscription::retrieved(function (FaceSubscription $retrieved) use ($subscription, &$mutated): void {
            if ($mutated || $retrieved->id !== $subscription->id) {
                return;
            }

            $mutated = true;
            DB::table('face_subscriptions')
                ->where('id', $retrieved->id)
                ->update(['expires_at' => now()->addYear()]);
        });

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 1 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 0, Skipped: 1, Failed: 0.')
            ->assertExitCode(0);

        $fresh = $subscription->fresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $fresh->status);
        $this->assertTrue($fresh->expires_at->isFuture());
    }

    public function test_command_does_not_touch_pending_cancelled_failed_or_already_expired_rows(): void
    {
        $faces = [];
        foreach (['pending', 'cancelled', 'failed', 'expired'] as $label) {
            $face = Face::factory()->create(['prenom' => "Face {$label}"]);
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
            $faces[$label] = $face;
        }

        $pending = FaceSubscription::factory()->starter()->pendingPayment()->create([
            'face_id' => $faces['pending']->id,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
        $cancelled = FaceSubscription::factory()->pro()->cancelled()->create([
            'face_id' => $faces['cancelled']->id,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
        $failed = FaceSubscription::factory()->elite()->failed()->create([
            'face_id' => $faces['failed']->id,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
        $alreadyExpired = FaceSubscription::factory()->starter()->expired()->create([
            'face_id' => $faces['expired']->id,
        ]);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 0 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pending->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Failed, $failed->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Expired, $alreadyExpired->fresh()->status);
        $this->assertNotNull($cancelled->fresh()->cancelled_at);

        // Plan column not mutated — the filter is status-only, tier-blind.
        $this->assertSame(FaceSubscriptionPlan::Starter, $pending->fresh()->plan);
        $this->assertSame(FaceSubscriptionPlan::Pro, $cancelled->fresh()->plan);
        $this->assertSame(FaceSubscriptionPlan::Elite, $failed->fresh()->plan);
        $this->assertSame(FaceSubscriptionPlan::Starter, $alreadyExpired->fresh()->plan);
    }

    public function test_command_does_not_delete_album_photos_or_videos_on_disk(): void
    {
        Storage::fake('public');

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FacePhoto::factory()->createSequentialForFace($face, 6);

        $face->update([
            'presentation_video' => 'presentation.mp4',
            'presentation_video_thumbnail' => 'presentation-thumb.jpg',
        ]);
        FaceVideo::factory()->acting()->create([
            'face_id' => $face->id,
            'filename' => 'acting.mp4',
            'thumbnail' => 'acting-thumb.jpg',
        ]);
        FaceVideo::factory()->ugc()->create([
            'face_id' => $face->id,
            'filename' => 'ugc.mp4',
            'thumbnail' => 'ugc-thumb.jpg',
        ]);

        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->put('avatars/faces/albums/'.$photo->filename, 'photo content');
        }
        Storage::disk('public')->put('videos/faces/acting/acting.mp4', 'acting content');
        Storage::disk('public')->put('videos/faces/acting/acting-thumb.jpg', 'acting thumb');
        Storage::disk('public')->put('videos/faces/ugc/ugc.mp4', 'ugc content');
        Storage::disk('public')->put('videos/faces/ugc/ugc-thumb.jpg', 'ugc thumb');
        Storage::disk('public')->put('videos/faces/presentation/presentation.mp4', 'presentation content');
        Storage::disk('public')->put('videos/faces/presentation/presentation-thumb.jpg', 'presentation thumb');

        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
        }
        Storage::disk('public')->assertExists('videos/faces/acting/acting.mp4');
        Storage::disk('public')->assertExists('videos/faces/acting/acting-thumb.jpg');
        Storage::disk('public')->assertExists('videos/faces/ugc/ugc.mp4');
        Storage::disk('public')->assertExists('videos/faces/ugc/ugc-thumb.jpg');
        Storage::disk('public')->assertExists('videos/faces/presentation/presentation.mp4');
        Storage::disk('public')->assertExists('videos/faces/presentation/presentation-thumb.jpg');

        $this->assertSame(6, FacePhoto::where('face_id', $face->id)->count());
        $this->assertSame(2, FaceVideo::where('face_id', $face->id)->count());
        $this->assertSame('presentation.mp4', $face->fresh()->presentation_video);
    }

    public function test_command_does_not_modify_faces_is_featured(): void
    {
        $face = Face::factory()->create(['is_featured' => true]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        $this->assertTrue($face->fresh()->is_featured);
    }

    public function test_command_writes_no_audit_row_for_expiration(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->subDay(),
        ]);

        $beforeCount = FaceSubscriptionAudit::query()->count();

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        $this->assertSame($beforeCount, FaceSubscriptionAudit::query()->count());
    }

    public function test_command_expires_only_old_row_during_chained_renewal_keeping_new_row_active(): void
    {
        $face = Face::factory()->create(['prenom' => 'Chained Renewal']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $oldRow = FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
            'paid_amount' => FaceSubscriptionPlan::Starter->price(),
            'currency' => 'XOF',
            'provider' => 'fedapay',
            'provider_reference' => 'fp_old_starter_'.now()->format('YmdHis'),
        ]);

        $newRow = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $face->id,
            'starts_at' => $oldRow->expires_at,
            'expires_at' => $oldRow->expires_at->copy()->addYear(),
            'paid_amount' => FaceSubscriptionPlan::Pro->price(),
            'currency' => 'XOF',
            'provider' => 'fedapay',
            'provider_reference' => 'fp_new_pro_'.now()->format('YmdHis'),
        ]);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 1 subscription(s) to expire.')
            ->expectsOutputToContain('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $oldRow->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Active, $newRow->fresh()->status);

        $face->load('activeSubscription');
        $this->assertNotNull($face->activeSubscription);
        $this->assertSame($newRow->id, $face->activeSubscription->id);

        // Plan column not mutated by the expiration command — old stays Starter, new stays Pro.
        $this->assertSame(FaceSubscriptionPlan::Starter, $oldRow->fresh()->plan);
        $this->assertSame(FaceSubscriptionPlan::Pro, $newRow->fresh()->plan);
        // The Face's resolved tier is Pro (new row wins the activeSubscription read).
        $this->assertSame(FaceSubscriptionTier::Pro, app(FaceEntitlementService::class)->capabilities($face->fresh())->tier);
    }

    public function test_expired_face_no_longer_shown_in_public_listing_bucket_zero(): void
    {
        $faceA = Face::factory()->create([
            'prenom' => 'Face A — stale active',
            'is_featured' => false,
            'profile_photo' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceA->id]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $faceA->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $faceB = Face::factory()->create([
            'prenom' => 'Face B — fresh active',
            'is_featured' => false,
            'profile_photo' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subHours(2),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceB->id]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $faceB->id,
        ]);

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        // The public order now comes from the materialized ranking: without a
        // rebuild the ranks table is empty and the listing falls back to
        // id DESC, which would make the assertions below pass vacuously.
        // Rebuilding AFTER the expiration classifies faceA in the free queue
        // and faceB in its paid-tier queue — the order becomes meaningful.
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');
        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertSame($faceB->uuid, $ids[0]);
        $this->assertContains($faceA->uuid, $ids);
        $faceAIndex = array_search($faceA->uuid, $ids, true);
        $faceBIndex = array_search($faceB->uuid, $ids, true);
        $this->assertGreaterThan($faceBIndex, $faceAIndex);

        $this->assertSame(FaceSubscriptionTier::Free, app(FaceEntitlementService::class)->capabilities($faceA->fresh())->tier);
    }

    public function test_expired_face_album_photos_and_acting_video_are_masked_publicly(): void
    {
        $face = Face::factory()->create(['username' => 'masked-after-expiry']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FacePhoto::factory()->createSequentialForFace($face, 4);
        FaceVideo::factory()->acting()->create([
            'face_id' => $face->id,
            'filename' => 'video.mp4',
        ]);

        FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");
        $response->assertOk()
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 1)
            ->assertJsonCount(0, 'data.videos');

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
        $this->assertSame(1, FaceVideo::where('face_id', $face->id)->count());

        $latestSub = FaceSubscription::query()
            ->where('face_id', $face->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($latestSub);
        $this->assertSame(FaceSubscriptionStatus::Expired, $latestSub->status);
    }

    public function test_command_sends_no_mail_or_notification_when_subscriptions_expire(): void
    {
        // FP-1.9 introduces FaceSubscriptionExpired listeners (in-app + email). The command itself
        // still does no I/O — it only dispatches the event. Faking the event proves the command's
        // own surface stays mail-free and notification-free.
        Event::fake([FaceSubscriptionExpired::class]);
        Mail::fake();
        Notification::fake();

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();

        // Positive event-dispatch assertion: the FaceSubscriptionExpired event IS fired with
        // the correct payload (FP-2.9 listeners will consume $event->subscription->plan).
        Event::assertDispatched(FaceSubscriptionExpired::class, function (FaceSubscriptionExpired $e) use ($subscription): bool {
            return $e->subscription->id === $subscription->id
                && $e->subscription->status === FaceSubscriptionStatus::Expired
                && $e->subscription->plan === FaceSubscriptionPlan::Pro;
        });
        Event::assertDispatchedTimes(FaceSubscriptionExpired::class, 1);
    }

    public function test_command_expires_starter_active_subscription(): void
    {
        Event::fake([FaceSubscriptionExpired::class]);

        $face = Face::factory()->create(['prenom' => 'Stale Starter']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame(FaceSubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertSame(FaceSubscriptionPlan::Starter, $subscription->fresh()->plan);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 1 subscription(s) to expire.')
            ->expectsOutputToContain("Expired face subscription #{$subscription->id} (face #{$face->id}, plan: starter)")
            ->expectsOutputToContain('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $subscription->fresh()->status);
        $this->assertSame(FaceSubscriptionPlan::Starter, $subscription->fresh()->plan);

        Event::assertDispatched(FaceSubscriptionExpired::class, function (FaceSubscriptionExpired $e) use ($subscription): bool {
            return $e->subscription->id === $subscription->id
                && $e->subscription->plan === FaceSubscriptionPlan::Starter
                && $e->subscription->status === FaceSubscriptionStatus::Expired;
        });
    }

    public function test_command_expires_elite_active_subscription(): void
    {
        Event::fake([FaceSubscriptionExpired::class]);

        $face = Face::factory()->create(['prenom' => 'Stale Élite']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain("Expired face subscription #{$subscription->id} (face #{$face->id}, plan: elite)")
            ->expectsOutputToContain('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $subscription->fresh()->status);
        $this->assertSame(FaceSubscriptionPlan::Elite, $subscription->fresh()->plan);

        Event::assertDispatched(FaceSubscriptionExpired::class, function (FaceSubscriptionExpired $e) use ($subscription): bool {
            return $e->subscription->id === $subscription->id
                && $e->subscription->plan === FaceSubscriptionPlan::Elite;
        });
    }

    public function test_command_expires_all_three_paid_tiers_in_single_run_with_correct_plan_logging(): void
    {
        Event::fake([FaceSubscriptionExpired::class]);

        $faceStarter = Face::factory()->create(['prenom' => 'Tier Starter']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceStarter->id]);
        $subStarter = FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $faceStarter->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $facePro = Face::factory()->create(['prenom' => 'Tier Pro']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $facePro->id]);
        $subPro = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $facePro->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $faceElite = Face::factory()->create(['prenom' => 'Tier Élite']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceElite->id]);
        $subElite = FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $faceElite->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 3 subscription(s) to expire.')
            ->expectsOutputToContain('plan: starter')
            ->expectsOutputToContain('plan: pro')
            ->expectsOutputToContain('plan: elite')
            ->expectsOutputToContain('Done. Expired: 3, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertSame(FaceSubscriptionStatus::Expired, $subStarter->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Expired, $subPro->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::Expired, $subElite->fresh()->status);

        Event::assertDispatchedTimes(FaceSubscriptionExpired::class, 3);
        Event::assertDispatched(FaceSubscriptionExpired::class, fn (FaceSubscriptionExpired $e): bool => $e->subscription->plan === FaceSubscriptionPlan::Starter);
        Event::assertDispatched(FaceSubscriptionExpired::class, fn (FaceSubscriptionExpired $e): bool => $e->subscription->plan === FaceSubscriptionPlan::Pro);
        Event::assertDispatched(FaceSubscriptionExpired::class, fn (FaceSubscriptionExpired $e): bool => $e->subscription->plan === FaceSubscriptionPlan::Elite);
    }

    public function test_log_payload_contains_plan_key_for_expired_subscription(): void
    {
        Event::fake([FaceSubscriptionExpired::class]);
        Mail::fake();
        Notification::fake();

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($subscription): bool {
                return $message === 'Face subscription expired by scheduled command'
                    && ($context['face_subscription_id'] ?? null) === $subscription->id
                    && ($context['face_id'] ?? null) === $subscription->face_id
                    && ($context['plan'] ?? null) === 'pro'
                    && array_key_exists('previous_expires_at', $context);
            });

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);
    }

    public function test_log_error_payload_contains_plan_key_when_transaction_throws(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $subscription = FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
        ]);

        // Force the inner UPDATE to throw — fires only on the command's status-flip attempt
        // (factory creates above use INSERT → 'creating', not 'updating'), so test isolation is clean.
        $shouldThrow = true;
        FaceSubscription::updating(function (FaceSubscription $row) use (&$shouldThrow): void {
            if (! $shouldThrow) {
                return;
            }

            if ($row->status === FaceSubscriptionStatus::Expired) {
                $shouldThrow = false;
                throw new \RuntimeException('Forced failure for test');
            }
        });

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($subscription): bool {
                return $message === 'Face subscription expiration failed'
                    && ($context['face_subscription_id'] ?? null) === $subscription->id
                    && ($context['face_id'] ?? null) === $subscription->face_id
                    && ($context['plan'] ?? null) === 'elite'
                    && array_key_exists('error_class', $context)
                    && array_key_exists('error_message', $context);
            });

        $this->artisan('subscriptions:expire-faces')
            ->expectsOutputToContain('Found 1 subscription(s) to expire.')
            ->expectsOutputToContain("Failed to expire face subscription #{$subscription->id}")
            ->expectsOutputToContain('Done. Expired: 0, Skipped: 0, Failed: 1.')
            ->assertExitCode(0);

        // Row stayed Active because the UPDATE threw and the transaction rolled back.
        $this->assertSame(FaceSubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertSame(FaceSubscriptionPlan::Elite, $subscription->fresh()->plan);
    }
}

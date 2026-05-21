<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceSubscriptionAudit;
use App\Models\User;
use App\Services\FaceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->expectsOutputToContain("Expired face subscription #{$subscription->id}")
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

        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $faces['pending']->id,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
        $cancelled = FaceSubscription::factory()->cancelled()->create([
            'face_id' => $faces['cancelled']->id,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
        $failed = FaceSubscription::factory()->failed()->create([
            'face_id' => $faces['failed']->id,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
        $alreadyExpired = FaceSubscription::factory()->expired()->create([
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
    }

    public function test_command_does_not_delete_album_photos_or_videos_on_disk(): void
    {
        Storage::fake('public');

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FacePhoto::factory()->createSequentialForFace($face, 4);

        $face->update([
            'acting_video' => 'acting.mp4',
            'acting_video_thumbnail' => 'acting-thumb.jpg',
            'presentation_video' => 'presentation.mp4',
            'presentation_video_thumbnail' => 'presentation-thumb.jpg',
        ]);

        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->put('avatars/faces/albums/'.$photo->filename, 'photo content');
        }
        Storage::disk('public')->put('videos/faces/acting/acting.mp4', 'acting content');
        Storage::disk('public')->put('videos/faces/acting/acting-thumb.jpg', 'acting thumb');
        Storage::disk('public')->put('videos/faces/presentation/presentation.mp4', 'presentation content');
        Storage::disk('public')->put('videos/faces/presentation/presentation-thumb.jpg', 'presentation thumb');

        FaceSubscription::factory()->active()->create([
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
        Storage::disk('public')->assertExists('videos/faces/presentation/presentation.mp4');
        Storage::disk('public')->assertExists('videos/faces/presentation/presentation-thumb.jpg');

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
        $this->assertSame('acting.mp4', $face->fresh()->acting_video);
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

        $oldRow = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(366),
            'expires_at' => now()->subDay(),
            'paid_amount' => 50000,
            'currency' => 'XOF',
            'provider' => 'fedapay',
            'provider_reference' => 'fp_old_'.now()->format('YmdHis'),
        ]);

        $newRow = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'starts_at' => $oldRow->expires_at,
            'expires_at' => $oldRow->expires_at->copy()->addYear(),
            'paid_amount' => 50000,
            'currency' => 'XOF',
            'provider' => 'fedapay',
            'provider_reference' => 'fp_new_'.now()->format('YmdHis'),
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
        $this->assertTrue(app(FaceEntitlementService::class)->isPremium($face->fresh()));
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

        $response = $this->getJson('/api/v1/public/faces?per_page=10');
        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertSame($faceB->uuid, $ids[0]);
        $this->assertContains($faceA->uuid, $ids);
        $faceAIndex = array_search($faceA->uuid, $ids, true);
        $faceBIndex = array_search($faceB->uuid, $ids, true);
        $this->assertGreaterThan($faceBIndex, $faceAIndex);

        $this->assertFalse(app(FaceEntitlementService::class)->isPremium($faceA->fresh()));
        $this->assertFalse(app(FaceEntitlementService::class)->isFeaturedBySubscription($faceA->fresh()));
    }

    public function test_expired_face_album_photos_and_acting_video_are_masked_publicly(): void
    {
        $face = Face::factory()->create(['username' => 'masked-after-expiry']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FacePhoto::factory()->createSequentialForFace($face, 4);
        $face->update([
            'acting_video' => 'video.mp4',
            'acting_video_thumbnail' => 'video-thumb.jpg',
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
            ->assertJsonPath('data.has_acting_video', false)
            ->assertJsonPath('data.acting_video_url', null);

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
        $this->assertSame('video.mp4', $face->fresh()->acting_video);

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
        \Illuminate\Support\Facades\Event::fake([\App\Events\FaceSubscriptionExpired::class]);
        Mail::fake();
        Notification::fake();

        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-faces')->assertExitCode(0);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }
}

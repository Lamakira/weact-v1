<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Mail\FaceSubscriptionRenewalReminderMail;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemindFaceSubscriptionRenewalsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_30_day_reminder_in_the_band_inclusive_lower_bound(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => '30d Band Lower']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'face30lower@example.test',
        ]);
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addMinutes(5),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 1 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain("30-day reminder sent for face subscription #{$subscription->id}")
            ->expectsOutputToContain('Done. Sent30d: 1, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertNotNull($subscription->fresh()->reminder_30d_sent_at);
        $this->assertNull($subscription->fresh()->reminder_7d_sent_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'face_subscription_renewal_reminder_30d',
        ]);
        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->hasTo('face30lower@example.test')
                && $mail->subscription->id === $subscription->id
                && $mail->daysRemaining === 30;
        });
    }

    public function test_command_sends_7_day_reminder_only_when_inside_7d_band(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => '7d Band']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'face7@example.test',
        ]);
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(6)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 0 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Found 1 subscription(s) needing 7-day reminder.')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 1, Failed: 0.')
            ->assertExitCode(0);

        $this->assertNull($subscription->fresh()->reminder_30d_sent_at);
        $this->assertNotNull($subscription->fresh()->reminder_7d_sent_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'face_subscription_renewal_reminder_7d',
        ]);
        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->hasTo('face7@example.test')
                && $mail->subscription->id === $subscription->id
                && $mail->daysRemaining === 7;
        });
    }

    public function test_command_is_idempotent_for_already_reminded_rows(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Already Reminded']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'already@example.test',
        ]);
        $alreadySentAt = now()->subDays(2);
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addHours(6),
            'reminder_30d_sent_at' => $alreadySentAt,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 0 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'type' => 'face_subscription_renewal_reminder_30d',
        ]);
        // reminder_30d_sent_at must NOT have been overwritten by the new run
        $this->assertTrue($subscription->fresh()->reminder_30d_sent_at->lessThan(now()->subDay()));
    }

    public function test_command_does_not_remind_non_active_subscriptions(): void
    {
        Mail::fake();

        $faces = [];
        foreach (['pending', 'cancelled', 'failed', 'expired'] as $label) {
            $face = Face::factory()->create(['prenom' => "Face {$label}"]);
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "{$label}@example.test",
            ]);
            $faces[$label] = $face;
        }

        FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $faces['pending']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);
        FaceSubscription::factory()->cancelled()->create([
            'face_id' => $faces['cancelled']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);
        FaceSubscription::factory()->failed()->create([
            'face_id' => $faces['failed']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);
        FaceSubscription::factory()->expired()->create([
            'face_id' => $faces['expired']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 0 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Found 0 subscription(s) needing 7-day reminder.')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_handles_two_subscriptions_in_30d_band_and_one_in_7d_band_in_single_run(): void
    {
        Mail::fake();

        $faceA = Face::factory()->create(['prenom' => 'Face A']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceA->id,
            'email' => 'a@example.test',
        ]);
        $subA = FaceSubscription::factory()->active()->create([
            'face_id' => $faceA->id,
            'expires_at' => now()->addDays(29)->addHours(3),
        ]);

        $faceB = Face::factory()->create(['prenom' => 'Face B']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceB->id,
            'email' => 'b@example.test',
        ]);
        $subB = FaceSubscription::factory()->active()->create([
            'face_id' => $faceB->id,
            'expires_at' => now()->addDays(29)->addHours(20),
        ]);

        $faceC = Face::factory()->create(['prenom' => 'Face C']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceC->id,
            'email' => 'c@example.test',
        ]);
        $subC = FaceSubscription::factory()->active()->create([
            'face_id' => $faceC->id,
            'expires_at' => now()->addDays(6)->addHours(5),
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 2 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Found 1 subscription(s) needing 7-day reminder.')
            ->expectsOutputToContain('Done. Sent30d: 2, Sent7d: 1, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertQueuedCount(3);
        $this->assertNotNull($subA->fresh()->reminder_30d_sent_at);
        $this->assertNotNull($subB->fresh()->reminder_30d_sent_at);
        $this->assertNotNull($subC->fresh()->reminder_7d_sent_at);
        $this->assertNull($subA->fresh()->reminder_7d_sent_at);
        $this->assertNull($subC->fresh()->reminder_30d_sent_at);
    }

    public function test_command_outputs_zero_summary_when_no_eligible_rows_exist(): void
    {
        Mail::fake();

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 0 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Found 0 subscription(s) needing 7-day reminder.')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('notifications', 0);
    }
}

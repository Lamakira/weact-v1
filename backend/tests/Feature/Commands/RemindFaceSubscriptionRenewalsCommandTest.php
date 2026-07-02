<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\FaceSubscriptionPlan;
use App\Mail\FaceSubscriptionRenewalReminderMail;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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
        $subscription = FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addMinutes(5),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 1 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain("30-day reminder sent for face subscription #{$subscription->id} (face #{$subscription->face_id}, plan: pro)")
            ->expectsOutputToContain('Done. Sent30d: 1, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertNotNull($subscription->fresh()->reminder_30d_sent_at);
        $this->assertNull($subscription->fresh()->reminder_7d_sent_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'face_subscription_renewal_reminder_30d',
        ]);
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_renewal_reminder_30d')
            ->firstOrFail();
        $this->assertStringContainsString('Pro', $notification->data['message']);
        $this->assertStringContainsString('photos 2 à 4 d\'album', $notification->data['message']);
        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->hasTo('face30lower@example.test')
                && $mail->subscription->id === $subscription->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Pro
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
        $subscription = FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(6)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 0 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Found 1 subscription(s) needing 7-day reminder.')
            ->expectsOutputToContain('plan: pro')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 1, Failed: 0.')
            ->assertExitCode(0);

        $this->assertNull($subscription->fresh()->reminder_30d_sent_at);
        $this->assertNotNull($subscription->fresh()->reminder_7d_sent_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'face_subscription_renewal_reminder_7d',
        ]);
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_renewal_reminder_7d')
            ->firstOrFail();
        $this->assertStringContainsString('Pro', $notification->data['message']);
        $this->assertStringContainsString('photos 2 à 4 d\'album', $notification->data['message']);
        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->hasTo('face7@example.test')
                && $mail->subscription->id === $subscription->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Pro
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
        $subscription = FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addHours(6),
            'reminder_30d_sent_at' => $alreadySentAt,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 0 subscription(s) needing 30-day reminder.')
            ->doesntExpectOutputToContain('plan:')
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

        FaceSubscription::factory()->pendingPayment()->starter()->create([
            'face_id' => $faces['pending']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);
        FaceSubscription::factory()->cancelled()->pro()->create([
            'face_id' => $faces['cancelled']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);
        FaceSubscription::factory()->failed()->elite()->create([
            'face_id' => $faces['failed']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);
        FaceSubscription::factory()->expired()->pro()->create([
            'face_id' => $faces['expired']->id,
            'expires_at' => now()->addDays(29)->addHours(6),
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 0 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Found 0 subscription(s) needing 7-day reminder.')
            ->doesntExpectOutputToContain('plan:')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_handles_two_subscriptions_in_30d_band_and_one_in_7d_band_in_single_run(): void
    {
        Mail::fake();

        $faceA = Face::factory()->create(['prenom' => 'Face A']);
        $userA = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceA->id,
            'email' => 'a@example.test',
        ]);
        $subA = FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $faceA->id,
            'expires_at' => now()->addDays(29)->addHours(3),
        ]);

        $faceB = Face::factory()->create(['prenom' => 'Face B']);
        $userB = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceB->id,
            'email' => 'b@example.test',
        ]);
        $subB = FaceSubscription::factory()->active()->starter()->create([
            'face_id' => $faceB->id,
            'expires_at' => now()->addDays(29)->addHours(20),
        ]);

        $faceC = Face::factory()->create(['prenom' => 'Face C']);
        $userC = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceC->id,
            'email' => 'c@example.test',
        ]);
        $subC = FaceSubscription::factory()->active()->elite()->create([
            'face_id' => $faceC->id,
            'expires_at' => now()->addDays(6)->addHours(5),
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Found 2 subscription(s) needing 30-day reminder.')
            ->expectsOutputToContain('Found 1 subscription(s) needing 7-day reminder.')
            ->expectsOutputToContain('plan: pro')
            ->expectsOutputToContain('plan: starter')
            ->expectsOutputToContain('plan: elite')
            ->expectsOutputToContain('Done. Sent30d: 2, Sent7d: 1, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertQueuedCount(3);
        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subA): bool {
            return $mail->subscription->id === $subA->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Pro
                && $mail->daysRemaining === 30;
        });
        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subB): bool {
            return $mail->subscription->id === $subB->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Starter
                && $mail->daysRemaining === 30;
        });
        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subC): bool {
            return $mail->subscription->id === $subC->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Elite
                && $mail->daysRemaining === 7;
        });
        $this->assertNotNull($subA->fresh()->reminder_30d_sent_at);
        $this->assertNotNull($subB->fresh()->reminder_30d_sent_at);
        $this->assertNotNull($subC->fresh()->reminder_7d_sent_at);
        $this->assertNull($subA->fresh()->reminder_7d_sent_at);
        $this->assertNull($subC->fresh()->reminder_30d_sent_at);

        $notificationA = Notification::where('user_id', $userA->id)
            ->where('type', 'face_subscription_renewal_reminder_30d')
            ->firstOrFail();
        $this->assertStringContainsString('Pro', $notificationA->data['message']);
        $this->assertStringContainsString('vos photos 2 à 4 d\'album, votre vidéo de présentation et votre vidéo de jeu', $notificationA->data['message']);

        $notificationB = Notification::where('user_id', $userB->id)
            ->where('type', 'face_subscription_renewal_reminder_30d')
            ->firstOrFail();
        $this->assertStringContainsString('Starter', $notificationB->data['message']);
        $this->assertStringContainsString('votre 2ème photo d\'album et votre vidéo de présentation', $notificationB->data['message']);

        $notificationC = Notification::where('user_id', $userC->id)
            ->where('type', 'face_subscription_renewal_reminder_7d')
            ->firstOrFail();
        $this->assertStringContainsString('Élite', $notificationC->data['message']);
        $this->assertStringContainsString('vos photos 2 à 6 d\'album, votre vidéo de présentation, vos 2 vidéos de jeu et votre vidéo UGC', $notificationC->data['message']);
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

    public function test_command_sends_30_day_reminder_for_starter_with_starter_specific_copy(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Starter 30d']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'starter30@example.test',
        ]);
        $subscription = FaceSubscription::factory()->active()->starter()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('plan: starter')
            ->expectsOutputToContain('Done. Sent30d: 1, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->subscription->id === $subscription->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Starter
                && $mail->daysRemaining === 30;
        });

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_renewal_reminder_30d')
            ->firstOrFail();
        $this->assertStringContainsString('Starter', $notification->data['message']);
        $this->assertStringContainsString('votre 2ème photo d\'album et votre vidéo de présentation', $notification->data['message']);
        $this->assertStringNotContainsString('photos 2 à 4', $notification->data['message']);
    }

    public function test_command_sends_30_day_reminder_for_elite_with_elite_specific_copy(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Elite 30d']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'elite30@example.test',
        ]);
        $subscription = FaceSubscription::factory()->active()->elite()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('plan: elite')
            ->expectsOutputToContain('Done. Sent30d: 1, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->subscription->id === $subscription->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Elite
                && $mail->daysRemaining === 30;
        });

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_renewal_reminder_30d')
            ->firstOrFail();
        $this->assertStringContainsString('Élite', $notification->data['message']);
        $this->assertStringContainsString('vos photos 2 à 6 d\'album', $notification->data['message']);
        $this->assertStringContainsString('vos 2 vidéos de jeu', $notification->data['message']);
        $this->assertStringContainsString('votre vidéo UGC', $notification->data['message']);
        $this->assertStringNotContainsString('photos 2 à 4', $notification->data['message']);
        $this->assertStringNotContainsString('2ème photo', $notification->data['message']);
    }

    public function test_command_sends_7_day_reminder_for_starter(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Starter 7d']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'starter7@example.test',
        ]);
        $subscription = FaceSubscription::factory()->active()->starter()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(6)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('plan: starter')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 1, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->subscription->id === $subscription->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Starter
                && $mail->daysRemaining === 7;
        });

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_renewal_reminder_7d')
            ->firstOrFail();
        $this->assertStringStartsWith('Rappel : votre abonnement Starter expire dans 7 jours', $notification->data['message']);
        $this->assertStringContainsString('votre 2ème photo d\'album et votre vidéo de présentation', $notification->data['message']);
    }

    public function test_command_sends_7_day_reminder_for_elite(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Elite 7d']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'elite7@example.test',
        ]);
        $subscription = FaceSubscription::factory()->active()->elite()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(6)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('plan: elite')
            ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 1, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertQueued(FaceSubscriptionRenewalReminderMail::class, function (FaceSubscriptionRenewalReminderMail $mail) use ($subscription): bool {
            return $mail->subscription->id === $subscription->id
                && $mail->subscription->plan === FaceSubscriptionPlan::Elite
                && $mail->daysRemaining === 7;
        });

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_renewal_reminder_7d')
            ->firstOrFail();
        $this->assertStringStartsWith('Rappel : votre abonnement Élite expire dans 7 jours', $notification->data['message']);
        $this->assertStringContainsString('vos photos 2 à 6 d\'album, votre vidéo de présentation, vos 2 vidéos de jeu et votre vidéo UGC', $notification->data['message']);
    }

    public function test_command_emits_log_info_with_plan_field_on_successful_30_day_reminder(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Log Info Pro']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'loginfo@example.test',
        ]);
        FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Face subscription 30-day reminder dispatched by scheduled command'
                    && ($context['plan'] ?? null) === 'pro'
                    && ($context['days_remaining'] ?? null) === 30
                    && array_key_exists('face_subscription_id', $context)
                    && array_key_exists('face_id', $context)
                    && array_key_exists('expires_at', $context);
            });

        $this->artisan('subscriptions:remind-face-renewals')->assertExitCode(0);
    }

    public function test_command_emits_log_error_with_plan_field_on_failed_30_day_reminder(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Log Error Pro']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'logerror@example.test',
        ]);
        FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Face subscription 30d reminder failed'
                    && ($context['plan'] ?? null) === 'pro'
                    && array_key_exists('face_subscription_id', $context)
                    && array_key_exists('face_id', $context)
                    && array_key_exists('error_class', $context)
                    && array_key_exists('error_message', $context);
            });

        // Inject a forced failure inside the lockForUpdate + update transaction.
        // Save/restore the model event dispatcher rather than flushEventListeners()
        // so production observers registered at boot are not silently disabled
        // for subsequent tests in the same process.
        $originalDispatcher = FaceSubscription::getEventDispatcher();
        try {
            \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher);
            FaceSubscription::updating(function (): void {
                throw new \RuntimeException('Forced test failure');
            });

            $this->artisan('subscriptions:remind-face-renewals')
                ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 0, Failed: 1.')
                ->assertExitCode(0);
        } finally {
            \Illuminate\Database\Eloquent\Model::setEventDispatcher($originalDispatcher);
        }
    }

    public function test_command_emits_log_error_with_plan_field_on_failed_7_day_reminder(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Log Error 7d Pro']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'logerror7@example.test',
        ]);
        FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(6)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Face subscription 7d reminder failed'
                    && ($context['plan'] ?? null) === 'pro'
                    && array_key_exists('face_subscription_id', $context)
                    && array_key_exists('face_id', $context)
                    && array_key_exists('error_class', $context)
                    && array_key_exists('error_message', $context);
            });

        // Save/restore the model event dispatcher (see same pattern in the 30d
        // failed-reminder test above — avoids silently disabling production
        // observers via flushEventListeners()).
        $originalDispatcher = FaceSubscription::getEventDispatcher();
        try {
            \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher);
            FaceSubscription::updating(function (): void {
                throw new \RuntimeException('Forced 7d test failure');
            });

            $this->artisan('subscriptions:remind-face-renewals')
                ->expectsOutputToContain('Done. Sent30d: 0, Sent7d: 0, Failed: 1.')
                ->assertExitCode(0);
        } finally {
            \Illuminate\Database\Eloquent\Model::setEventDispatcher($originalDispatcher);
        }
    }

    public function test_command_emits_log_warning_with_plan_field_when_reminder_mail_queue_fails(): void
    {
        $face = Face::factory()->create(['prenom' => 'Mail Warning Pro']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'mailwarning@example.test',
        ]);
        FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(29)->addHours(12),
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
        ]);

        // Force the throw on ->queue(...) so the actual catch site in sendReminder()
        // is what triggers the warning log (rather than ->to() short-circuiting).
        $pendingMailMock = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pendingMailMock->shouldReceive('queue')
            ->once()
            ->andThrow(new \RuntimeException('Queue failure'));

        Mail::shouldReceive('to')
            ->once()
            ->with('mailwarning@example.test')
            ->andReturn($pendingMailMock);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'FaceSubscriptionRenewalReminderMail queue failed'
                    && ($context['plan'] ?? null) === 'pro'
                    && ($context['days_remaining'] ?? null) === 30
                    && array_key_exists('face_subscription_id', $context)
                    && array_key_exists('face_id', $context)
                    && ($context['error'] ?? null) === 'Queue failure';
            });
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Face subscription 30-day reminder dispatched by scheduled command'
                && ($context['plan'] ?? null) === 'pro');

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('Done. Sent30d: 1, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);
    }

    public function test_command_multi_tier_batch_in_single_run_emits_one_log_info_per_tier(): void
    {
        Mail::fake();

        foreach ([
            ['plan' => 'starter', 'state' => 'starter'],
            ['plan' => 'pro', 'state' => 'pro'],
            ['plan' => 'elite', 'state' => 'elite'],
        ] as $tier) {
            $face = Face::factory()->create(['prenom' => "Multi {$tier['plan']}"]);
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "multi-{$tier['plan']}@example.test",
            ]);
            FaceSubscription::factory()->active()->{$tier['state']}()->create([
                'face_id' => $face->id,
                'expires_at' => now()->addDays(29)->addHours(12),
                'reminder_30d_sent_at' => null,
                'reminder_7d_sent_at' => null,
            ]);
        }

        $observedPlans = [];
        Log::shouldReceive('info')
            ->times(3)
            ->withArgs(function (string $message, array $context) use (&$observedPlans): bool {
                if ($message !== 'Face subscription 30-day reminder dispatched by scheduled command') {
                    return false;
                }
                $observedPlans[] = $context['plan'] ?? null;

                return true;
            });

        $this->artisan('subscriptions:remind-face-renewals')
            ->expectsOutputToContain('plan: starter')
            ->expectsOutputToContain('plan: pro')
            ->expectsOutputToContain('plan: elite')
            ->expectsOutputToContain('Done. Sent30d: 3, Sent7d: 0, Failed: 0.')
            ->assertExitCode(0);

        Mail::assertQueuedCount(3);
        sort($observedPlans);
        $this->assertSame(['elite', 'pro', 'starter'], $observedPlans);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Notifications\FaceExclusivitySlotFreedNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReleaseLostFaceExclusivitiesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function exclusiveFace(array $attributes = []): Face
    {
        return Face::factory()->create(['is_exclusive' => true] + $attributes);
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function alertRecipients(array $emails = ['ops@weact.test', 'tech@weact.test']): void
    {
        config(['alerts.critical.recipients' => $emails]);
    }

    private function mailText(FaceExclusivitySlotFreedNotification $notification, AnonymousNotifiable $notifiable): string
    {
        return implode(' ', $notification->toMail($notifiable)->introLines);
    }

    public function test_exclusivity_is_removed_when_the_elite_subscription_expired(): void
    {
        Notification::fake();
        $this->alertRecipients(['ops@weact.test']);
        $face = $this->exclusiveFace(['prenom' => 'Aïcha', 'nom' => 'Koné']);
        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $face->id]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        $this->assertFalse($face->fresh()->is_exclusive);
        Notification::assertSentOnDemand(
            FaceExclusivitySlotFreedNotification::class,
            function (FaceExclusivitySlotFreedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                $mail = $notification->toMail($notifiable);
                $text = $this->mailText($notification, $notifiable);

                return $notifiable->routes['mail'] === 'ops@weact.test'
                    && $mail->subject === 'Une place d\'exclusivité Élite est libre'
                    && str_contains($text, 'Aïcha Koné')
                    && str_contains($text, 'Découverte');
            }
        );
    }

    public function test_exclusivity_is_removed_when_the_elite_subscription_was_cancelled(): void
    {
        Notification::fake();
        $this->alertRecipients();
        $face = $this->exclusiveFace();
        FaceSubscription::factory()->elite()->cancelled()->create(['face_id' => $face->id]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        $this->assertFalse($face->fresh()->is_exclusive);
    }

    public function test_exclusivity_is_removed_and_the_mail_names_the_current_tier_after_a_move_to_pro(): void
    {
        Notification::fake();
        $this->alertRecipients(['ops@weact.test']);
        $face = $this->exclusiveFace(['prenom' => 'Awa', 'nom' => 'Diallo']);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        $this->assertFalse($face->fresh()->is_exclusive);
        Notification::assertSentOnDemand(
            FaceExclusivitySlotFreedNotification::class,
            fn (FaceExclusivitySlotFreedNotification $n, array $c, AnonymousNotifiable $notifiable): bool => str_contains($this->mailText($n, $notifiable), 'Pro')
        );
    }

    public function test_the_mail_falls_back_to_the_username_when_the_face_has_no_name(): void
    {
        Notification::fake();
        $this->alertRecipients(['ops@weact.test']);
        $this->exclusiveFace(['prenom' => '', 'nom' => '', 'username' => 'aicha_k']);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        Notification::assertSentOnDemand(
            FaceExclusivitySlotFreedNotification::class,
            fn (FaceExclusivitySlotFreedNotification $n, array $c, AnonymousNotifiable $notifiable): bool => str_contains($this->mailText($n, $notifiable), 'aicha_k')
        );
    }

    public function test_faces_still_elite_keep_their_exclusivity_and_trigger_no_mail(): void
    {
        Notification::fake();
        $this->alertRecipients();
        $face = $this->exclusiveFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        $this->assertTrue($face->fresh()->is_exclusive);
        Notification::assertNothingSent();
    }

    public function test_one_mail_per_freed_slot_to_every_recipient(): void
    {
        Notification::fake();
        $this->alertRecipients(['ops@weact.test', 'tech@weact.test']);
        $this->exclusiveFace();
        $this->exclusiveFace();
        $kept = $this->exclusiveFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $kept->id]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        Notification::assertSentOnDemandTimes(FaceExclusivitySlotFreedNotification::class, 4);
        $this->assertSame(1, Face::query()->where('is_exclusive', true)->count());
    }

    public function test_nothing_is_sent_when_there_is_nothing_to_remove(): void
    {
        Notification::fake();
        $this->alertRecipients();
        Face::factory()->create(['is_exclusive' => false]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_exclusivity_is_removed_without_mail_when_no_alert_address_is_configured(): void
    {
        Notification::fake();
        $this->alertRecipients([]);
        $face = $this->exclusiveFace();

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        $this->assertFalse($face->fresh()->is_exclusive);
        Notification::assertNothingSent();
    }

    public function test_an_admin_unchecking_the_flag_himself_triggers_no_mail(): void
    {
        Notification::fake();
        $this->alertRecipients();
        $face = $this->exclusiveFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);
        $face->update(['is_exclusive' => false]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        $this->assertFalse($face->fresh()->is_exclusive);
        Notification::assertNothingSent();
    }

    public function test_running_twice_removes_nothing_more_and_sends_no_duplicate(): void
    {
        Notification::fake();
        $this->alertRecipients(['ops@weact.test']);
        $this->exclusiveFace();

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);
        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        Notification::assertSentOnDemandTimes(FaceExclusivitySlotFreedNotification::class, 1);
    }

    public function test_a_face_that_resubscribes_to_elite_does_not_get_the_exclusivity_back(): void
    {
        Notification::fake();
        $this->alertRecipients();
        $face = $this->exclusiveFace();
        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $face->id]);

        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);
        $this->artisan('exclusivity:release-lost')->assertExitCode(0);

        $this->assertFalse($face->fresh()->is_exclusive);
    }

    public function test_it_is_scheduled_hourly_after_the_subscription_expiry_tasks(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => (string) $event->command)
            ->values();

        $expire = $commands->search(fn (string $c): bool => str_contains($c, 'subscriptions:expire-faces'));
        $pending = $commands->search(fn (string $c): bool => str_contains($c, 'subscriptions:fail-stale-pending'));
        $releaseIndex = $commands->search(fn (string $c): bool => str_contains($c, 'exclusivity:release-lost'));

        $this->assertNotFalse($expire);
        $this->assertNotFalse($releaseIndex);
        $this->assertGreaterThan($expire, $releaseIndex);

        $event = app(Schedule::class)->events()[$releaseIndex];
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
        $this->assertNotFalse($pending);
        $this->assertGreaterThan($pending, $releaseIndex);
    }
}

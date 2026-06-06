<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleWiringTest extends TestCase
{
    public function test_auto_validate_mission_attendance_is_scheduled_hourly(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'missions:auto-validate-attendance'),
        );

        $this->assertNotNull($event, 'AutoValidateMissionAttendanceCommand is not scheduled.');
        $this->assertSame('0 * * * *', $event->expression, 'Schedule must be hourly.');
    }

    public function test_settle_disputed_mission_attendance_is_scheduled_hourly(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'missions:settle-disputed-attendance'),
        );

        $this->assertNotNull($event, 'SettleDisputedMissionAttendanceCommand is not scheduled.');
        $this->assertSame('0 * * * *', $event->expression, 'Schedule must be hourly.');
    }

    public function test_expire_face_subscriptions_is_scheduled_hourly(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'subscriptions:expire-faces'),
        );

        $this->assertNotNull($event, 'ExpireFaceSubscriptionsCommand is not scheduled.');
        $this->assertSame('0 * * * *', $event->expression, 'Schedule must be hourly.');
    }

    public function test_remind_face_subscription_renewals_is_scheduled_hourly(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'subscriptions:remind-face-renewals'),
        );

        $this->assertNotNull($event, 'RemindFaceSubscriptionRenewalsCommand is not scheduled.');
        $this->assertSame('0 * * * *', $event->expression, 'Schedule must be hourly.');
    }

    public function test_fail_stale_pending_face_subscriptions_is_scheduled_hourly(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'subscriptions:fail-stale-pending'),
        );

        $this->assertNotNull($event, 'FailStalePendingFaceSubscriptionsCommand is not scheduled.');
        $this->assertSame('0 * * * *', $event->expression, 'Schedule must be hourly.');
    }

    public function test_purge_expired_media_is_scheduled_daily_at_03_utc(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'faces:purge-expired-media'),
        );

        $this->assertNotNull($event, 'PurgeExpiredMediaCommand is not scheduled.');
        $this->assertSame('0 3 * * *', $event->expression, 'Schedule must be daily at 03:00 UTC.');
        $this->assertSame('UTC', $event->timezone, 'Schedule timezone must be UTC (destructive cron contract).');
        $this->assertTrue($event->withoutOverlapping, 'Schedule must have withoutOverlapping() — destructive cron concurrency guard.');
        $this->assertTrue($event->onOneServer, 'Schedule must have onOneServer() — destructive cron single-server guard.');
    }

    public function test_prune_expired_sanctum_tokens_is_scheduled_daily(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'sanctum:prune-expired'),
        );

        $this->assertNotNull($event, 'sanctum:prune-expired is not scheduled.');
        $this->assertSame('30 3 * * *', $event->expression, 'Schedule must be daily at 03:30.');
        $this->assertSame('UTC', $event->timezone, 'Schedule timezone must be UTC.');
        $this->assertTrue($event->withoutOverlapping, 'Schedule must have withoutOverlapping().');
        $this->assertTrue($event->onOneServer, 'Schedule must have onOneServer().');
    }
}

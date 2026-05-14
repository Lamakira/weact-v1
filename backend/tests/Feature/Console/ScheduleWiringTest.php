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
}

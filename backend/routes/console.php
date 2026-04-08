<?php

use App\Console\Commands\AutoCompleteBookingsCommand;
use App\Console\Commands\AutoReleaseMissionFundsCommand;
use App\Console\Commands\ExpireUnacceptedBookingsCommand;
use App\Console\Commands\ExpireUnpaidBookingsCommand;
use App\Console\Commands\ReconcileWalletCommand;
use App\Console\Commands\RemindBookingPaymentCommand;
use App\Console\Commands\RemindShootingDayCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register scheduled commands
app(Schedule::class)->command(AutoCompleteBookingsCommand::class)->hourly();
app(Schedule::class)->command(ExpireUnacceptedBookingsCommand::class)->hourly();
app(Schedule::class)->command(ExpireUnpaidBookingsCommand::class)->hourly();
app(Schedule::class)->command(RemindBookingPaymentCommand::class)->hourly();
app(Schedule::class)->command(ReconcileWalletCommand::class)->daily();
app(Schedule::class)->command(AutoReleaseMissionFundsCommand::class)->daily();
app(Schedule::class)->command(RemindShootingDayCommand::class)->dailyAt('08:00');

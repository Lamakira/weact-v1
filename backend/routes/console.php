<?php

use App\Console\Commands\AutoCompleteBookingsCommand;
use App\Console\Commands\ExpireUnpaidBookingsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register scheduled commands
app(Schedule::class)->command(AutoCompleteBookingsCommand::class)->hourly();
app(Schedule::class)->command(ExpireUnpaidBookingsCommand::class)->hourly();

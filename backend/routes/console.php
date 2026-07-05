<?php

use App\Console\Commands\AutoCompleteBookingsCommand;
use App\Console\Commands\AutoReleaseMissionFundsCommand;
use App\Console\Commands\AutoValidateMissionAttendanceCommand;
use App\Console\Commands\CheckFaceListingRanksFreshnessCommand;
use App\Console\Commands\ExpireFaceSubscriptionsCommand;
use App\Console\Commands\ExpireUnacceptedBookingsCommand;
use App\Console\Commands\ExpireUnacceptedUgcDealsCommand;
use App\Console\Commands\ExpireUnpaidBookingsCommand;
use App\Console\Commands\ExpireUnreconfirmedUgcCandidaturesCommand;
use App\Console\Commands\FailStalePendingFaceSubscriptionsCommand;
use App\Console\Commands\ProcessUgcDeadlinesCommand;
use App\Console\Commands\PurgeExpiredMediaCommand;
use App\Console\Commands\RebuildFaceListingRanksCommand;
use App\Console\Commands\ReconcileWalletCommand;
use App\Console\Commands\RemindBookingPaymentCommand;
use App\Console\Commands\RemindFaceSubscriptionRenewalsCommand;
use App\Console\Commands\RemindShootingDayCommand;
use App\Console\Commands\SettleDisputedMissionAttendanceCommand;
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
app(Schedule::class)->command(ExpireUnacceptedUgcDealsCommand::class)->hourly();
// ugc-9-1 (D-9.1.g) — dénoue les candidatures UGC acceptées jamais reconfirmées après
// 48h (refund escrow hybride au Producteur, libère le slot, réouvre la mission).
app(Schedule::class)->command(ExpireUnreconfirmedUgcCandidaturesCommand::class)->hourly();
// 4.5 — relance d'escalade des deadlines d'upload Face (~15 min, NFR3/AR3).
// Idempotente par shipments.last_notified_threshold ; withoutOverlapping en
// défense supplémentaire (D-4.5.d).
app(Schedule::class)->command(ProcessUgcDeadlinesCommand::class)->everyFifteenMinutes()->withoutOverlapping();
app(Schedule::class)->command(RemindBookingPaymentCommand::class)->hourly();
app(Schedule::class)->command(ReconcileWalletCommand::class)->daily();
app(Schedule::class)->command(AutoReleaseMissionFundsCommand::class)->daily();
app(Schedule::class)->command(AutoValidateMissionAttendanceCommand::class)->hourly();
app(Schedule::class)->command(SettleDisputedMissionAttendanceCommand::class)->hourly();
app(Schedule::class)->command(RemindShootingDayCommand::class)->dailyAt('08:00');
app(Schedule::class)->command(ExpireFaceSubscriptionsCommand::class)->hourly();
app(Schedule::class)->command(FailStalePendingFaceSubscriptionsCommand::class)->hourly();
app(Schedule::class)->command(RemindFaceSubscriptionRenewalsCommand::class)->hourly();
app(Schedule::class)->command(PurgeExpiredMediaCommand::class)
    ->dailyAt('03:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

// Rotation du listing public : reconstruit chaque nuit le classement matérialisé
// (quotas de palier via WRR lissé + équité LRU page 1) en une génération
// atomique. Passe APRÈS les crons horaires d'expiration d'abonnements pour
// classer chaque Face sur son palier à jour.
app(Schedule::class)->command(RebuildFaceListingRanksCommand::class)
    ->dailyAt('03:15')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

// Watchdog du classement : le mode dégradé (génération précédente servie) est
// invisible pour les visiteurs, donc un rebuild qui ne tourne plus doit être
// détecté de l'extérieur — Log::critical répété chaque heure tant que le
// classement date de plus de 48 h (= 2 nuits ratées).
app(Schedule::class)->command(CheckFaceListingRanksFreshnessCommand::class)->hourly();

// Sanctum token hygiene: drop tokens expired (created_at + sanctum.expiration) for
// more than 24h, so abandoned/non-expiring sessions don't accumulate forever.
app(Schedule::class)->command('sanctum:prune-expired', ['--hours' => 24])
    ->dailyAt('03:30')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

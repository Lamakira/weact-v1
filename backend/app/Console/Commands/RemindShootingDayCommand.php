<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemindShootingDayCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'missions:remind-shooting';

    /**
     * The console command description.
     */
    protected $description = 'Send shooting day reminders (J-1) to producers and selected faces for active missions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tomorrow = now()->addDay()->toDateString();

        $missions = Mission::whereIn('status', [
            MissionStatus::Published->value,
            MissionStatus::Closed->value,
        ])
            ->whereDate('date_tournage', $tomorrow)
            ->whereNull('shooting_reminder_sent_at')
            ->with([
                'producer',
                'candidatures' => fn ($q) => $q->whereIn('status', [
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                ])->with('face'),
            ])
            ->get();

        $this->info("Found {$missions->count()} mission(s) with shooting tomorrow.");

        $sent = 0;
        $failed = 0;

        foreach ($missions as $mission) {
            try {
                $this->sendRemindersForMission($mission);
                $mission->update(['shooting_reminder_sent_at' => now()]);
                $this->info("Reminders sent for mission #{$mission->id} \"{$mission->titre}\"");
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('ShootingDay reminder failed', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed for mission #{$mission->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return self::SUCCESS;
    }

    private function sendRemindersForMission(Mission $mission): void
    {
        $producerUserId = User::where('userable_type', Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->value('id');

        // Notify Producer
        if ($producerUserId) {
            Notification::create([
                'user_id' => $producerUserId,
                'type' => 'shooting_day_reminder',
                'data' => [
                    'message' => "Rappel : le tournage de votre mission \"{$mission->titre}\" a lieu demain.",
                    'mission_id' => $mission->id,
                    'url' => "/producer/missions/{$mission->uuid}/edit",
                ],
            ]);
        }

        // Notify selected Faces
        foreach ($mission->candidatures as $candidature) {
            /** @var Candidature $candidature */
            $faceId = $candidature->face_id;
            $faceUserId = $faceId
                ? User::where('userable_type', \App\Models\Face::class)
                    ->where('userable_id', $faceId)
                    ->value('id')
                : null;

            if (! $faceUserId) {
                continue;
            }

            try {
                Notification::create([
                    'user_id' => $faceUserId,
                    'type' => 'shooting_day_reminder',
                    'data' => [
                        'message' => "Rappel : votre tournage pour la mission \"{$mission->titre}\" a lieu demain.",
                        'mission_id' => $mission->id,
                        'candidature_id' => $candidature->id,
                        'url' => "/face/candidatures/{$candidature->uuid}",
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('ShootingDay reminder for Face failed', [
                    'mission_id' => $mission->id,
                    'candidature_id' => $candidature->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

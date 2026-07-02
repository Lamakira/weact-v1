<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemindShootingDayCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_notifies_producer_and_only_confirmed_or_in_progress_faces(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->addDay()->format('Y-m-d'),
            'shooting_reminder_sent_at' => null,
        ]);

        $confirmedFace = Face::factory()->create();
        $confirmedUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $confirmedFace->id,
        ]);

        $acceptedFace = Face::factory()->create();
        $acceptedUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $acceptedFace->id,
        ]);

        $confirmedCandidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $confirmedFace->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $acceptedCandidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $acceptedFace->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        $this->artisan('missions:remind-shooting')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'shooting_day_reminder',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $confirmedUser->id,
            'type' => 'shooting_day_reminder',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $acceptedUser->id,
            'type' => 'shooting_day_reminder',
        ]);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
        ]);

        $mission->refresh();
        $this->assertNotNull($mission->shooting_reminder_sent_at);

        $confirmedNotification = Notification::where('user_id', $confirmedUser->id)->first();
        $this->assertSame($confirmedCandidature->id, $confirmedNotification->data['candidature_id']);

        $this->assertNull(Notification::where('user_id', $acceptedUser->id)->first());
        $this->assertSame(CandidatureStatus::Accepted, $acceptedCandidature->fresh()->status);
    }

    public function test_command_ignores_ugc_missions(): void
    {
        // UGC 2.4 : pas de tournage en UGC (livrables à distance) — le rappel J-1
        // ne doit notifier ni le Producteur ni les Faces engagées d'une mission UGC.
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->addDay()->format('Y-m-d'),
            'shooting_reminder_sent_at' => null,
            'type_mission' => 'ugc',
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'commission_paid_at' => now(),
        ]);

        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->artisan('missions:remind-shooting')
            ->expectsOutput('Found 0 mission(s) with shooting tomorrow.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', ['user_id' => $producerUser->id, 'type' => 'shooting_day_reminder']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $faceUser->id, 'type' => 'shooting_day_reminder']);
        $this->assertNull($mission->fresh()->shooting_reminder_sent_at);
    }
}

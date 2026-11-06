<?php

declare(strict_types=1);

namespace Tests\Feature\DateChange;

use App\Enums\CandidatureStatus;
use App\Enums\MissionGender;
use App\Enums\MissionType;
use App\Mail\DateChangeMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Mission publiée (avant paiement) : la modification de la date de tournage prévient les candidatures
 * en attente ou acceptées, sans réponse attendue, et la clôture passée peut rester inchangée.
 */
class PublishedMissionDateChangeTest extends DateChangeTestCase
{
    private Mission $mission;

    /** @var array<string, array{candidature: Candidature, user: User}> */
    private array $candidates = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Clôture des candidatures déjà passée (05/11), tournage le 20/11.
        $this->mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Spot test',
            'description' => 'Description',
            'date_tournage' => '2026-11-20',
            'profil_recherche' => 'Profil',
            'budget' => 100000,
            'date_limite_candidature' => '2026-11-05',
            'nombre_faces_voulu' => 2,
            'type_mission' => MissionType::Publicite,
            'genre_voulu' => MissionGender::Tous,
            'lieu' => 'Cotonou, Bénin',
            'duree' => '1 journée',
        ]);

        foreach (['pending' => CandidatureStatus::Pending, 'accepted' => CandidatureStatus::Accepted, 'rejected' => CandidatureStatus::Rejected, 'cancelled' => CandidatureStatus::Cancelled] as $key => $status) {
            $face = Face::factory()->create();
            $user = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
            $this->candidates[$key] = [
                'candidature' => Candidature::factory()->create(['mission_id' => $this->mission->id, 'face_id' => $face->id, 'status' => $status]),
                'user' => $user,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->producerUser)->putJson("/api/v1/producer/missions/{$this->mission->uuid}", array_merge([
            'titre' => 'Spot test',
            'description' => 'Description',
            'date_tournage' => '2026-11-20',
            'profil_recherche' => 'Profil',
            'budget' => 100000,
            'date_limite_candidature' => '2026-11-05',
            'nombre_faces_voulu' => 2,
            'type_mission' => 'publicite',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou, Bénin',
            'duree' => '1 journée',
        ], $overrides));
    }

    public function test_changing_the_shooting_date_notifies_pending_and_accepted_candidatures_only(): void
    {
        $this->update(['date_tournage' => '2026-11-25'])->assertOk();

        $this->assertSame('2026-11-25', $this->mission->fresh()->date_tournage->toDateString());

        foreach (['pending', 'accepted'] as $key) {
            $notification = Notification::where('user_id', $this->candidates[$key]['user']->id)->sole();
            $this->assertSame('mission_date_changed', $notification->type);
            $this->assertSame('Le tournage de « Spot test » passe au mercredi 25 novembre.', $notification->data['message']);
            $this->assertSame("/face/missions/{$this->mission->uuid}", $notification->data['url']);
            Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($this->candidates[$key]['user']->email)
                && $mail->subjectText === 'La date de tournage a changé : mercredi 25 novembre');
        }

        foreach (['rejected', 'cancelled'] as $key) {
            $this->assertSame(0, Notification::where('user_id', $this->candidates[$key]['user']->id)->count());
        }

        Mail::assertQueuedCount(2);
        // Aucune ligne de réponse ni quota : la modification publiée n'ouvre pas de demande.
        $this->assertDatabaseCount('date_change_requests', 0);
        $this->assertDatabaseCount('date_change_responses', 0);
    }

    public function test_no_notification_nor_mail_when_the_date_does_not_change(): void
    {
        $this->update(['titre' => 'Nouveau titre', 'budget' => 120000])->assertOk();

        $this->assertSame(0, Notification::count());
        Mail::assertNothingQueued();
    }

    public function test_an_unchanged_past_closing_date_is_accepted(): void
    {
        // La clôture (05/11) est passée mais inchangée : on peut déplacer le tournage.
        $this->update(['date_tournage' => '2026-11-27'])->assertOk()->assertJsonPath('data.titre', 'Spot test');

        $this->assertSame('2026-11-05', $this->mission->fresh()->date_limite_candidature->toDateString());
    }

    public function test_a_modified_past_closing_date_is_refused(): void
    {
        $this->update(['date_limite_candidature' => '2026-11-06', 'date_tournage' => '2026-11-27'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_limite_candidature');

        $this->assertSame('2026-11-20', $this->mission->fresh()->date_tournage->toDateString());
        $this->assertSame(0, Notification::count());
    }

    public function test_a_modified_future_closing_date_is_accepted(): void
    {
        $this->update(['date_limite_candidature' => '2026-11-15', 'date_tournage' => '2026-11-27'])->assertOk();

        $this->assertSame('2026-11-15', $this->mission->fresh()->date_limite_candidature->toDateString());
    }

    public function test_a_shooting_date_before_the_closing_date_is_refused(): void
    {
        $this->update(['date_tournage' => '2026-11-04'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_tournage');

        $this->assertSame('2026-11-20', $this->mission->fresh()->date_tournage->toDateString());
        $this->assertSame(0, Notification::count());
    }

    public function test_a_past_shooting_date_is_refused_even_with_an_unchanged_closing_date(): void
    {
        $this->update(['date_tournage' => '2026-11-09'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_tournage');

        $this->assertSame('2026-11-20', $this->mission->fresh()->date_tournage->toDateString());
        $this->assertSame(0, Notification::count());
    }

    public function test_the_published_edit_is_not_capped_at_30_days(): void
    {
        $this->update(['date_tournage' => '2027-03-01'])->assertOk();
    }
}

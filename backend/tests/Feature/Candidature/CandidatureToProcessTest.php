<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lot D7 : filtre « À traiter » de « Mes candidatures » (Candidature::toProcess), exposé par
 * GET /api/v1/face/candidatures?status=to_process.
 * Instant figé : mardi 20/10/2026 10:00 UTC (11:00 heure métier, UTC+1).
 */
class CandidatureToProcessTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Face $otherFace;

    private User $otherFaceUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $this->face->id]);
        $this->otherFace = Face::factory()->create();
        $this->otherFaceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $this->otherFace->id]);
        $this->producer = Producer::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $missionAttrs
     */
    private function candidature(
        CandidatureStatus $status,
        ?Carbon $acceptedAt,
        array $missionAttrs = [],
        ?Face $face = null,
    ): Candidature {
        $mission = Mission::factory()->create(array_merge([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Closed,
            'type_mission' => MissionType::Publicite,
            'date_tournage' => '2026-11-15',
        ], $missionAttrs));

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => ($face ?? $this->face)->id,
            'status' => $status,
            'accepted_at' => $acceptedAt,
        ]);
    }

    private function inProcess(Candidature $candidature, ?Face $face = null): bool
    {
        return Candidature::query()
            ->where('face_id', ($face ?? $this->face)->id)
            ->toProcess()
            ->whereKey($candidature->id)
            ->exists();
    }

    public function test_cash_participation_to_confirm_within_48_hours(): void
    {
        $recent = $this->candidature(CandidatureStatus::Accepted, now()->subHours(10));
        $almostElapsed = $this->candidature(CandidatureStatus::Accepted, now()->subHours(47));
        $elapsed = $this->candidature(CandidatureStatus::Accepted, now()->subHours(49));

        $this->assertTrue($this->inProcess($recent));
        $this->assertTrue($this->inProcess($almostElapsed));
        $this->assertFalse($this->inProcess($elapsed));
    }

    public function test_cash_participation_deadline_is_capped_by_shooting_day_with_a_12_hour_floor(): void
    {
        // Tournage demain 00:00 heure métier (= 20/10 23:00 UTC) : échéance = max(min(accepté + 48 h, tournage), accepté + 12 h).
        // Accepté il y a 20 h : l'échéance est le début du tournage (23:00 UTC), pas encore atteint.
        $acceptedLongAgo = $this->candidature(CandidatureStatus::Accepted, now()->subHours(20), ['date_tournage' => '2026-10-21']);
        // Sélection payée il y a 2 h pour un tournage demain : le plancher de 12 h garde la fenêtre ouverte.
        $acceptedRecently = $this->candidature(CandidatureStatus::Accepted, now()->subHours(2), ['date_tournage' => '2026-10-21']);
        // Tournage aujourd'hui (déjà commencé), accepté il y a 13 h : plancher dépassé, fenêtre fermée.
        $shootStartedFloorElapsed = $this->candidature(CandidatureStatus::Accepted, now()->subHours(13), ['date_tournage' => '2026-10-20']);
        $shootStartedWithinFloor = $this->candidature(CandidatureStatus::Accepted, now()->subHours(11), ['date_tournage' => '2026-10-20']);

        $this->assertTrue($this->inProcess($acceptedLongAgo));
        $this->assertTrue($this->inProcess($acceptedRecently));
        $this->assertFalse($this->inProcess($shootStartedFloorElapsed));
        $this->assertTrue($this->inProcess($shootStartedWithinFloor));
    }

    public function test_ugc_reconfirmation_within_reconfirm_window(): void
    {
        $ugc = ['type_mission' => MissionType::Ugc, 'date_tournage' => null];

        $open = $this->candidature(CandidatureStatus::Accepted, now()->subHours(30), $ugc);
        $elapsed = $this->candidature(CandidatureStatus::Accepted, now()->subHours(49), $ugc);

        $this->assertTrue($this->inProcess($open));
        $this->assertFalse($this->inProcess($elapsed));
    }

    public function test_other_statuses_are_never_to_process(): void
    {
        foreach ([
            CandidatureStatus::Pending,
            CandidatureStatus::Confirmed,
            CandidatureStatus::InProgress,
            CandidatureStatus::Completed,
            CandidatureStatus::Rejected,
            CandidatureStatus::Cancelled,
            CandidatureStatus::Expired,
        ] as $status) {
            $candidature = $this->candidature($status, now()->subHours(2));

            $this->assertFalse($this->inProcess($candidature), "Statut {$status->value} ne doit pas être à traiter.");
        }
    }

    public function test_accepted_candidature_without_accepted_at_is_ignored(): void
    {
        $this->assertFalse($this->inProcess($this->candidature(CandidatureStatus::Accepted, null)));
    }

    public function test_api_status_filter_to_process_is_isolated_between_faces(): void
    {
        $mine = $this->candidature(CandidatureStatus::Accepted, now()->subHours(3));
        $this->candidature(CandidatureStatus::Pending, null);
        $theirs = $this->candidature(CandidatureStatus::Accepted, now()->subHours(3), face: $this->otherFace);

        $uuids = fn (User $viewer): array => collect(
            $this->actingAs($viewer)->getJson('/api/v1/face/candidatures?status=to_process')->assertOk()->json('data')
        )->pluck('id')->all();

        $this->assertSame([$mine->uuid], $uuids($this->faceUser));
        $this->assertSame([$theirs->uuid], $uuids($this->otherFaceUser));
    }
}

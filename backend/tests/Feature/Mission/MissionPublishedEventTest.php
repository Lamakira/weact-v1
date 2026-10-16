<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Events\MissionPublished;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\MissionService;
use App\Services\Ugc\UgcCommissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MissionPublishedEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([MissionPublished::class]);
    }

    public function test_first_publication_sets_published_at_and_dispatches_once(): void
    {
        $mission = Mission::factory()->create(['status' => MissionStatus::Draft]);
        $this->assertNull($mission->published_at);
        Event::assertNotDispatched(MissionPublished::class);

        $mission->update(['status' => MissionStatus::Published]);

        $this->assertNotNull($mission->fresh()->published_at);
        Event::assertDispatchedTimes(MissionPublished::class, 1);

        // Plain re-save of an already published mission: nothing more.
        $mission->update(['titre' => 'Nouveau titre']);
        Event::assertDispatchedTimes(MissionPublished::class, 1);
    }

    public function test_creation_directly_as_published_dispatches_once(): void
    {
        $mission = Mission::factory()->published()->create();

        $this->assertNotNull($mission->fresh()->published_at);
        Event::assertDispatchedTimes(MissionPublished::class, 1);
    }

    public function test_republication_after_closing_does_not_dispatch_again(): void
    {
        $mission = Mission::factory()->published()->create();
        Event::assertDispatchedTimes(MissionPublished::class, 1);
        $firstPublishedAt = $mission->fresh()->published_at;

        $mission->update(['status' => MissionStatus::Closed]);
        $mission->update(['status' => MissionStatus::Published]);

        Event::assertDispatchedTimes(MissionPublished::class, 1);
        $this->assertEquals($firstPublishedAt, $mission->fresh()->published_at);
    }

    public function test_backfilled_mission_without_published_at_is_not_a_first_publication_candidate_after_migration(): void
    {
        // Mirrors the migration backfill: a legacy published mission gets published_at = updated_at.
        $mission = Mission::factory()->published()->create();
        DB::table('missions')->where('id', $mission->id)->update(['published_at' => null]);

        DB::table('missions')
            ->whereIn('status', ['published', 'closed', 'pending_attendance_validation', 'completed'])
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(updated_at, created_at)')]);

        $legacy = $mission->fresh();
        $this->assertNotNull($legacy->published_at);

        $legacy->update(['status' => MissionStatus::Closed]);
        $legacy->update(['status' => MissionStatus::Published]);

        Event::assertDispatchedTimes(MissionPublished::class, 1); // only the factory creation above
    }

    public function test_standard_mission_creation_through_service_dispatches(): void
    {
        $producer = Producer::factory()->create();

        app(MissionService::class)->createMission($producer, [
            'titre' => 'Pub lessive',
            'description' => 'Description',
            'date_tournage' => now()->addDays(20)->toDateString(),
            'profil_recherche' => 'Profil',
            'budget' => 100000,
            'date_limite_candidature' => now()->addDays(10)->toDateString(),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Publicite->value,
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => '1 jour',
        ]);

        Event::assertDispatchedTimes(MissionPublished::class, 1);
    }

    public function test_hybrid_ugc_mission_creation_through_service_dispatches_but_product_only_waits_for_payment(): void
    {
        $producer = Producer::factory()->create();
        $base = [
            'titre' => 'Dotation',
            'description' => 'Description',
            'profil_recherche' => 'Profil',
            'date_limite_candidature' => now()->addDays(10)->toDateString(),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'nom_produit' => 'Crème',
            'valeur_produit' => 20000,
        ];

        app(MissionService::class)->createMission($producer, $base + ['type_compensation' => 'product']);
        Event::assertNotDispatched(MissionPublished::class);

        app(MissionService::class)->createMission($producer, $base + [
            'type_compensation' => 'hybrid',
            'nombre_videos' => 2,
            'montant_remuneration' => 10000,
        ]);
        Event::assertDispatchedTimes(MissionPublished::class, 1);
    }

    public function test_ugc_commission_payment_publishes_and_dispatches(): void
    {
        $mission = Mission::factory()->create([
            'type_mission' => MissionType::Ugc,
            'type_compensation' => 'product',
            'status' => MissionStatus::PendingPayment,
        ]);
        Event::assertNotDispatched(MissionPublished::class);

        app(UgcCommissionPaymentService::class)->markMissionCommissionPaid($mission, 'ref-123');

        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        Event::assertDispatchedTimes(MissionPublished::class, 1);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lot C3 : `display_status` / `display_status_label` disent la vérité sur le cycle de vie
 * sans changer le statut stocké.
 */
class MissionDisplayStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /** @param array<string, mixed> $attrs */
    private function mission(MissionStatus $status, bool $ugc = false, bool $paid = false, array $attrs = []): Mission
    {
        $mission = Mission::factory()->create(array_merge([
            'producer_id' => $this->producer->id,
            'status' => $status,
        ], $ugc ? ['type_mission' => 'ugc', 'commission_ugc' => 5000] : [], $attrs));

        if ($paid) {
            MissionPayment::create([
                'mission_id' => $mission->id,
                'producer_id' => $this->producer->id,
                'nombre_faces_retenues' => 1,
                'budget_par_face' => 100000,
                'montant_sous_total' => 100000,
                'commission_producteur' => 10000,
                'montant_total_producteur' => 110000,
                'commission_faces_total' => 10000,
                'montant_total_faces' => 90000,
                'status' => MissionPaymentStatus::Paid,
                'paid_at' => now(),
            ]);
        }

        return $mission;
    }

    /** @return array{0: string, 1: string} */
    private function fromShow(Mission $mission): array
    {
        $data = $this->actingAs($this->producerUser)->getJson("/api/v1/producer/missions/{$mission->uuid}")->assertOk()->json('data');

        return [$data['display_status'], $data['display_status_label']];
    }

    /** @return array{0: string, 1: string} */
    private function fromList(Mission $mission): array
    {
        $row = collect($this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions')->assertOk()->json('data'))
            ->firstWhere('id', $mission->uuid);

        return [$row['display_status'], $row['display_status_label']];
    }

    /** @return array<string, array{0: MissionStatus, 1: bool, 2: bool, 3: string, 4: string}> */
    public static function displayCases(): array
    {
        return [
            'draft' => [MissionStatus::Draft, false, false, 'draft', 'Brouillon'],
            'published' => [MissionStatus::Published, false, false, 'published', 'Publiée'],
            'standard pending payment' => [MissionStatus::PendingPayment, false, false, 'payment_in_progress', 'Paiement en cours'],
            'ugc pending payment' => [MissionStatus::PendingPayment, true, false, 'commission_due', 'Commission à payer'],
            'standard closed paid' => [MissionStatus::Closed, false, true, 'paid_preparing', 'Payée, en préparation'],
            'standard closed manual' => [MissionStatus::Closed, false, false, 'closed', 'Clôturée'],
            'ugc closed' => [MissionStatus::Closed, true, false, 'in_progress', 'En cours'],
            'pending attendance' => [MissionStatus::PendingAttendanceValidation, false, true, 'attendance_pending', 'Présences à valider'],
            'completed' => [MissionStatus::Completed, false, true, 'completed', 'Terminée'],
            'ugc completed' => [MissionStatus::Completed, true, false, 'completed', 'Terminée'],
        ];
    }

    #[DataProvider('displayCases')]
    public function test_label_on_show_and_list_without_changing_stored_status(MissionStatus $status, bool $ugc, bool $paid, string $display, string $label): void
    {
        $mission = $this->mission($status, $ugc, $paid);

        $this->assertSame([$display, $label], $this->fromShow($mission));
        $this->assertSame([$display, $label], $this->fromList($mission));

        $row = $this->actingAs($this->producerUser)->getJson("/api/v1/producer/missions/{$mission->uuid}")->json('data');
        $this->assertSame($status->value, $row['status']);
        $this->assertSame($status->label(), $row['status_label']);
    }

    public function test_list_reuses_the_preloaded_paid_flag_instead_of_querying_per_mission(): void
    {
        $this->mission(MissionStatus::Closed, false, true);
        $this->mission(MissionStatus::Closed);
        $this->mission(MissionStatus::PendingPayment, true);
        $this->mission(MissionStatus::Published);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions')->assertOk();

        $perMissionPaidLookups = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql): bool => str_starts_with($sql, 'select exists(select * from `mission_payments` where `mission_id` = ?'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(0, $perMissionPaidLookups);
    }

    public function test_dashboard_active_missions_expose_the_display_status(): void
    {
        $published = $this->mission(MissionStatus::Published);
        $paid = $this->mission(MissionStatus::Closed, false, true);
        foreach ([$published, $paid] as $mission) {
            \App\Models\Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => \App\Models\Face::factory()->create()->id,
                'status' => \App\Enums\CandidatureStatus::Confirmed,
            ]);
        }

        $rows = collect($this->actingAs($this->producerUser)->getJson('/api/v1/producer/dashboard/active-missions')->assertOk()->json('data'));

        $this->assertSame(['published', 'Publiée'], [$rows->firstWhere('id', $published->uuid)['display_status'], $rows->firstWhere('id', $published->uuid)['display_status_label']]);
        $this->assertSame(['paid_preparing', 'Payée, en préparation'], [$rows->firstWhere('id', $paid->uuid)['display_status'], $rows->firstWhere('id', $paid->uuid)['display_status_label']]);
    }
}

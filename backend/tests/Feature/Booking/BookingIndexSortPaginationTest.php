<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tri serveur (sort/direction/per_page) de GET /api/v1/bookings.
 */
class BookingIndexSortPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function booking(array $attrs = []): Booking
    {
        $createdAt = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);

        $booking = Booking::factory()->create(array_merge([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ], $attrs));

        if ($createdAt !== null) {
            DB::table('bookings')->where('id', $booking->id)->update(['created_at' => $createdAt]);
        }

        return $booking;
    }

    /**
     * @return list<string>
     */
    private function ids(User $viewer, string $query): array
    {
        return collect(
            $this->actingAs($viewer)->getJson('/api/v1/bookings?'.$query)->assertOk()->json('data')
        )->pluck('id')->all();
    }

    public function test_default_order_is_still_updated_at_desc(): void
    {
        $old = $this->booking();
        $new = $this->booking();
        DB::table('bookings')->where('id', $old->id)->update(['updated_at' => now()->subDays(2)]);
        DB::table('bookings')->where('id', $new->id)->update(['updated_at' => now()]);

        $this->assertSame([$new->uuid, $old->uuid], $this->ids($this->producerUser, ''));
    }

    public function test_sort_date_debut_asc_and_desc(): void
    {
        $a = $this->booking(['date_debut' => now()->addDays(5), 'date_fin' => now()->addDays(6)]);
        $b = $this->booking(['date_debut' => now()->addDays(2), 'date_fin' => now()->addDays(3)]);
        $c = $this->booking(['date_debut' => now()->addDays(9), 'date_fin' => now()->addDays(10)]);

        $this->assertSame([$b->uuid, $a->uuid, $c->uuid], $this->ids($this->producerUser, 'sort=date_debut&direction=asc'));
        $this->assertSame([$c->uuid, $a->uuid, $b->uuid], $this->ids($this->producerUser, 'sort=date_debut&direction=desc'));
    }

    public function test_sort_status_follows_the_full_lifecycle_order(): void
    {
        $expected = [];
        foreach (BookingStatus::lifecycleOrder() as $status) {
            $expected[] = $this->booking(['status' => $status])->uuid;
        }

        $this->assertSame($expected, $this->ids($this->producerUser, 'sort=status&direction=asc&per_page=50'));
        $this->assertSame(array_reverse($expected), $this->ids($this->producerUser, 'sort=status&direction=desc&per_page=50'));
    }

    public function test_sort_date_debut_puts_ugc_bookings_without_dates_last(): void
    {
        $dated = $this->booking(['date_debut' => now()->addDays(5), 'date_fin' => now()->addDays(6)]);
        $later = $this->booking(['date_debut' => now()->addDays(9), 'date_fin' => now()->addDays(10)]);
        $ugc = $this->booking();
        DB::table('bookings')->where('id', $ugc->id)->update(['date_debut' => null, 'date_fin' => null]);

        $this->assertSame([$dated->uuid, $later->uuid, $ugc->uuid], $this->ids($this->producerUser, 'sort=date_debut&direction=asc'));
        $this->assertSame([$later->uuid, $dated->uuid, $ugc->uuid], $this->ids($this->producerUser, 'sort=date_debut&direction=desc'));
    }

    public function test_sort_created_at_asc_and_desc(): void
    {
        $a = $this->booking(['created_at' => now()->subDays(3)]);
        $b = $this->booking(['created_at' => now()->subDays(1)]);
        $c = $this->booking(['created_at' => now()->subDays(2)]);

        $this->assertSame([$a->uuid, $c->uuid, $b->uuid], $this->ids($this->producerUser, 'sort=created_at&direction=asc'));
        $this->assertSame([$b->uuid, $c->uuid, $a->uuid], $this->ids($this->producerUser, 'sort=created_at&direction=desc'));
    }

    public function test_sort_status_asc_and_desc(): void
    {
        $pending = $this->booking(['status' => BookingStatus::Pending]);
        $completed = $this->booking(['status' => BookingStatus::Completed]);
        $accepted = $this->booking(['status' => BookingStatus::Accepted]);

        // Ordre de cycle de vie (pending, ..., accepted, ..., completed), pas alphabétique.
        $this->assertSame(
            [$pending->uuid, $accepted->uuid, $completed->uuid],
            $this->ids($this->producerUser, 'sort=status&direction=asc')
        );
        $this->assertSame(
            [$completed->uuid, $accepted->uuid, $pending->uuid],
            $this->ids($this->producerUser, 'sort=status&direction=desc')
        );
    }

    public function test_montant_maps_to_producer_amount_for_a_producer(): void
    {
        // producteur : 100 < 200 < 300 ; face reçoit : ordre différent (100, 10, 50)
        $a = $this->booking(['montant_total_producteur' => 100, 'montant_face_recoit' => 100]);
        $b = $this->booking(['montant_total_producteur' => 300, 'montant_face_recoit' => 10]);
        $c = $this->booking(['montant_total_producteur' => 200, 'montant_face_recoit' => 50]);

        $this->assertSame([$a->uuid, $c->uuid, $b->uuid], $this->ids($this->producerUser, 'sort=montant&direction=asc'));
        $this->assertSame([$b->uuid, $c->uuid, $a->uuid], $this->ids($this->producerUser, 'sort=montant&direction=desc'));
    }

    public function test_montant_maps_to_face_amount_for_a_face(): void
    {
        $a = $this->booking(['montant_total_producteur' => 100, 'montant_face_recoit' => 100]);
        $b = $this->booking(['montant_total_producteur' => 300, 'montant_face_recoit' => 10]);
        $c = $this->booking(['montant_total_producteur' => 200, 'montant_face_recoit' => 50]);

        $this->assertSame([$b->uuid, $c->uuid, $a->uuid], $this->ids($this->faceUser, 'sort=montant&direction=asc'));
        $this->assertSame([$a->uuid, $c->uuid, $b->uuid], $this->ids($this->faceUser, 'sort=montant&direction=desc'));
    }

    public function test_sort_is_deterministic_with_id_tiebreaker(): void
    {
        $first = $this->booking(['montant_total_producteur' => 100]);
        $second = $this->booking(['montant_total_producteur' => 100]);
        $third = $this->booking(['montant_total_producteur' => 100]);

        $this->assertSame(
            [$first->uuid, $second->uuid, $third->uuid],
            $this->ids($this->producerUser, 'sort=montant&direction=asc')
        );
        $this->assertSame(
            [$third->uuid, $second->uuid, $first->uuid],
            $this->ids($this->producerUser, 'sort=montant&direction=desc')
        );
    }

    public function test_sort_combines_with_status_filter_and_pagination(): void
    {
        $pending = [];
        for ($i = 1; $i <= 12; $i++) {
            $pending[] = $this->booking(['status' => BookingStatus::Pending, 'montant_total_producteur' => $i * 100])->uuid;
        }
        // Hors filtre : ne doit jamais apparaître, même avec le montant le plus bas.
        $this->booking(['status' => BookingStatus::Completed, 'montant_total_producteur' => 1]);

        $page2 = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/bookings?status=pending&sort=montant&direction=asc&per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertSame([$pending[10], $pending[11]], collect($page2->json('data'))->pluck('id')->all());
    }

    public function test_per_page_is_honoured_and_defaults_to_15(): void
    {
        Booking::factory()->count(12)->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->producerUser)->getJson('/api/v1/bookings?per_page=10')
            ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.per_page', 10);
        $this->actingAs($this->producerUser)->getJson('/api/v1/bookings?per_page=10&page=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->actingAs($this->producerUser)->getJson('/api/v1/bookings')
            ->assertOk()->assertJsonPath('meta.per_page', 15);
    }

    public function test_invalid_params_are_rejected_with_422(): void
    {
        foreach (['sort=password', 'sort=created_at&direction=sideways', 'per_page=7', 'per_page=500', 'sort[]=status'] as $query) {
            $this->actingAs($this->producerUser)->getJson('/api/v1/bookings?'.$query)
                ->assertStatus(422);
        }
    }

    public function test_sorted_request_query_count_is_constant(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->producerUser)
                ->getJson('/api/v1/bookings?sort=montant&direction=desc&per_page=50')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->booking();
        $this->booking();
        $small = $count();
        for ($i = 0; $i < 6; $i++) {
            $this->booking(['face_id' => User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => Face::factory()->create()->id,
            ])->id]);
        }
        $large = $count();

        $this->assertSame($small, $large, "Sorted booking index queries grew: {$small} -> {$large}");
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingListTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    // ─── INDEX (LIST) ─────────────────────────────────────────────

    public function test_returns_paginated_list_of_bookings(): void
    {
        Booking::factory()->count(20)->create();

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/bookings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'status', 'status_label',
                    'face' => ['id', 'name', 'email', 'role'],
                    'producer' => ['id', 'name', 'email', 'role'],
                    'tarif_base', 'montant_total_producteur', 'montant_face_recoit',
                    'created_at', 'updated_at',
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonCount(15, 'data');
    }

    public function test_index_is_not_scoped_to_the_admin_user(): void
    {
        // The party-facing endpoint only returns bookings where the caller is the
        // Face or Producer. The admin (a party to none of them) must still see all.
        Booking::factory()->count(3)->create();

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/bookings');

        $response->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_index_exposes_full_financials_and_both_party_emails(): void
    {
        $booking = Booking::factory()->create([
            'tarif_base' => 100000,
            'montant_total_producteur' => 110000,
            'montant_face_recoit' => 90000,
        ]);
        $faceEmail = User::find($booking->face_id)?->email;
        $producerEmail = User::find($booking->producer_id)?->email;

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/bookings');

        $response->assertOk()
            ->assertJsonPath('data.0.tarif_base', 100000)
            ->assertJsonPath('data.0.montant_total_producteur', 110000)
            ->assertJsonPath('data.0.montant_face_recoit', 90000)
            ->assertJsonPath('data.0.face.email', $faceEmail)
            ->assertJsonPath('data.0.producer.email', $producerEmail);
    }

    public function test_search_by_party_email_returns_filtered_results(): void
    {
        $target = Booking::factory()->create();
        Booking::factory()->create();
        $producerEmail = User::find($target->producer_id)?->email;

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/bookings?search='.urlencode((string) $producerEmail));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $target->uuid);
    }

    public function test_search_by_lieu_returns_filtered_results(): void
    {
        Booking::factory()->create(['lieu' => 'Studio Cotonou']);
        Booking::factory()->create(['lieu' => 'Plateau Parakou']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/bookings?search=Cotonou');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.lieu', 'Studio Cotonou');
    }

    public function test_filter_by_status_returns_only_matching(): void
    {
        Booking::factory()->completed()->count(2)->create();
        Booking::factory()->pending()->count(3)->create();
        Booking::factory()->noShow()->create();

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/bookings?status=completed');

        $response->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        Booking::factory()->count(2)->create();

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/bookings?status=not_a_status');

        $response->assertOk()->assertJsonPath('meta.total', 2);
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_show_booking_includes_both_parties_and_money(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'montant_face_recoit' => 72000,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/bookings/{$booking->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'status', 'status_label',
                    'face' => ['id', 'name', 'email', 'role'],
                    'producer' => ['id', 'name', 'email', 'role'],
                    'tarif_base', 'montant_total_producteur', 'montant_face_recoit',
                ],
                'message',
            ])
            ->assertJsonPath('data.id', $booking->uuid)
            ->assertJsonPath('data.montant_face_recoit', 72000)
            ->assertJsonPath('data.face.role', 'Face')
            ->assertJsonPath('data.producer.role', 'Producer');
    }

    // ─── AUTH GUARDS ──────────────────────────────────────────────

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/v1/admin/bookings')->assertUnauthorized();

        $booking = Booking::factory()->create();
        $this->getJson("/api/v1/admin/bookings/{$booking->uuid}")->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_access_admin_bookings(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/v1/admin/bookings')
            ->assertForbidden();
    }
}

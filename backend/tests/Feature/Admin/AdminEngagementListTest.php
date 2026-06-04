<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEngagementListTest extends TestCase
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

    // ─── INCLUSION / UNIFIED SHAPE ───────────────────────────────

    public function test_returns_unified_booking_and_mission_engagements(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid);
        $this->makeMissionEngagement(CandidatureStatus::Accepted);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'type', 'status', 'status_label', 'engaged_since', 'montant_face_recoit',
                    'face' => ['id', 'display_name', 'whatsapp_number', 'has_whatsapp'],
                    'producer' => ['display_name'],
                    'objet' => ['label', 'date', 'detail_id'],
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 20);

        $types = collect($response->json('data'))->pluck('type')->sort()->values()->all();
        $this->assertSame(['booking', 'mission'], $types);
    }

    public function test_mission_engagement_exposes_per_face_amount_from_entry(): void
    {
        $this->makeMissionEngagement(CandidatureStatus::Accepted);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'mission')
            ->assertJsonPath('data.0.montant_face_recoit', 90000)
            ->assertJsonPath('data.0.objet.label', 'Mission Test');
    }

    // ─── BOOKING INCLUSION RULES ─────────────────────────────────

    public function test_excludes_terminal_bookings(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid);
        $this->makeBookingEngagement(BookingStatus::Completed);
        $this->makeBookingEngagement(BookingStatus::CancelledByFace);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'paid');
    }

    public function test_includes_accepted_booking_before_payment(): void
    {
        $this->makeBookingEngagement(BookingStatus::Accepted);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'accepted');
    }

    // ─── CANDIDATURE INCLUSION RULES ─────────────────────────────

    public function test_excludes_pending_and_terminal_candidatures(): void
    {
        $this->makeMissionEngagement(CandidatureStatus::Pending, withEntry: false);
        $this->makeMissionEngagement(CandidatureStatus::Rejected, withEntry: false);
        $this->makeMissionEngagement(CandidatureStatus::Accepted);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'accepted');
    }

    // ─── WHATSAPP RESOLUTION (the User↔Face asymmetry) ───────────

    public function test_resolves_whatsapp_for_booking_face_via_userable(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid, '+229 97 00 00 00');

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'booking')
            ->assertJsonPath('data.0.face.whatsapp_number', '+229 97 00 00 00')
            ->assertJsonPath('data.0.face.has_whatsapp', true);
    }

    public function test_resolves_whatsapp_for_mission_face_directly(): void
    {
        $this->makeMissionEngagement(CandidatureStatus::Confirmed, '+229 96 11 22 33');

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'mission')
            ->assertJsonPath('data.0.face.whatsapp_number', '+229 96 11 22 33')
            ->assertJsonPath('data.0.face.has_whatsapp', true);
    }

    public function test_missing_whatsapp_sets_has_whatsapp_false(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid, null);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.face.whatsapp_number', null)
            ->assertJsonPath('data.0.face.has_whatsapp', false);
    }

    public function test_non_dialable_whatsapp_sets_has_whatsapp_false(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid, 'N/A');

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.face.whatsapp_number', 'N/A')
            ->assertJsonPath('data.0.face.has_whatsapp', false);
    }

    // ─── FILTERS ─────────────────────────────────────────────────

    public function test_filter_by_type_booking_excludes_missions(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid);
        $this->makeMissionEngagement(CandidatureStatus::Accepted);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements?type=booking');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'booking');
    }

    public function test_filter_by_type_mission_excludes_bookings(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid);
        $this->makeMissionEngagement(CandidatureStatus::Accepted);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements?type=mission');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'mission');
    }

    public function test_filter_by_status_returns_only_matching(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid);
        $this->makeBookingEngagement(BookingStatus::Accepted);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements?status=accepted');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'accepted');
    }

    public function test_search_matches_face_display_name(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid, faceAttrs: ['prenom' => 'Awa', 'nom' => 'Traore']);
        $this->makeBookingEngagement(BookingStatus::Paid, faceAttrs: ['prenom' => 'Koffi', 'nom' => 'Mensah']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements?search=Awa');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.face.display_name', 'Awa Traore');
    }

    public function test_search_matches_producer_display_name(): void
    {
        $this->makeBookingEngagement(BookingStatus::Paid, producerAttrs: ['agency_name' => 'Studio Lumiere']);
        $this->makeBookingEngagement(BookingStatus::Paid, producerAttrs: ['agency_name' => 'Autre Agence']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements?search=Lumiere');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.producer.display_name', 'Studio Lumiere');
    }

    // ─── PAGINATION / SORTING ───────────────────────────────────────

    public function test_second_page_uses_recent_sort_order(): void
    {
        $oldestBooking = null;

        for ($i = 0; $i < 21; $i++) {
            $booking = $this->makeBookingEngagement(
                BookingStatus::Paid,
                faceAttrs: ['prenom' => "Face {$i}", 'nom' => 'Pagination'],
            );
            $booking->forceFill([
                'created_at' => now()->subMinutes($i + 1),
                'updated_at' => now()->subMinutes($i),
            ])->save();

            if ($i === 20) {
                $oldestBooking = $booking;
            }
        }

        $this->assertInstanceOf(Booking::class, $oldestBooking);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements?page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 21)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', "booking:{$oldestBooking->uuid}");
    }

    public function test_out_of_range_page_clamps_to_last_page(): void
    {
        $oldestBooking = null;

        for ($i = 0; $i < 21; $i++) {
            $booking = $this->makeBookingEngagement(
                BookingStatus::Paid,
                faceAttrs: ['prenom' => "Clamp {$i}", 'nom' => 'Pagination'],
            );
            $booking->forceFill([
                'created_at' => now()->subMinutes($i + 1),
                'updated_at' => now()->subMinutes($i),
            ])->save();

            if ($i === 20) {
                $oldestBooking = $booking;
            }
        }

        $this->assertInstanceOf(Booking::class, $oldestBooking);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/engagements?page=999');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 21)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', "booking:{$oldestBooking->uuid}");
    }

    // ─── AUTH GUARDS ─────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/admin/engagements')->assertUnauthorized();
    }

    public function test_editor_role_forbidden_403(): void
    {
        $editor = Admin::factory()->editor()->create();
        $editorToken = $editor->createToken('admin-token')->plainTextToken;

        $this->withToken($editorToken)
            ->getJson('/api/v1/admin/engagements')
            ->assertForbidden();
    }

    public function test_non_admin_user_forbidden_403(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/v1/admin/engagements')
            ->assertForbidden();
    }

    // ─── FIXTURE HELPERS ─────────────────────────────────────────

    /**
     * Build a booking engagement: a Face (with whatsapp) behind a User, a
     * Producer behind a User, and a booking linking the two Users.
     *
     * @param  array<string, mixed>  $faceAttrs
     * @param  array<string, mixed>  $producerAttrs
     */
    private function makeBookingEngagement(
        BookingStatus $status,
        ?string $whatsapp = '+229 97 00 00 00',
        array $faceAttrs = [],
        array $producerAttrs = [],
    ): Booking {
        $face = Face::factory()->create(array_merge(['whatsapp_number' => $whatsapp], $faceAttrs));
        $faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $producer = Producer::factory()->agency()->create(array_merge(['agency_name' => 'Agence Test'], $producerAttrs));
        $producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);

        return Booking::factory()->create([
            'face_id' => $faceUser->id,
            'producer_id' => $producerUser->id,
            'status' => $status,
        ]);
    }

    /**
     * Build a mission engagement: a closed mission with a candidature in the
     * given status, and (when $withEntry) the paid MissionPaymentCandidature
     * carrying montant_face_recoit = 90000.
     *
     * @param  array<string, mixed>  $faceAttrs
     */
    private function makeMissionEngagement(
        CandidatureStatus $status,
        ?string $whatsapp = '+229 96 11 22 33',
        bool $withEntry = true,
        array $faceAttrs = [],
    ): Candidature {
        $producer = Producer::factory()->agency()->create(['agency_name' => 'Agence Mission']);
        User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
            'titre' => 'Mission Test',
        ]);

        $face = Face::factory()->create(array_merge(['whatsapp_number' => $whatsapp], $faceAttrs));
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);

        if ($withEntry) {
            $payment = MissionPayment::create([
                'mission_id' => $mission->id,
                'producer_id' => $producer->id,
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

            MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now(),
            ]);
        }

        return $candidature;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateUgcBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Photos produit booking = disque UGC privé (`local`).
        Storage::fake('local');

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create([
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidUgcProductData(): array
    {
        return [
            'face_id' => $this->face->uuid,
            'date_debut' => now()->addWeek()->toDateString(),
            'date_fin' => now()->addWeeks(2)->toDateString(),
            'duree_heures' => 8,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000, // → commission plancher 2500
            'lieu' => 'Cotonou',
            // Photos produit obligatoires (1-2) depuis la décision PO 2026-07-07.
            'product_photos' => [UploadedFile::fake()->image('produit.jpg', 900, 900)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidUgcHybridData(): array
    {
        return array_merge($this->getValidUgcProductData(), [
            'type_compensation' => 'hybrid',
            'valeur_produit' => 50000,        // hybride : valeur produit NON commissionnée (produit off-platform)
            'nombre_videos' => 3,
            'montant_remuneration' => 15000,  // → règlement BookingPricing : Free 0.15 → total 16500 / Face 12750 / WeAct 3750
        ]);
    }

    public function test_server_ignores_client_submitted_commission(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidUgcProductData();
        $data['commission_ugc'] = 999999; // tentative client falsifiée

        // le serveur recalcule via UgcCommissionService (valeur_produit 20000 → 2500), valeur client ignorée
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data)
            ->assertCreated()
            ->assertJsonPath('data.commission_ugc', 2500);

        $this->assertDatabaseMissing('bookings', ['commission_ugc' => 999999]);
    }

    public function test_producer_can_create_ugc_product_only_booking(): void
    {
        Event::fake([BookingCreated::class]);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->getValidUgcProductData());

        $response->assertCreated()
            ->assertJsonPath('data.type_contenu', 'UGC')
            ->assertJsonPath('data.type_compensation', 'product')
            ->assertJsonPath('data.type_compensation_label', 'Produit seul')
            ->assertJsonPath('data.nom_produit', 'Tenue Shade Fit')
            ->assertJsonPath('data.valeur_produit', 20000)
            ->assertJsonPath('data.nombre_videos', 2)
            ->assertJsonPath('data.commission_ugc', 2500)
            ->assertJsonPath('data.montant_remuneration', null)
            ->assertJsonPath('data.tarif_base', 0)
            ->assertJsonPath('data.montant_face_recoit', 0)
            ->assertJsonPath('data.montant_total_producteur', 2500);

        $this->assertDatabaseHas('bookings', [
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending->value,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'montant_face_recoit' => 0,
            'montant_total_producteur' => 2500,
            'tarif_base' => 0,
            'montant_remuneration' => null,
        ]);

        Event::assertDispatchedTimes(BookingCreated::class, 1);
    }

    public function test_producer_can_create_ugc_hybrid_booking(): void
    {
        Event::fake([BookingCreated::class]);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->getValidUgcHybridData());

        // Face fixture sans abonnement → palier Free (0.15). RH.2 : le cash (15000) se tarife comme un
        // booking cash via BookingPricing — Producteur +10 % flat (1500), Face −0.15 (2250), WeAct les deux.
        // total 16500 · net Face 12750 · revenu WeAct 3750. La valeur produit n'est jamais commissionnée.
        $response->assertCreated()
            ->assertJsonPath('data.type_compensation', 'hybrid')
            ->assertJsonPath('data.type_compensation_label', 'Produit + argent')
            ->assertJsonPath('data.valeur_produit', 50000)
            ->assertJsonPath('data.nombre_videos', 3)
            ->assertJsonPath('data.commission_ugc', 3750)
            ->assertJsonPath('data.montant_remuneration', 15000)
            ->assertJsonPath('data.tarif_base', 0)
            ->assertJsonPath('data.montant_face_recoit', 12750)
            ->assertJsonPath('data.montant_total_producteur', 16500);

        $this->assertDatabaseHas('bookings', [
            'type_contenu' => 'UGC',
            'type_compensation' => 'hybrid',
            'commission_ugc' => 3750,
            'montant_remuneration' => 15000,
            'montant_face_recoit' => 12750,
            'montant_total_producteur' => 16500,
            'nombre_videos' => 3,
        ]);

        Event::assertDispatchedTimes(BookingCreated::class, 1);
    }

    public function test_ugc_hybrid_commission_follows_face_tier(): void
    {
        Event::fake([BookingCreated::class]);

        // Face abonnée Élite (0.05). RH.2 BookingPricing : Producteur +10 % flat (1500), Face −0.05 (750).
        // total 16500 (palier-indépendant) · net Face 14250 · revenu WeAct 2250. Produit jamais commissionné.
        \App\Models\FaceSubscription::factory()->elite()->active()->create(['face_id' => $this->face->id]);

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->getValidUgcHybridData())
            ->assertCreated()
            ->assertJsonPath('data.commission_ugc', 2250)            // 1500 (Producteur) + 750 (Face Élite)
            ->assertJsonPath('data.montant_face_recoit', 14250)      // 15000 − 750
            ->assertJsonPath('data.montant_total_producteur', 16500); // 15000 + 1500

        $this->assertDatabaseHas('bookings', [
            'type_contenu' => 'UGC',
            'type_compensation' => 'hybrid',
            'commission_ugc' => 2250,
            'montant_face_recoit' => 14250,
            'montant_total_producteur' => 16500,
        ]);
    }

    public function test_ugc_product_only_forces_video_count_to_2(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidUgcProductData();
        $data['nombre_videos'] = 5; // client tente d'imposer 5 en produit seul

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated()
            ->assertJsonPath('data.nombre_videos', 2);

        $this->assertDatabaseHas('bookings', [
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nombre_videos' => 2,
        ]);
    }

    public function test_ugc_product_only_ignores_client_supplied_financial_fields(): void
    {
        Event::fake([BookingCreated::class]);

        // AC3 / autorité serveur : le client tente d'imposer des montants financiers.
        // Tous sont absents de validated() (aucune règle) et écrasés par le service.
        $data = $this->getValidUgcProductData();
        $data['commission_ugc'] = 1;            // tentative de sous-payer la commission WeAct
        $data['montant_remuneration'] = 99999;  // ignoré en produit seul (jamais hybride)
        $data['montant_total_producteur'] = 1;  // tentative d'écraser le total dérivé
        $data['montant_face_recoit'] = 99999;   // tentative d'imposer un versement Face
        $data['tarif_base'] = 99999;            // tentative d'imposer un tarif

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        // valeur_produit=20000 → commission plancher 2500, recalculée serveur.
        $response->assertCreated()
            ->assertJsonPath('data.commission_ugc', 2500)
            ->assertJsonPath('data.montant_remuneration', null)
            ->assertJsonPath('data.montant_face_recoit', 0)
            ->assertJsonPath('data.tarif_base', 0)
            ->assertJsonPath('data.montant_total_producteur', 2500);

        $this->assertDatabaseHas('bookings', [
            'face_id' => $this->faceUser->id,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'commission_ugc' => 2500,
            'montant_remuneration' => null,
            'montant_face_recoit' => 0,
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
        ]);

        // Le montant frauduleux ne doit jamais être persisté.
        $this->assertDatabaseMissing('bookings', [
            'commission_ugc' => 1,
        ]);
    }

    public function test_ugc_hybrid_requires_remuneration(): void
    {
        $data = $this->getValidUgcHybridData();
        unset($data['montant_remuneration']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('montant_remuneration');
    }

    public function test_ugc_requires_valeur_produit(): void
    {
        $data = $this->getValidUgcProductData();
        unset($data['valeur_produit']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('valeur_produit');
    }

    public function test_ugc_booking_requires_product_photos(): void
    {
        // Décision PO 2026-07-07 : au moins une photo produit est obligatoire (1-2).
        $data = $this->getValidUgcProductData();
        unset($data['product_photos']);

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_photos')
            ->assertJsonPath('errors.product_photos.0', 'Au moins une photo du produit est requise.');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_ugc_rejects_invalid_compensation_type(): void
    {
        $data = $this->getValidUgcProductData();
        $data['type_compensation'] = 'cash';

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type_compensation');
    }

    public function test_ugc_rejects_zero_valeur_produit(): void
    {
        $data = $this->getValidUgcProductData();
        $data['valeur_produit'] = 0;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('valeur_produit');
    }

    public function test_ugc_rejects_valeur_produit_above_storage_limit(): void
    {
        $data = $this->getValidUgcProductData();
        $data['valeur_produit'] = 4294967296;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('valeur_produit');
    }

    public function test_ugc_hybrid_requires_nombre_videos(): void
    {
        $data = $this->getValidUgcHybridData();
        unset($data['nombre_videos']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nombre_videos');
    }

    public function test_ugc_hybrid_rejects_video_count_below_2(): void
    {
        $data = $this->getValidUgcHybridData();
        $data['nombre_videos'] = 1; // option B : hybride à 1 désormais refusé (ugc-4-0)

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre_videos');
    }

    public function test_ugc_hybrid_accepts_video_count_of_2(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidUgcHybridData();
        $data['nombre_videos'] = 2; // nouveau plancher (ugc-4-0)

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data)
            ->assertCreated()
            ->assertJsonPath('data.nombre_videos', 2);
    }

    public function test_ugc_hybrid_rejects_too_many_videos(): void
    {
        $data = $this->getValidUgcHybridData();
        $data['nombre_videos'] = 21;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nombre_videos');
    }

    public function test_ugc_hybrid_rejects_total_amount_above_storage_limit(): void
    {
        // RH.2 : total = cash + frais service 10 % flat. montant_remuneration = MAX → total = MAX + round(MAX×0.10) > MAX → 422.
        $data = $this->getValidUgcHybridData();
        $data['valeur_produit'] = 1;
        $data['montant_remuneration'] = 4294967295;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('montant_remuneration');
    }

    public function test_ugc_hybrid_rejects_total_within_overflow_window(): void
    {
        // RH.2 : montant_total_producteur = cash + frais service 10 % flat (BookingPricing, palier-indépendant).
        // Un montant_remuneration SOUS le MAX mais dont (cash + 10 %) dépasse le MAX doit être rejeté en 422 —
        // pas accepté puis débordé en QueryException/500 à l'insert. Ici le persisté réel =
        // 4e9 + round(0.10 × 4e9) = 4_400_000_000 > 4_294_967_295.
        $data = $this->getValidUgcHybridData();
        $data['valeur_produit'] = 1;
        $data['montant_remuneration'] = 4000000000;

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors('montant_remuneration');
    }

    public function test_ugc_product_only_succeeds_when_face_has_no_tarifs(): void
    {
        Event::fake([BookingCreated::class]);

        $faceNoTarif = Face::factory()->create([
            'tarif_horaire' => 0,
            'tarif_journalier' => 0,
            'is_available' => true,
        ]);
        $faceUserNoTarif = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceNoTarif->id,
        ]);

        $data = $this->getValidUgcProductData();
        $data['face_id'] = $faceNoTarif->uuid;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        // AC4 : la garde tarif ne s'applique pas au chemin UGC.
        $response->assertCreated()
            ->assertJsonPath('data.commission_ugc', 2500)
            ->assertJsonPath('data.tarif_base', 0);

        $this->assertDatabaseHas('bookings', [
            'face_id' => $faceUserNoTarif->id,
            'type_contenu' => 'UGC',
            'commission_ugc' => 2500,
        ]);
    }

    public function test_ugc_booking_can_be_created_without_shoot_fields_and_stores_null(): void
    {
        Event::fake([BookingCreated::class]);

        // A UGC dotation has no shoot date / duration / location — the form omits them.
        $data = $this->getValidUgcProductData();
        unset($data['date_debut'], $data['date_fin'], $data['duree_heures'], $data['lieu']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated()
            ->assertJsonPath('data.type_contenu', 'UGC')
            ->assertJsonPath('data.date_debut', null)
            ->assertJsonPath('data.date_fin', null)
            ->assertJsonPath('data.duree_heures', null)
            ->assertJsonPath('data.lieu', null)
            ->assertJsonPath('data.commission_ugc', 2500);

        $this->assertDatabaseHas('bookings', [
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'duree_heures' => null,
            'lieu' => null,
        ]);
    }

    public function test_ugc_booking_never_persists_shoot_fields_even_when_client_sends_them(): void
    {
        Event::fake([BookingCreated::class]);

        // The full helper includes shoot fields; the server must drop them for UGC (invariant).
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->getValidUgcProductData());

        $response->assertCreated()
            ->assertJsonPath('data.date_debut', null)
            ->assertJsonPath('data.date_fin', null)
            ->assertJsonPath('data.duree_heures', null)
            ->assertJsonPath('data.lieu', null);
    }

    public function test_cash_booking_still_requires_shoot_fields(): void
    {
        // Non-regression: the cash path keeps date/duration/location required.
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', [
                'face_id' => $this->face->uuid,
                'type_contenu' => 'Shooting photo',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_debut', 'date_fin', 'duree_heures', 'lieu']);
    }

    public function test_ugc_booking_creation_notifies_face_without_a_shoot_date(): void
    {
        Mail::fake(); // let the listeners run, without sending a real email

        // Do NOT fake BookingCreated → exercises NotifyFaceOnBookingReceived with a null shoot date.
        $data = $this->getValidUgcProductData();
        unset($data['date_debut'], $data['date_fin'], $data['duree_heures'], $data['lieu']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated();

        // The in-app notification is created → the listener ran past the date formatting without fataling.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'booking_received',
        ]);
    }
}

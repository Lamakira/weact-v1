<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UGC 4.5 — moteur de relance `ugc:process-deadlines`. Couvre l'escalade par
 * palier de progress (ambre/orange/rouge), l'idempotence par
 * shipments.last_notified_threshold (anti-spam, un seul palier au plus haut), la
 * résolution destinataire Face (booking + candidature, piège 2.4), l'exclusion
 * des états *_in_review (AC6) et le contenu de la Notification (level/kind/url).
 * Temps figé en setUp → progress déterministe.
 */
class UgcDeadlineEscalationTest extends TestCase
{
    use RefreshDatabase;

    private const TYPE = 'ugc_deliverable_deadline_approaching';

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create([
            'prenom' => 'Aïcha',
            'nom' => 'Bello',
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    // ===================================================================
    // Fixtures
    // ===================================================================

    /**
     * @return array{0: Booking, 1: Shipment}
     */
    private function makeReceivedBooking(int $recuDaysAgo = 1): array
    {
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,         // users.id
            'producer_id' => $this->producerUser->id, // users.id
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'commission_paid_at' => now()->subDay(),
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
            'montant_face_recoit' => 0,
        ]);

        $shipment = $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(2),
            'recu_le' => now()->subDays($recuDaysAgo),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$booking, $shipment];
    }

    /**
     * @return array{0: Mission, 1: Candidature, 2: Shipment}
     */
    private function makeReceivedCandidature(int $recuDaysAgo = 1): array
    {
        /** @var Mission $mission */
        $mission = $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing sneakers',
            'description' => 'Brief',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 2,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ]);

        $candidature = Candidature::create([
            'face_id' => $this->face->id,  // faces.id (PAS users.id)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $shipment = $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-991337',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(2),
            'recu_le' => now()->subDays($recuDaysAgo),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$mission, $candidature, $shipment];
    }

    /**
     * Deal en avis_pending : Unboxing validé (chrono Avis = validated_at + 14 j),
     * AUCUNE ligne Avis (la Face doit encore l'uploader). D-4.5.b.
     *
     * @return array{0: Booking, 1: Shipment}
     */
    private function makeAvisPendingBooking(int $validatedDaysAgo = 13): array
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $validatedAt = now()->subDays($validatedDaysAgo);
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisPending]);
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => $shipment->recu_le,
            'validated_at' => $validatedAt,
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'duree_seconds' => 42,
        ]);

        return [$booking, $shipment];
    }

    /**
     * Variante candidature de makeAvisPendingBooking.
     *
     * @return array{0: Mission, 1: Candidature, 2: Shipment}
     */
    private function makeAvisPendingCandidature(int $validatedDaysAgo = 13): array
    {
        [$mission, $candidature, $shipment] = $this->makeReceivedCandidature();
        $validatedAt = now()->subDays($validatedDaysAgo);
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisPending]);
        $candidature->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => $shipment->recu_le,
            'validated_at' => $validatedAt,
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'duree_seconds' => 42,
        ]);

        return [$mission, $candidature, $shipment];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Notification>
     */
    private function deadlineNotifications()
    {
        return Notification::where('type', self::TYPE)->orderBy('id')->get();
    }

    // ===================================================================
    // AC2 — escalade au franchissement d'un palier
    // ===================================================================

    public function test_escalates_unboxing_at_amber_threshold_for_booking(): void
    {
        // recu_le = now - 3 j ; span 7 j → progress ≈ 0.43 → palier 1 (ambre).
        [$booking, $shipment] = $this->makeReceivedBooking(3);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $notifs = $this->deadlineNotifications();
        $this->assertCount(1, $notifs);
        $notif = $notifs->first();
        $this->assertSame($this->faceUser->id, $notif->user_id);
        $this->assertSame(1, data_get($notif->data, 'level'));
        $this->assertSame('unboxing', data_get($notif->data, 'kind'));
        $this->assertSame("/face/bookings/{$booking->uuid}", data_get($notif->data, 'url'));
        $this->assertSame($shipment->uuid, data_get($notif->data, 'shipment_id'));
        $this->assertSame(1, $shipment->fresh()->last_notified_threshold);
    }

    public function test_escalates_at_orange_then_red(): void
    {
        // recu_le = now - 5 j → ≈ 0.71 → palier 2 (orange).
        [, $shipment] = $this->makeReceivedBooking(5);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();
        $this->assertCount(1, $this->deadlineNotifications());
        $this->assertSame(2, $shipment->fresh()->last_notified_threshold);

        // Le chrono avance : recu_le = now - 6 j → ≈ 0.857 → palier 3 (rouge).
        $shipment->update(['recu_le' => now()->subDays(6)]);
        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $notifs = $this->deadlineNotifications();
        $this->assertCount(2, $notifs);
        $this->assertSame(2, data_get($notifs[0]->data, 'level'));
        $this->assertSame(3, data_get($notifs[1]->data, 'level'));
        $this->assertSame(3, $shipment->fresh()->last_notified_threshold);
    }

    // ===================================================================
    // AC3 — idempotence par seuil (anti-spam)
    // ===================================================================

    public function test_jump_past_two_thresholds_sends_single_top_level(): void
    {
        // D'emblée à ≈ 0.857 (palier 3) depuis last_notified_threshold = 0 :
        // une SEULE notif au palier le plus haut (pas une par palier sauté).
        [, $shipment] = $this->makeReceivedBooking(6);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $notifs = $this->deadlineNotifications();
        $this->assertCount(1, $notifs);
        $this->assertSame(3, data_get($notifs->first()->data, 'level'));
        // R2 (code-review) : verrouille la copie FR ACCENTUÉE du payload 100 % user-facing
        // (variante palier 3 « suspendu » + kindLabel + nom produit + guillemets). Le token
        // temps-restant (humanizeRemaining) est volontairement EXCLU de l'assertion : il
        // dépend de la troncature à la seconde du round-trip DB de recu_le (« 23 heures »
        // vs « 1 jour ») — non déterministe à la seconde près, et hors du périmètre « copie ».
        $message = (string) data_get($notifs->first()->data, 'message');
        $this->assertStringStartsWith('⏰ Dernière ligne droite : il te reste ', $message);
        $this->assertStringContainsString('pour déposer ton Unboxing « Tenue Shade Fit », sinon ton compte sera suspendu.', $message);
        $this->assertSame(3, $shipment->fresh()->last_notified_threshold);
    }

    public function test_idempotent_no_duplicate_at_same_threshold(): void
    {
        [, $shipment] = $this->makeReceivedBooking(3); // palier 1

        $this->artisan('ugc:process-deadlines')->assertSuccessful();
        $this->artisan('ugc:process-deadlines')->assertSuccessful(); // même progress

        $this->assertCount(1, $this->deadlineNotifications());
        $this->assertSame(1, $shipment->fresh()->last_notified_threshold);
    }

    public function test_below_first_threshold_no_notification(): void
    {
        // recu_le = now - 1 j → ≈ 0.14 → teal/base, aucune notif.
        [, $shipment] = $this->makeReceivedBooking(1);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertCount(0, $this->deadlineNotifications());
        $this->assertSame(0, $shipment->fresh()->last_notified_threshold);
    }

    // ===================================================================
    // AC4 — couverture candidature + chrono Avis + résolution destinataire
    // ===================================================================

    public function test_escalates_avis_for_candidature(): void
    {
        // Unboxing validé il y a 13 j ; chrono Avis 14 j → ≈ 0.93 → palier 3.
        [$mission, , $shipment] = $this->makeAvisPendingCandidature(13);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $notif = $this->deadlineNotifications()->first();
        $this->assertNotNull($notif);
        $this->assertSame($this->faceUser->id, $notif->user_id); // User(Face) résolu (piège 2.4)
        $this->assertSame('avis', data_get($notif->data, 'kind'));
        $this->assertSame(3, data_get($notif->data, 'level'));
        $this->assertSame("/face/missions/{$mission->uuid}", data_get($notif->data, 'url'));
        $this->assertSame(3, $shipment->fresh()->last_notified_threshold);
    }

    public function test_escalates_avis_for_booking(): void
    {
        // R3 (code-review) : owner BOOKING en avis_pending (Unboxing validé il y a
        // 13 j ; chrono Avis 14 j → ≈ 0.93 → palier 3). Couvre la résolution face_id
        // direct × kind='avis' (symétrie avec test_escalates_avis_for_candidature ;
        // ferme le helper makeAvisPendingBooking jusque-là inutilisé).
        [$booking, $shipment] = $this->makeAvisPendingBooking(13);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $notif = $this->deadlineNotifications()->first();
        $this->assertNotNull($notif);
        $this->assertSame($this->faceUser->id, $notif->user_id); // booking.face_id = users.id
        $this->assertSame('avis', data_get($notif->data, 'kind'));
        $this->assertSame(3, data_get($notif->data, 'level'));
        $this->assertSame("/face/bookings/{$booking->uuid}", data_get($notif->data, 'url'));
        $this->assertSame(3, $shipment->fresh()->last_notified_threshold);
    }

    public function test_recipient_is_face_user_for_booking_and_candidature(): void
    {
        [$booking] = $this->makeReceivedBooking(3);           // booking owner (≈ 0.43)
        [$mission] = $this->makeAvisPendingCandidature(13);   // candidature owner (≈ 0.93)

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $notifs = $this->deadlineNotifications();
        $this->assertCount(2, $notifs);
        // Les deux notifs ciblent le même User Face (booking.face_id = users.id ;
        // candidature → User where userable=Face(candidature.face_id)).
        foreach ($notifs as $notif) {
            $this->assertSame($this->faceUser->id, $notif->user_id);
        }
        $urls = $notifs->map(fn (Notification $n) => data_get($n->data, 'url'))->all();
        $this->assertContains("/face/bookings/{$booking->uuid}", $urls);
        $this->assertContains("/face/missions/{$mission->uuid}", $urls);
    }

    // ===================================================================
    // AC6 — états hors-scope non escaladés
    // ===================================================================

    public function test_in_review_states_are_not_escalated(): void
    {
        // Un deal dont la balle est dans le camp du Producteur (Face ne doit rien
        // uploader) n'est jamais escaladé, même chrono largement avancé.
        [, $shipment] = $this->makeReceivedBooking(6);

        $shipment->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);
        $this->artisan('ugc:process-deadlines')->assertSuccessful();
        $this->assertCount(0, $this->deadlineNotifications());

        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisInReview]);
        $this->artisan('ugc:process-deadlines')->assertSuccessful();
        $this->assertCount(0, $this->deadlineNotifications());

        // Le compteur d'escalade n'a pas bougé non plus.
        $this->assertSame(0, $shipment->fresh()->last_notified_threshold);
    }
}

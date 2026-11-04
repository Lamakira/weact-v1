<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract\Concerns;

use App\Models\Booking;
use App\Models\Face;
use App\Models\ImageContract;
use App\Models\Producer;
use App\Models\SignatureChallenge;
use App\Models\User;
use App\Services\ImageContract\SignatureChallengeService;
use Illuminate\Support\Facades\Hash;

/**
 * Fixtures partagées des tests de l'autorisation d'utilisation de l'image.
 * Une Face majeure (30 ans) et un Producteur, tous deux avec un e-mail connu.
 */
trait BuildsImageContracts
{
    protected Producer $producer;

    protected User $producerUser;

    protected Face $face;

    protected User $faceUser;

    protected function setUpParties(bool $enabled = true): void
    {
        config(['image_contracts.enabled' => $enabled]);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
            'email' => 'producteur@example.com',
        ]);

        $this->face = Face::factory()->create([
            'prenom' => 'Aïcha',
            'nom' => 'Bello',
            'date_naissance' => now()->subYears(30)->toDateString(),
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
            'email' => 'aicha@gmail.com',
        ]);
    }

    /**
     * Défi non consommé dont le code en clair est connu.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeChallenge(User $user, string $purpose, string $code = '123456', array $overrides = []): SignatureChallenge
    {
        return SignatureChallenge::create(array_merge([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function terms(array $overrides = []): array
    {
        return array_merge([
            'finalite' => 'Campagne réseaux sociaux de la marque',
            'supports' => ['social', 'web'],
            'territoire' => 'benin',
            'duree_annees' => 3,
        ], $overrides);
    }

    /**
     * Corps de création d'un booking cash avec conditions et signature.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function cashPayload(array $overrides = []): array
    {
        return array_merge([
            'face_id' => $this->face->uuid,
            'date_debut' => now()->addWeeks(2)->toDateString(),
            'date_fin' => now()->addWeeks(2)->toDateString(),
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou',
            'image_terms' => $this->terms(),
            'signature' => ['name' => 'Jean Producteur', 'code' => '123456'],
        ], $overrides);
    }

    protected function pendingCashBooking(): Booking
    {
        return Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->addWeeks(2)->toDateString(),
            'date_fin' => now()->addWeeks(2)->toDateString(),
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeContract(Booking $booking, array $overrides = []): ImageContract
    {
        return ImageContract::create(array_merge([
            'booking_id' => $booking->id,
            'template_version' => 'v1',
            'finalite' => 'Campagne réseaux sociaux de la marque',
            'supports' => ['social', 'web'],
            'territoire' => 'benin',
            'duree_annees' => 3,
            'snapshot' => [
                'parties' => [
                    'producer' => ['name' => 'Studio Test', 'is_agency' => false],
                    'face' => ['name' => 'Aïcha Bello', 'ville' => 'Cotonou'],
                ],
                'projet' => ['type_contenu' => 'Publicité', 'is_ugc' => false, 'lieu' => 'Cotonou', 'date_debut' => null, 'date_fin' => null, 'nom_produit' => null, 'nombre_videos' => null],
                'contrepartie' => ['kind' => 'cash', 'montant_face_recoit' => 90000],
            ],
            'producer_signed_name' => 'Jean Producteur',
            'producer_signed_at' => now()->subHour(),
            'producer_signed_email_masked' => 'p•••••@example.com',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSignedContract(Booking $booking, array $overrides = []): ImageContract
    {
        return $this->makeContract($booking, array_merge([
            'face_signed_name' => 'Aïcha Bello',
            'face_signed_at' => now(),
            'face_signed_email_masked' => 'a•••••@gmail.com',
        ], $overrides));
    }

    protected function acceptPurpose(Booking $booking): string
    {
        return SignatureChallengeService::bookingAcceptPurpose($booking->uuid);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\ImageContract;

use App\Enums\CompensationType;
use App\Enums\ImageContractSupport;
use App\Exceptions\SignatureCodeException;
use App\Models\Booking;
use App\Models\Face;
use App\Models\ImageContract;
use App\Models\Producer;
use App\Models\SignatureChallenge;
use App\Models\User;
use App\Support\EmailMask;
use App\Support\ImageContractTemplate;
use Illuminate\Validation\ValidationException;

class ImageContractService
{
    public function __construct(
        private readonly SignatureChallengeService $challenges,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('image_contracts.enabled');
    }

    /**
     * Vérifie le code du Producteur (booking_request). À appeler dans la transaction de création.
     */
    public function verifyProducerCode(User $producer, string $code): SignatureChallenge|SignatureCodeException
    {
        return $this->challenges->attempt($producer, SignatureChallengeService::PURPOSE_BOOKING_REQUEST, $code);
    }

    /**
     * Crée le contrat signé par le Producteur, dans la transaction de création du booking.
     * Les conditions et les parties sont figées dans le snapshot.
     *
     * @param  array{finalite: string, supports: list<string>, territoire: string, duree_annees: int|string}  $terms
     */
    public function createForBooking(
        Booking $booking,
        User $producerUser,
        Face $face,
        array $terms,
        SignatureChallenge $challenge,
        string $signedName,
        ?string $userAgent,
    ): ImageContract {
        $supports = array_values(array_unique($terms['supports']));
        if (in_array(ImageContractSupport::All->value, $supports, true)) {
            $supports = [ImageContractSupport::All->value];
        }

        return ImageContract::create([
            'booking_id' => $booking->id,
            'template_version' => ImageContractTemplate::VERSION,
            'finalite' => $terms['finalite'],
            'supports' => $supports,
            'territoire' => $terms['territoire'],
            'duree_annees' => (int) $terms['duree_annees'],
            'snapshot' => $this->snapshot($booking, $producerUser, $face),
            'producer_signed_name' => $signedName,
            'producer_signed_at' => now(),
            'producer_signed_email_masked' => EmailMask::mask($challenge->email),
            'producer_user_agent' => $this->truncateUserAgent($userAgent),
        ]);
    }

    /**
     * Le booking porte-t-il un contrat que la Face n'a pas encore signé ?
     * Lecture verrouillante : à appeler sous le verrou du booking.
     */
    public function lockUnsignedContract(Booking $booking): ?ImageContract
    {
        if (! $this->enabled()) {
            return null;
        }

        $contract = ImageContract::query()->where('booking_id', $booking->id)->lockForUpdate()->first();

        return $contract !== null && ! $contract->isSignedByFace() ? $contract : null;
    }

    /**
     * Signature de la Face à l'acceptation : vérifie le code (booking_accept:{uuid}) et pose
     * face_signed_* et guardian_*. Renvoie l'échec au lieu de le lever (cf. attempt()).
     *
     * @param  array{name?: mixed, code?: mixed}|null  $signature
     *
     * @throws ValidationException signature absente
     */
    public function signByFace(
        ImageContract $contract,
        Booking $booking,
        ?array $signature,
        ?string $userAgent,
    ): ImageContract|SignatureCodeException {
        $name = trim((string) ($signature['name'] ?? ''));
        $code = (string) ($signature['code'] ?? '');

        if ($name === '' || $code === '') {
            throw ValidationException::withMessages([
                'signature' => ['La signature du contrat est requise pour accepter ce booking.'],
            ]);
        }

        $faceUser = User::query()->with('userable')->findOrFail($booking->face_id);
        $profile = $faceUser->userable;
        $requireGuardian = $profile instanceof Face && $profile->isMinor();

        $challenge = $this->challenges->attempt(
            $faceUser,
            SignatureChallengeService::bookingAcceptPurpose($booking->uuid),
            $code,
            $requireGuardian,
        );

        if ($challenge instanceof SignatureCodeException) {
            return $challenge;
        }

        $contract->update([
            'face_signed_name' => $name,
            'face_signed_at' => now(),
            'face_signed_email_masked' => EmailMask::mask($challenge->email),
            'face_user_agent' => $this->truncateUserAgent($userAgent),
            'guardian_name' => $challenge->guardian_name,
            'guardian_relation' => $challenge->guardian_relation,
        ]);

        return $contract;
    }

    /**
     * Parties, projet et contrepartie, figés à la création.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Booking $booking, User $producerUser, Face $face): array
    {
        $producerProfile = $producerUser->userable;
        $isHybrid = $booking->type_compensation === CompensationType::Hybrid;
        $isUgc = $booking->type_contenu === 'UGC';

        $contrepartie = match (true) {
            ! $isUgc => [
                'kind' => 'cash',
                'montant_face_recoit' => (int) $booking->montant_face_recoit,
            ],
            $isHybrid => [
                'kind' => 'ugc_hybrid',
                'nom_produit' => $booking->nom_produit,
                'valeur_produit' => (int) $booking->valeur_produit,
                'montant_face_recoit' => (int) $booking->montant_face_recoit,
            ],
            default => [
                'kind' => 'ugc_product',
                'nom_produit' => $booking->nom_produit,
                'valeur_produit' => (int) $booking->valeur_produit,
            ],
        };

        return [
            'parties' => [
                'producer' => [
                    'name' => $producerProfile instanceof Producer ? $producerProfile->display_name : '',
                    'is_agency' => $producerProfile instanceof Producer && $producerProfile->isAgency(),
                ],
                'face' => [
                    'name' => trim($face->prenom.' '.$face->nom),
                    'ville' => $face->ville,
                ],
            ],
            'projet' => [
                'type_contenu' => $booking->type_contenu,
                'is_ugc' => $isUgc,
                'lieu' => $booking->lieu,
                'date_debut' => $booking->date_debut?->toDateString(),
                'date_fin' => $booking->date_fin?->toDateString(),
                'nom_produit' => $booking->nom_produit,
                'nombre_videos' => $booking->nombre_videos,
            ],
            'contrepartie' => $contrepartie,
        ];
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        return $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, 255);
    }
}

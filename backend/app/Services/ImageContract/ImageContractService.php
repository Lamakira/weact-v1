<?php

declare(strict_types=1);

namespace App\Services\ImageContract;

use App\Enums\CompensationType;
use App\Enums\ImageContractSupport;
use App\Enums\ImageContractTerritoire;
use App\Enums\MissionType;
use App\Exceptions\SignatureCodeException;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\ImageContract;
use App\Models\Mission;
use App\Models\MissionImageTerms;
use App\Models\Producer;
use App\Models\SignatureChallenge;
use App\Models\User;
use App\Support\EmailMask;
use App\Support\ImageContractTemplate;
use App\Support\Money;
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
        return ImageContract::create([
            'booking_id' => $booking->id,
            'template_version' => ImageContractTemplate::VERSION,
            'finalite' => $terms['finalite'],
            'supports' => $this->normalizeSupports($terms['supports']),
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
     * Vérifie le code du Producteur (mission_publish). À appeler dans la transaction de création
     * de la mission, AVANT de créer quoi que ce soit.
     */
    public function verifyMissionPublishCode(User $producer, string $code): SignatureChallenge|SignatureCodeException
    {
        return $this->challenges->attempt($producer, SignatureChallengeService::PURPOSE_MISSION_PUBLISH, $code);
    }

    /**
     * Enregistre les conditions d'image signées par le Producteur à la publication. Aucun contrat
     * n'existe encore : il naît à la signature de chaque Face retenue.
     *
     * @param  array{finalite: string, supports: list<string>, territoire: string, duree_annees: int|string}  $terms
     */
    public function createMissionTerms(
        Mission $mission,
        array $terms,
        SignatureChallenge $challenge,
        string $signedName,
        ?string $userAgent,
    ): MissionImageTerms {
        return MissionImageTerms::create([
            'mission_id' => $mission->id,
            'template_version' => ImageContractTemplate::VERSION,
            'finalite' => $terms['finalite'],
            'supports' => $this->normalizeSupports($terms['supports']),
            'territoire' => $terms['territoire'],
            'duree_annees' => (int) $terms['duree_annees'],
            'producer_signed_name' => $signedName,
            'producer_signed_at' => now(),
            'producer_signed_email_masked' => EmailMask::mask($challenge->email),
            'producer_user_agent' => $this->truncateUserAgent($userAgent),
        ]);
    }

    /**
     * Signature de la Face retenue (confirm / reconfirm) : vérifie le code (candidature_confirm:{uuid})
     * et crée le contrat à partir des conditions de la mission. À appeler DANS la transaction de
     * confirmation, sous le verrou de la candidature, avant d'écrire le statut. Renvoie l'échec au
     * lieu de le lever (cf. attempt()). Null = aucun contrat requis (fonctionnalité éteinte, mission
     * sans conditions, contrat déjà existant).
     *
     * @param  array{name?: mixed, code?: mixed}|null  $signature
     *
     * @throws ValidationException signature absente
     */
    public function signForCandidature(
        Candidature $locked,
        User $faceUser,
        ?array $signature,
        ?string $userAgent,
    ): ImageContract|SignatureCodeException|null {
        if (! $this->enabled()) {
            return null;
        }

        // Copie rechargée avec ses relations : la candidature verrouillée de l'appelant n'est pas enrichie
        // (ses réponses API gardent leur forme).
        $candidature = Candidature::query()
            ->with('mission.imageTerms', 'mission.producer', 'face', 'paymentEntry')
            ->findOrFail($locked->id);
        $mission = $candidature->mission;
        $terms = $mission?->imageTerms;

        if ($mission === null || $terms === null) {
            return null;
        }

        if (ImageContract::query()->where('candidature_id', $locked->id)->lockForUpdate()->exists()) {
            return null;
        }

        $name = trim((string) ($signature['name'] ?? ''));
        $code = (string) ($signature['code'] ?? '');

        if ($name === '' || $code === '') {
            throw ValidationException::withMessages([
                'signature' => ['La signature du contrat est requise pour confirmer cette participation.'],
            ]);
        }

        $face = $candidature->face;
        $requireGuardian = $face instanceof Face && $face->isMinor();

        $challenge = $this->challenges->attempt(
            $faceUser,
            SignatureChallengeService::candidatureConfirmPurpose($locked->uuid),
            $code,
            $requireGuardian,
        );

        if ($challenge instanceof SignatureCodeException) {
            return $challenge;
        }

        return ImageContract::create([
            'candidature_id' => $locked->id,
            'template_version' => $terms->template_version,
            'finalite' => $terms->finalite,
            'supports' => $terms->supports,
            'territoire' => $terms->territoire,
            'duree_annees' => $terms->duree_annees,
            'snapshot' => $this->candidatureSnapshot($candidature),
            'producer_signed_name' => $terms->producer_signed_name,
            'producer_signed_at' => $terms->producer_signed_at,
            'producer_signed_email_masked' => $terms->producer_signed_email_masked,
            'producer_user_agent' => $terms->producer_user_agent,
            'face_signed_name' => $name,
            'face_signed_at' => now(),
            'face_signed_email_masked' => EmailMask::mask($challenge->email),
            'face_user_agent' => $this->truncateUserAgent($userAgent),
            'guardian_name' => $challenge->guardian_name,
            'guardian_relation' => $challenge->guardian_relation,
        ]);
    }

    /**
     * Une candidature doit-elle encore signer ? (mission avec conditions, pas de contrat.)
     * Sans verrou : sert à la validation et à l'envoi du code ; la vérité reste signForCandidature.
     */
    public function candidatureNeedsSignature(Candidature $candidature): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $candidature->loadMissing('mission.imageTerms');

        return $candidature->mission?->imageTerms !== null
            && ! ImageContract::query()->where('candidature_id', $candidature->id)->exists();
    }

    /**
     * @param  list<string>  $supports
     * @return list<string>
     */
    private function normalizeSupports(array $supports): array
    {
        $supports = array_values(array_unique($supports));

        return in_array(ImageContractSupport::All->value, $supports, true)
            ? [ImageContractSupport::All->value]
            : $supports;
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
            'parties' => $this->parties($producerProfile instanceof Producer ? $producerProfile : null, $face),
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

    /**
     * Parties figées au snapshot (nom du Producteur = display_name, comme pour le booking).
     *
     * @return array{producer: array{name: string, is_agency: bool}, face: array{name: string, ville: mixed}}
     */
    private function parties(?Producer $producer, Face $face): array
    {
        return [
            'producer' => [
                'name' => $producer !== null ? $producer->display_name : '',
                'is_agency' => $producer !== null && $producer->isAgency(),
            ],
            'face' => [
                'name' => trim($face->prenom.' '.$face->nom),
                'ville' => $face->ville,
            ],
        ];
    }

    /**
     * Snapshot d'une candidature de mission : mêmes clés que celui d'un booking, plus le titre.
     *
     * @return array<string, mixed>
     */
    private function candidatureSnapshot(Candidature $candidature): array
    {
        $candidature->loadMissing('mission.producer', 'face', 'paymentEntry');
        /** @var Mission $mission */
        $mission = $candidature->mission;
        /** @var Face $face */
        $face = $candidature->face;

        $isUgc = $mission->type_mission === MissionType::Ugc;
        $isHybrid = $mission->type_compensation === CompensationType::Hybrid;
        $montant = (int) ($candidature->paymentEntry->montant_face_recoit ?? 0);

        $contrepartie = match (true) {
            ! $isUgc => [
                'kind' => 'cash',
                'montant_face_recoit' => $montant,
            ],
            $isHybrid => [
                'kind' => 'ugc_hybrid',
                'nom_produit' => $mission->nom_produit,
                'valeur_produit' => (int) $mission->valeur_produit,
                'montant_face_recoit' => $montant,
            ],
            default => [
                'kind' => 'ugc_product',
                'nom_produit' => $mission->nom_produit,
                'valeur_produit' => (int) $mission->valeur_produit,
            ],
        };

        return [
            'parties' => $this->parties($mission->producer, $face),
            'projet' => [
                'titre' => $mission->titre,
                'type_contenu' => $mission->type_mission?->label(),
                'is_ugc' => $isUgc,
                'lieu' => $mission->lieu,
                'date_debut' => $isUgc ? null : $mission->date_tournage?->toDateString(),
                'date_fin' => null,
                'nom_produit' => $mission->nom_produit,
                'nombre_videos' => $mission->nombre_videos,
            ],
            'contrepartie' => $contrepartie,
        ];
    }

    /**
     * Lignes de contrepartie lisibles (PDF et e-mail du parent).
     *
     * @param  array<string, mixed>  $contrepartie
     * @return list<string>
     */
    public static function contrepartieLines(array $contrepartie): array
    {
        $produit = 'Produit remis : '.($contrepartie['nom_produit'] ?? '').' (valeur '.Money::format((int) ($contrepartie['valeur_produit'] ?? 0)).')';
        $cash = 'Rémunération reçue par la Face : '.Money::format((int) ($contrepartie['montant_face_recoit'] ?? 0));

        return match ((string) ($contrepartie['kind'] ?? 'cash')) {
            'ugc_product' => [$produit],
            'ugc_hybrid' => [$produit, $cash],
            default => [$cash],
        };
    }

    /**
     * Résumé des conditions montré au parent ou tuteur qui signe pour une Face mineure.
     *
     * @return array{producer: string, finalite: string, supports: string, territoire: string, duree: string, contrepartie: list<string>}
     */
    public function guardianSummary(ImageContract $contract): array
    {
        return $this->summary(
            $contract->snapshot,
            $contract->finalite,
            $contract->supports,
            $contract->territoire,
            $contract->duree_annees,
        );
    }

    /**
     * Même résumé avant tout contrat : construit depuis les conditions de la mission et la candidature.
     *
     * @return array{producer: string, finalite: string, supports: string, territoire: string, duree: string, contrepartie: list<string>}
     */
    public function guardianSummaryForCandidature(Candidature $candidature, MissionImageTerms $terms): array
    {
        return $this->summary(
            $this->candidatureSnapshot($candidature),
            $terms->finalite,
            $terms->supports,
            $terms->territoire,
            $terms->duree_annees,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $supports
     * @return array{producer: string, finalite: string, supports: string, territoire: string, duree: string, contrepartie: list<string>}
     */
    private function summary(array $snapshot, string $finalite, array $supports, string $territoire, int $dureeAnnees): array
    {
        return [
            'producer' => (string) ($snapshot['parties']['producer']['name'] ?? ''),
            'finalite' => $finalite,
            'supports' => collect($supports)
                ->map(fn (string $value): string => ImageContractSupport::from($value)->label())
                ->implode(', '),
            'territoire' => ImageContractTerritoire::from($territoire)->label(),
            'duree' => $dureeAnnees.' '.($dureeAnnees > 1 ? 'ans' : 'an'),
            'contrepartie' => self::contrepartieLines($snapshot['contrepartie'] ?? []),
        ];
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        return $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, 255);
    }
}

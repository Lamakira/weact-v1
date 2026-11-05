<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CandidatureStatus;
use App\Enums\ErrorCodes;
use App\Enums\ImageContractSupport;
use App\Enums\ImageContractTerritoire;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageContract\RequestSignatureCodeRequest;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\ImageContract;
use App\Models\Producer;
use App\Models\User;
use App\Services\FaceEntitlementService;
use App\Services\ImageContract\ImageContractService;
use App\Services\ImageContract\SignatureChallengeService;
use App\Support\EmailMask;
use App\Support\ImageContractTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageContractController extends Controller
{
    public function __construct(
        private readonly ImageContractService $imageContracts,
        private readonly SignatureChallengeService $challenges,
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * Texte du contrat et valeurs permises (source unique pour les écrans).
     */
    public function template(): JsonResponse
    {
        if (! $this->imageContracts->enabled()) {
            return $this->notFound();
        }

        return response()->json([
            'version' => ImageContractTemplate::VERSION,
            'articles' => ImageContractTemplate::articles(),
            'supports' => array_map(
                fn (ImageContractSupport $case): array => ['value' => $case->value, 'label' => $case->label()],
                ImageContractSupport::cases(),
            ),
            'territoires' => array_map(
                fn (ImageContractTerritoire $case): array => ['value' => $case->value, 'label' => $case->label()],
                ImageContractTerritoire::cases(),
            ),
            'durees' => ImageContractTemplate::DUREES,
        ]);
    }

    /**
     * Envoie un code de signature par e-mail : au Producteur avant la création d'un booking ou la
     * publication d'une mission, à la Face (ou à son parent si elle a moins de 18 ans) avant
     * l'acceptation d'un booking ou la confirmation d'une candidature.
     */
    public function sendCode(RequestSignatureCodeRequest $request): JsonResponse
    {
        if (! $this->imageContracts->enabled()) {
            return $this->notFound();
        }

        $user = $request->user();

        $purpose = (string) $request->validated('purpose');

        // Producteur : code vers son propre e-mail, avant de créer un booking ou de publier une mission.
        if (in_array($purpose, [SignatureChallengeService::PURPOSE_BOOKING_REQUEST, SignatureChallengeService::PURPOSE_MISSION_PUBLISH], true)) {
            if ($user->userable_type !== Producer::class) {
                return $this->forbidden();
            }

            $challenge = $this->challenges->issue($user, $purpose, (string) $user->email);

            return $this->sent($challenge->email, $challenge->expires_at);
        }

        if ($purpose === RequestSignatureCodeRequest::PURPOSE_CANDIDATURE_CONFIRM) {
            return $this->sendCandidatureCode($request, $user);
        }

        $booking = Booking::query()->where('uuid', (string) $request->validated('booking_id'))->firstOrFail();

        if ($user->id !== $booking->face_id || $user->userable_type !== Face::class) {
            return $this->forbidden();
        }

        Gate::authorize('accept', $booking);

        // Même garde que l'acceptation : une Face sans accès UGC ne peut pas s'engager sur ce deal.
        if ($booking->type_contenu === 'UGC' && ! $this->entitlement->canAccessUgc($user->userable)) {
            return response()->json(
                ErrorCodes::UgcSubscriptionRequired->envelope(
                    "L'accès aux missions UGC est réservé aux Faces abonnées Pro ou Élite."
                ),
                403
            );
        }

        $contract = $booking->imageContract;
        if ($contract === null || $contract->isSignedByFace()) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Aucun contrat à signer pour ce booking.'),
                422
            );
        }

        return $this->issueFaceCode(
            $request,
            $user,
            SignatureChallengeService::bookingAcceptPurpose($booking->uuid),
            fn (): array => $this->imageContracts->guardianSummary($contract),
        );
    }

    /**
     * Code de la Face retenue sur une mission : elle seule (Candidature.face_id = faces.id) et
     * seulement tant qu'il reste un contrat à signer.
     */
    private function sendCandidatureCode(RequestSignatureCodeRequest $request, User $user): JsonResponse
    {
        $candidature = Candidature::query()->where('uuid', (string) $request->validated('candidature_id'))->firstOrFail();

        if ($user->userable_type !== Face::class || $candidature->face_id !== $user->userable_id) {
            return $this->forbidden();
        }

        $candidature->loadMissing('mission.imageTerms');
        $terms = $candidature->mission?->imageTerms;

        if ($candidature->status !== CandidatureStatus::Accepted
            || $terms === null
            || ! $this->imageContracts->candidatureNeedsSignature($candidature)) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Aucun contrat à signer pour cette candidature.'),
                422
            );
        }

        return $this->issueFaceCode(
            $request,
            $user,
            SignatureChallengeService::candidatureConfirmPurpose($candidature->uuid),
            fn (): array => $this->imageContracts->guardianSummaryForCandidature($candidature, $terms),
        );
    }

    /**
     * Émet le code de la Face : à son e-mail, ou à celui de son parent ou tuteur si elle a moins de
     * 18 ans (le résumé des conditions part alors avec le code).
     *
     * @param  \Closure(): array<string, mixed>  $guardianSummary
     */
    private function issueFaceCode(RequestSignatureCodeRequest $request, User $user, string $purpose, \Closure $guardianSummary): JsonResponse
    {
        /** @var Face $face */
        $face = $user->userable;
        if ($face->date_naissance === null) {
            return response()->json(
                ErrorCodes::BirthDateRequired->envelope('Renseignez votre date de naissance dans votre profil pour signer ce contrat.'),
                422
            );
        }

        $email = (string) $user->email;
        $guardianName = null;
        $guardianRelation = null;

        if ($face->isMinor()) {
            foreach (['guardian_name', 'guardian_relation', 'guardian_email'] as $field) {
                if (blank($request->validated($field))) {
                    throw ValidationException::withMessages([
                        $field => ['Ce champ est obligatoire pour une Face de moins de 18 ans.'],
                    ]);
                }
            }

            $email = trim((string) $request->validated('guardian_email'));
            if (mb_strtolower($email) === mb_strtolower(trim((string) $user->email))) {
                throw ValidationException::withMessages([
                    'guardian_email' => ['Indiquez l\'e-mail de votre parent ou tuteur, pas le vôtre.'],
                ]);
            }
            $guardianName = trim((string) $request->validated('guardian_name'));
            $guardianRelation = (string) $request->validated('guardian_relation');
        }

        $challenge = $this->challenges->issue(
            $user,
            $purpose,
            $email,
            $guardianName,
            $guardianRelation,
            $guardianName !== null ? $guardianSummary() : null,
        );

        return $this->sent($challenge->email, $challenge->expires_at);
    }

    /**
     * Télécharge le PDF du contrat signé (les deux parties du booking seulement).
     */
    public function pdf(Request $request, Booking $booking): StreamedResponse|JsonResponse
    {
        if (! $this->imageContracts->enabled()) {
            return $this->notFound();
        }

        Gate::authorize('view', $booking);

        return $this->downloadPdf($booking->imageContract);
    }

    /**
     * Télécharge le PDF du contrat d'une candidature (la Face propriétaire et le Producteur de la mission seulement).
     */
    public function candidaturePdf(Request $request, Candidature $candidature): StreamedResponse|JsonResponse
    {
        if (! $this->imageContracts->enabled()) {
            return $this->notFound();
        }

        $user = $request->user();
        $candidature->loadMissing('mission');

        $isOwnerFace = $user->userable_type === Face::class && $candidature->face_id === $user->userable_id;
        $isMissionProducer = $user->userable_type === Producer::class && $candidature->mission?->producer_id === $user->userable_id;

        if (! $isOwnerFace && ! $isMissionProducer) {
            return $this->forbidden();
        }

        return $this->downloadPdf($candidature->imageContract);
    }

    private function downloadPdf(?ImageContract $contract): StreamedResponse|JsonResponse
    {
        $disk = Storage::disk((string) config('image_contracts.storage_disk', 'local'));

        if ($contract === null || $contract->pdf_path === null || ! $disk->exists($contract->pdf_path)) {
            return $this->notFound();
        }

        return $disk->download(
            $contract->pdf_path,
            'contrat-image-'.$contract->reference().'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function sent(string $email, \DateTimeInterface $expiresAt): JsonResponse
    {
        return response()->json([
            'data' => [
                'sent_to' => EmailMask::mask($email),
                'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
                'retry_after' => (int) config('image_contracts.resend_delay_seconds', 60),
            ],
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(ErrorCodes::NotFound->envelope('Ressource introuvable.'), 404);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(ErrorCodes::Forbidden->envelope('Action non autorisée.'), 403);
    }
}

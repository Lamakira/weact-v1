<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ErrorCodes;
use App\Enums\ImageContractSupport;
use App\Enums\ImageContractTerritoire;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageContract\RequestSignatureCodeRequest;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
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
     * Envoie un code de signature par e-mail : au Producteur avant la création d'un booking,
     * à la Face (ou à son parent si elle a moins de 18 ans) avant l'acceptation.
     */
    public function sendCode(RequestSignatureCodeRequest $request): JsonResponse
    {
        if (! $this->imageContracts->enabled()) {
            return $this->notFound();
        }

        $user = $request->user();

        if ($request->validated('purpose') === SignatureChallengeService::PURPOSE_BOOKING_REQUEST) {
            if ($user->userable_type !== Producer::class) {
                return $this->forbidden();
            }

            $challenge = $this->challenges->issue($user, SignatureChallengeService::PURPOSE_BOOKING_REQUEST, (string) $user->email);

            return $this->sent($challenge->email, $challenge->expires_at);
        }

        $booking = Booking::query()->where('uuid', (string) $request->validated('booking_id'))->firstOrFail();

        if ($user->id !== $booking->face_id || $user->userable_type !== Face::class) {
            return $this->forbidden();
        }

        Gate::authorize('accept', $booking);

        $contract = $booking->imageContract;
        if ($contract === null || $contract->isSignedByFace()) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Aucun contrat à signer pour ce booking.'),
                422
            );
        }

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

            $email = (string) $request->validated('guardian_email');
            $guardianName = trim((string) $request->validated('guardian_name'));
            $guardianRelation = (string) $request->validated('guardian_relation');
        }

        $challenge = $this->challenges->issue(
            $user,
            SignatureChallengeService::bookingAcceptPurpose($booking->uuid),
            $email,
            $guardianName,
            $guardianRelation,
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

        $contract = $booking->imageContract;
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

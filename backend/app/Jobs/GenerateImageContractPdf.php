<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImageContractSupport;
use App\Enums\ImageContractTerritoire;
use App\Mail\ImageContractSignedMail;
use App\Models\Face;
use App\Models\ImageContract;
use App\Models\Producer;
use App\Models\SignatureChallenge;
use App\Models\User;
use App\Services\ImageContract\ImageContractService;
use App\Services\ImageContract\SignatureChallengeService;
use App\Support\ImageContractTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Génère le PDF signé de l'autorisation d'image, enregistre son empreinte sha256 puis l'envoie
 * aux deux parties (et au parent d'une Face mineure). Idempotent : un contrat qui a déjà son
 * PDF n'est ni régénéré ni renvoyé. L'empreinte ne figure pas dans le PDF qu'elle hache.
 *
 * Porté par l'id (pas un modèle sérialisé) : un contrat disparu entre l'envoi et l'exécution
 * est un no-op journalisé, jamais un job empoisonné.
 */
class GenerateImageContractPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $contractId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $contract = DB::transaction(function (): ?ImageContract {
            $contract = ImageContract::query()->lockForUpdate()->find($this->contractId);

            if ($contract === null) {
                Log::warning('Image contract PDF: contrat introuvable', ['contract_id' => $this->contractId]);

                return null;
            }

            if (! $contract->isSignedByFace()) {
                return null;
            }

            $disk = Storage::disk((string) config('image_contracts.storage_disk', 'local'));

            if ($contract->pdf_path !== null && $disk->exists($contract->pdf_path)) {
                // PDF déjà écrit : ne reste à envoyer que les copies, si elles ne sont pas parties.
                return $contract->copies_sent_at === null ? $contract : null;
            }

            $path = 'image-contracts/'.$contract->uuid.'.pdf';
            $bytes = Pdf::loadView('contracts.image-authorization', $this->viewData($contract))
                ->setPaper('a4')
                ->output();

            if (! $disk->put($path, $bytes)) {
                throw new \RuntimeException("Écriture du PDF impossible pour le contrat {$contract->id}.");
            }

            $contract->update([
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $bytes),
                'copies_sent_at' => null,
            ]);

            return $contract;
        });

        if ($contract === null) {
            return;
        }

        $this->sendCopies($contract);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Image contract PDF generation failed', [
            'contract_id' => $this->contractId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(ImageContract $contract): array
    {
        $snapshot = $contract->snapshot;
        $contrepartie = $snapshot['contrepartie'] ?? [];
        $kind = (string) ($contrepartie['kind'] ?? 'cash');

        $lines = ImageContractService::contrepartieLines($contrepartie);

        return [
            'contract' => $contract,
            'reference' => $contract->reference(),
            'articles' => ImageContractTemplate::articles(),
            'producerName' => (string) ($snapshot['parties']['producer']['name'] ?? ''),
            'faceName' => (string) ($snapshot['parties']['face']['name'] ?? ''),
            'faceVille' => $snapshot['parties']['face']['ville'] ?? null,
            'projet' => $snapshot['projet'] ?? [],
            'contrepartieLines' => $lines,
            'supportsLabel' => collect($contract->supports)
                ->map(fn (string $value): string => ImageContractSupport::from($value)->label())
                ->implode(', '),
            'territoireLabel' => ImageContractTerritoire::from($contract->territoire)->label(),
            'effectiveMoment' => $kind === 'ugc_product'
                ? 'à la réception du produit par la Face'
                : 'après le paiement de la Face',
        ];
    }

    private function sendCopies(ImageContract $contract): void
    {
        $recipients = match (true) {
            $contract->candidature_id !== null => $this->candidatureRecipients($contract),
            $contract->booking_id !== null => $this->bookingRecipients($contract),
            default => null,
        };

        if ($recipients === null) {
            // Ni booking ni candidature (supprimés) : personne à qui écrire, rien ne doit planter.
            Log::warning('Image contract PDF: aucun destinataire, copies non envoyées', ['contract_id' => $contract->id]);

            return;
        }

        $allSent = true;

        foreach (array_unique(array_filter(array_map('trim', $recipients))) as $email) {
            try {
                Mail::to($email)->send(new ImageContractSignedMail($contract->id, $contract->reference(), (string) $contract->pdf_path));
            } catch (\Throwable $e) {
                $allSent = false;
                Log::warning('ImageContractSignedMail failed', [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Un échec laisse copies_sent_at nul : une nouvelle exécution du job renverra les copies.
        if ($allSent) {
            $contract->update(['copies_sent_at' => now()]);
        }
    }

    /**
     * @return list<string>
     */
    private function bookingRecipients(ImageContract $contract): array
    {
        $contract->loadMissing('booking.face', 'booking.producer');
        $booking = $contract->booking;

        $recipients = [
            (string) $booking?->face?->email,
            (string) $booking?->producer?->email,
        ];

        if ($contract->guardian_name !== null && $booking !== null) {
            $recipients[] = (string) SignatureChallenge::query()
                ->where('user_id', $booking->face_id)
                ->where('purpose', 'booking_accept:'.$booking->uuid)
                ->whereNotNull('consumed_at')
                ->orderByDesc('id')
                ->value('email');
        }

        return $recipients;
    }

    /**
     * Face (son User), Producteur de la mission et, pour une Face mineure, le parent qui a reçu le
     * code. Null si la candidature a disparu : no-op journalisé (le contrat survit à sa suppression).
     *
     * @return list<string>|null
     */
    private function candidatureRecipients(ImageContract $contract): ?array
    {
        $contract->loadMissing('candidature.mission');
        $candidature = $contract->candidature;

        if ($candidature === null) {
            return null;
        }

        $faceUser = User::query()
            ->where('userable_type', Face::class)
            ->where('userable_id', $candidature->face_id)
            ->first();
        $producerUser = User::query()
            ->where('userable_type', Producer::class)
            ->where('userable_id', $candidature->mission?->producer_id)
            ->first();

        $recipients = [
            (string) $faceUser?->email,
            (string) $producerUser?->email,
        ];

        if ($contract->guardian_name !== null && $faceUser !== null) {
            $recipients[] = (string) SignatureChallenge::query()
                ->where('user_id', $faceUser->id)
                ->where('purpose', SignatureChallengeService::candidatureConfirmPurpose($candidature->uuid))
                ->whereNotNull('consumed_at')
                ->orderByDesc('id')
                ->value('email');
        }

        return $recipients;
    }
}

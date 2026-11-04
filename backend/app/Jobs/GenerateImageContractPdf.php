<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImageContractSupport;
use App\Enums\ImageContractTerritoire;
use App\Mail\ImageContractSignedMail;
use App\Models\ImageContract;
use App\Models\SignatureChallenge;
use App\Support\ImageContractTemplate;
use App\Support\Money;
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

            $disk = Storage::disk((string) config('image_contracts.storage_disk', 'local'));

            if (! $contract->isSignedByFace()) {
                return null;
            }

            if ($contract->pdf_path !== null && $disk->exists($contract->pdf_path)) {
                return null;
            }

            $path = 'image-contracts/'.$contract->uuid.'.pdf';
            $bytes = Pdf::loadView('contracts.image-authorization', $this->viewData($contract))
                ->setPaper('a4')
                ->output();

            $disk->put($path, $bytes);
            $contract->update([
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $bytes),
            ]);

            return $contract;
        });

        if ($contract === null) {
            return;
        }

        $this->sendCopies($contract);
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(ImageContract $contract): array
    {
        $snapshot = $contract->snapshot;
        $contrepartie = $snapshot['contrepartie'] ?? [];
        $kind = (string) ($contrepartie['kind'] ?? 'cash');

        $lines = match ($kind) {
            'ugc_product' => ['Produit remis : '.($contrepartie['nom_produit'] ?? '').' (valeur '.Money::format((int) ($contrepartie['valeur_produit'] ?? 0)).')'],
            'ugc_hybrid' => [
                'Produit remis : '.($contrepartie['nom_produit'] ?? '').' (valeur '.Money::format((int) ($contrepartie['valeur_produit'] ?? 0)).')',
                'Rémunération reçue par la Face : '.Money::format((int) ($contrepartie['montant_face_recoit'] ?? 0)),
            ],
            default => ['Rémunération reçue par la Face : '.Money::format((int) ($contrepartie['montant_face_recoit'] ?? 0))],
        };

        return [
            'contract' => $contract,
            'reference' => $contract->reference(),
            'version' => $contract->template_version,
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

        foreach (array_unique(array_filter(array_map('trim', $recipients))) as $email) {
            try {
                Mail::to($email)->send(new ImageContractSignedMail($contract->id, $contract->reference(), (string) $contract->pdf_path));
            } catch (\Throwable $e) {
                Log::warning('ImageContractSignedMail failed', [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

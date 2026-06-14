<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Events\DeliverableUploaded;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Shipment;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload du livrable Unboxing d'un deal UGC (FR6 étape 5) : crée la ligne
 * Deliverable À l'upload (D-4.1.b) en in_review, fait avancer le tunnel
 * `received → unboxing_in_review` et notifie le Producteur (post-commit).
 * Transition gardée idempotente sous lock shipment (calque
 * UgcShipmentService::markReceived) ; gardes owner status + refund
 * ré-exécutées sous transaction (D-3.3.f) ; unique DB en backstop (AC3).
 *
 * Le média est écrit sur le disque PRIVÉ (`config('ugc.storage_disk')`),
 * distinct du portfolio FaceVideo (AC5). Les seams ffmpeg/IO sont publics
 * (getVideoDuration appelé aussi par le FormRequest ; storeMedia mockable en
 * test) pour que la logique transactionnelle reste testable sans ffmpeg réel.
 *
 * 4.1 ne traite QUE la branche Unboxing (D-4.1.d) ; le chemin Avis arrive en 4.3.
 */
class UgcDeliverableService
{
    private FFMpeg $ffmpeg;

    private FFProbe $ffprobe;

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe'),
        ]);

        $this->ffprobe = FFProbe::create([
            'ffprobe.binaries' => config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe'),
        ]);
    }

    /**
     * @return array{outcome: string, deliverable?: Deliverable}
     */
    public function uploadUnboxing(Shipment $shipment, UploadedFile $video): array
    {
        $result = DB::transaction(function () use ($shipment, $video): array {
            /** @var Shipment $locked */
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            // Idempotence (AC3) : un Unboxing déjà déposé a fait avancer le
            // tunnel ET créé la ligne. Re-check sous lock — un 2ᵉ POST ne crée
            // jamais de 2ᵉ ligne, n'écrase aucun fichier, ne re-dispatch rien.
            if ($locked->tunnel_status === UgcTunnelStatus::UnboxingInReview
                || $this->unboxingExistsFor($locked)) {
                return ['outcome' => 'already_uploaded'];
            }

            // Fenêtre d'upload (AC4) : seul `received` (recu_le non null, chrono
            // Unboxing actif) accepte le dépôt. Tout autre état = fenêtre fermée.
            if ($locked->tunnel_status !== UgcTunnelStatus::Received || $locked->recu_le === null) {
                return ['outcome' => 'invalid_status'];
            }

            $owner = $locked->owner;

            if (! $owner instanceof Booking && ! $owner instanceof Candidature) {
                return ['outcome' => 'invalid_status'];
            }

            // Gardes owner status + refund ré-exécutées (parité markReceived,
            // D-3.3.f) : une Face engagée poursuit son tunnel, mais un deal
            // annulé / non-UGC / en cours de remboursement ne reçoit pas d'upload.
            $guard = $owner instanceof Booking
                ? $this->guardBooking($owner)
                : $this->guardCandidature($owner);

            if ($guard !== null) {
                return ['outcome' => $guard];
            }

            // Stockage média (disque privé) DANS la transaction, après gardes —
            // cleanup sur tout échec en aval.
            $media = $this->storeMedia($video, DeliverableKind::Unboxing);

            try {
                /** @var Deliverable $deliverable */
                $deliverable = $owner->deliverables()->create([
                    'kind' => DeliverableKind::Unboxing,
                    'validation_status' => DeliverableValidationStatus::InReview,
                    'chrono_started_at' => $locked->recu_le,
                    // app() (et non l'injection constructeur) : aligné sur
                    // ShipmentResource, et garde uploadUnboxing testable via
                    // partialMock (qui n'exécute pas le constructeur).
                    'deadline_at' => app(UgcDeadlineService::class)->unboxingDeadlineFor($locked),
                    'video_path' => $media['video_path'],
                    'thumbnail_path' => $media['thumbnail_path'],
                    'duree_seconds' => $media['duree_seconds'],
                ]);

                $locked->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);

                return ['outcome' => 'uploaded', 'deliverable' => $deliverable];
            } catch (UniqueConstraintViolationException) {
                // Backstop deliverables_owner_kind_unique (AC3) : un writer
                // concurrent a gagné la course — même contrat ALREADY_UPLOADED,
                // pas une 500. On nettoie le fichier qu'on vient d'écrire.
                $this->cleanupMedia($media);

                return ['outcome' => 'already_uploaded'];
            } catch (\Throwable $e) {
                $this->cleanupMedia($media);

                throw $e;
            }
        });

        // Post-commit : un rollback ne notifie pas (D-2.4.f reconduite).
        if ($result['outcome'] === 'uploaded') {
            DeliverableUploaded::dispatch($result['deliverable']);
        }

        return $result;
    }

    /**
     * Durée vidéo via ffprobe. Seam public : appelé aussi par
     * UploadDeliverableRequest::withValidator (sonde « illisible » + cap optionnel).
     */
    public function getVideoDuration(UploadedFile $video): float
    {
        return (float) $this->ffprobe
            ->format($video->getRealPath())
            ->get('duration');
    }

    /**
     * Écrit la vidéo + sa miniature sur le disque PRIVÉ et renvoie les chemins
     * relatifs + la durée. Seam public isolé pour mock en test (la logique
     * transactionnelle de uploadUnboxing reste testée sans ffmpeg réel).
     *
     * @return array{video_path: string, thumbnail_path: string, duree_seconds: int}
     */
    public function storeMedia(UploadedFile $video, DeliverableKind $kind): array
    {
        $disk = Storage::disk((string) config('ugc.storage_disk', 'local'));
        $uuid = (string) Str::uuid();
        $extension = $video->getClientOriginalExtension() ?: 'mp4';

        $dir = "ugc/deliverables/{$kind->value}";
        $thumbnailDir = "{$dir}/thumbnails";
        $videoFilename = "{$uuid}.{$extension}";
        $thumbnailFilename = "{$uuid}.jpg";
        $videoPath = "{$dir}/{$videoFilename}";
        $thumbnailPath = "{$thumbnailDir}/{$thumbnailFilename}";

        // Durée probée AVANT stockage (le fichier temporaire est encore frais).
        $duration = (int) round($this->getVideoDuration($video));

        $disk->makeDirectory($dir);
        $disk->makeDirectory($thumbnailDir);

        try {
            $disk->putFileAs($dir, $video, $videoFilename);

            // Miniature ffmpeg (frame 0) sur le fichier stocké (disque privé).
            $this->generateThumbnail($disk->path($videoPath), $disk->path($thumbnailPath));
        } catch (\Throwable $e) {
            // Chemin d'erreur IO/ffmpeg : si la miniature (ou putFileAs) échoue
            // APRÈS l'écriture de la vidéo, on supprime les fichiers partiels —
            // sinon ils restent orphelins (la transaction appelante rollback la
            // ligne mais ne nettoie pas le disque). Calque FaceVideoService::
            // uploadVideo (cleanup des 2 fichiers sur \Throwable puis re-throw).
            $disk->delete($videoPath);
            $disk->delete($thumbnailPath);

            throw $e;
        }

        return [
            'video_path' => $videoPath,
            'thumbnail_path' => $thumbnailPath,
            'duree_seconds' => $duration,
        ];
    }

    private function unboxingExistsFor(Shipment $shipment): bool
    {
        // Query par les clés morph du shipment (= celles du deliverable, même
        // owner) : pas besoin de charger la relation owner pour l'idempotence.
        return Deliverable::query()
            ->where('owner_type', $shipment->owner_type)
            ->where('owner_id', $shipment->owner_id)
            ->where('kind', DeliverableKind::Unboxing)
            ->exists();
    }

    /**
     * @param  array{video_path: string, thumbnail_path: string, duree_seconds: int}  $media
     */
    private function cleanupMedia(array $media): void
    {
        $disk = Storage::disk((string) config('ugc.storage_disk', 'local'));
        $disk->delete($media['video_path']);
        $disk->delete($media['thumbnail_path']);
    }

    private function generateThumbnail(string $videoFullPath, string $thumbnailFullPath): void
    {
        $thumbnailDir = dirname($thumbnailFullPath);
        if (! is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        /** @var Video $video */
        $video = $this->ffmpeg->open($videoFullPath);
        $video
            ->frame(TimeCode::fromSeconds(0))
            ->save($thumbnailFullPath);
    }

    private function guardBooking(Booking $booking): ?string
    {
        if ($booking->type_contenu !== 'UGC' || $booking->status !== BookingStatus::Accepted) {
            return 'invalid_status';
        }

        // Propagation D-2.5.h : refund demandé OU réglé hors-procédure → un deal
        // en cours de remboursement ne reçoit pas de livrable.
        if ($booking->commission_refund_requested_at !== null || $booking->commission_refunded_at !== null) {
            return 'refund_in_progress';
        }

        return null;
    }

    private function guardCandidature(Candidature $candidature): ?string
    {
        $mission = $candidature->mission;

        if ($candidature->status !== CandidatureStatus::Confirmed
            || $mission === null
            || $mission->type_mission !== MissionType::Ugc
            || $mission->commission_paid_at === null) {
            return 'invalid_status';
        }

        if ($mission->commission_refund_requested_at !== null || $mission->commission_refunded_at !== null) {
            return 'refund_in_progress';
        }

        return null;
    }
}

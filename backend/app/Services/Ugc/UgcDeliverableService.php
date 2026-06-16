<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Events\DeliverableRejected;
use App\Events\DeliverableRetoucheRequested;
use App\Events\DeliverableUploaded;
use App\Events\DeliverableValidated;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Shipment;
use App\Services\BookingService;
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
 * Cycle livrable d'un deal UGC (FR6 étape 5 / FR7) co-localisé dans un seul
 * service (D-4.3.h) :
 *  - upload() : dépôt Face de l'Unboxing puis de l'Avis (+ re-upload après
 *    reject/retouche, update-in-place) — crée/maj la ligne Deliverable, fait
 *    avancer le tunnel et notifie le Producteur (post-commit) ;
 *  - validate() / reject() / requestRetouche() : décision Producteur — fait
 *    avancer (Unboxing→avis_pending, Avis→completed) ou rouvre (reject/retouche)
 *    le tunnel, notifie la Face (post-commit).
 *
 * Transitions gardées idempotentes sous lock shipment (+ lock deliverable pour
 * la validation) ; gardes owner status + refund ré-exécutées sous transaction
 * (D-3.3.f) ; unique DB en backstop (AC3/AC6). Le média est écrit sur le disque
 * PRIVÉ (config('ugc.storage_disk')), distinct du portfolio FaceVideo. Les seams
 * ffmpeg/IO sont publics (getVideoDuration appelé aussi par le FormRequest ;
 * storeMedia mockable en test) pour que la logique transactionnelle reste
 * testable sans ffmpeg réel.
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
     * Dépôt Face d'un livrable (Unboxing ou Avis, première soumission ou
     * re-upload après reject/retouche). Le kind ciblé + la fenêtre sont
     * dispatchés par tunnel_status sous lock (table d'états story 4.3) :
     *  received → unboxing ; avis_pending → avis ; *_in_review → already_uploaded
     *  (idempotence 4.1) ; tout autre état → invalid_status.
     *
     * @return array{outcome: string, deliverable?: Deliverable, old_media?: array{video_path: string, thumbnail_path: string|null}}
     */
    public function upload(Shipment $shipment, UploadedFile $video): array
    {
        $result = DB::transaction(function () use ($shipment, $video): array {
            /** @var Shipment $locked */
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            // Idempotence (AC3, préservée 4.1) : un livrable du cycle courant est
            // déjà soumis (tunnel en *_in_review) → already_uploaded. Un 2ᵉ POST ne
            // crée jamais de 2ᵉ ligne, n'écrase aucun fichier, ne re-dispatch rien.
            if ($locked->tunnel_status === UgcTunnelStatus::UnboxingInReview
                || $locked->tunnel_status === UgcTunnelStatus::AvisInReview) {
                return ['outcome' => 'already_uploaded'];
            }

            // Fenêtre d'upload par tunnel_status : `received` ouvre l'Unboxing,
            // `avis_pending` ouvre l'Avis. Tout autre état = fenêtre fermée.
            $kind = match ($locked->tunnel_status) {
                UgcTunnelStatus::Received => DeliverableKind::Unboxing,
                UgcTunnelStatus::AvisPending => DeliverableKind::Avis,
                default => null,
            };

            if ($kind === null) {
                return ['outcome' => 'invalid_status'];
            }

            // Garde Unboxing 4.1 préservée : `received` exige recu_le (chrono actif).
            if ($kind === DeliverableKind::Unboxing && $locked->recu_le === null) {
                return ['outcome' => 'invalid_status'];
            }

            $owner = $locked->owner;

            if (! $owner instanceof Booking && ! $owner instanceof Candidature) {
                return ['outcome' => 'invalid_status'];
            }

            // Gardes owner status + refund ré-exécutées (parité markReceived, D-3.3.f) :
            // une Face engagée poursuit son tunnel, mais un deal annulé / non-UGC /
            // en cours de remboursement ne reçoit pas d'upload.
            $guard = $owner instanceof Booking
                ? $this->guardBooking($owner)
                : $this->guardCandidature($owner);

            if ($guard !== null) {
                return ['outcome' => $guard];
            }

            // Ligne existante du kind ciblé : backstop course concurrente +
            // branche re-upload (D-4.3.f).
            $existing = $this->deliverableFor($locked, $kind);

            if ($existing !== null
                && $existing->validation_status !== DeliverableValidationStatus::Rejected
                && $existing->validation_status !== DeliverableValidationStatus::RetoucheRequested) {
                // in_review / validated sous lock → déjà soumis (course concurrente).
                return ['outcome' => 'already_uploaded'];
            }

            // Pour une PREMIÈRE soumission, dérive chrono + deadline serveur AVANT
            // de stocker le média (un état incohérent échoue sans écrire sur disque).
            $chronoStartedAt = null;
            $deadlineAt = null;

            if ($existing === null) {
                $deadlineService = app(UgcDeadlineService::class);

                if ($kind === DeliverableKind::Unboxing) {
                    $chronoStartedAt = $locked->recu_le;
                    $deadlineAt = $deadlineService->unboxingDeadlineFor($locked);
                } else {
                    // Avis : ancré sur la validation de l'Unboxing (D-4.3.e).
                    $deadlineAt = $deadlineService->avisDeadlineFor($locked);
                    $unboxing = $this->deliverableFor($locked, DeliverableKind::Unboxing);

                    // Défensif : avis_pending implique un Unboxing validé, mais on
                    // garde le filet (état incohérent) → invalid_status, aucun write/IO.
                    if ($deadlineAt === null || $unboxing === null || $unboxing->validated_at === null) {
                        return ['outcome' => 'invalid_status'];
                    }

                    $chronoStartedAt = $unboxing->validated_at;
                }
            }

            // Stockage média (disque privé) DANS la transaction, après gardes —
            // cleanup sur tout échec en aval.
            $media = $this->storeMedia($video, $kind);

            try {
                if ($existing !== null) {
                    // Re-upload update-in-place (D-4.3.f) : la ligne rejected/retouche
                    // transite vers in_review sur une PK stable ; chrono CONSERVÉ
                    // (D-4.3.b). Ancien média capturé pour suppression post-commit.
                    $oldMedia = [
                        'video_path' => $existing->video_path,
                        'thumbnail_path' => $existing->thumbnail_path,
                    ];

                    $existing->update([
                        'validation_status' => DeliverableValidationStatus::InReview,
                        'review_note' => null,
                        'validated_at' => null,
                        'submitted_at' => now(),       // nouveau cycle SLA (D-4.3.g)
                        'video_path' => $media['video_path'],
                        'thumbnail_path' => $media['thumbnail_path'],
                        'duree_seconds' => $media['duree_seconds'],
                    ]);

                    $deliverable = $existing;
                } else {
                    /** @var Deliverable $deliverable */
                    $deliverable = $owner->deliverables()->create([
                        'kind' => $kind,
                        'validation_status' => DeliverableValidationStatus::InReview,
                        'chrono_started_at' => $chronoStartedAt,
                        'deadline_at' => $deadlineAt,
                        'submitted_at' => now(),
                        'video_path' => $media['video_path'],
                        'thumbnail_path' => $media['thumbnail_path'],
                        'duree_seconds' => $media['duree_seconds'],
                    ]);

                    $oldMedia = null;
                }

                // Avance le tunnel : ouvre la fenêtre de review du kind déposé.
                $locked->update([
                    'tunnel_status' => $kind === DeliverableKind::Unboxing
                        ? UgcTunnelStatus::UnboxingInReview
                        : UgcTunnelStatus::AvisInReview,
                ]);

                return ['outcome' => 'uploaded', 'deliverable' => $deliverable, 'old_media' => $oldMedia];
            } catch (UniqueConstraintViolationException) {
                // Backstop deliverables_owner_kind_unique (AC3) : un writer concurrent
                // a gagné la course — même contrat ALREADY_UPLOADED, pas une 500.
                $this->cleanupMedia($media);

                return ['outcome' => 'already_uploaded'];
            } catch (\Throwable $e) {
                $this->cleanupMedia($media);

                throw $e;
            }
        });

        // Post-commit : un rollback ne notifie pas / n'efface pas (D-2.4.f reconduite).
        if ($result['outcome'] === 'uploaded') {
            // Ancien média (re-upload) supprimé POST-COMMIT, hors transaction (un
            // rollback ne doit pas effacer le fichier de la ligne courante — AC6).
            $oldMedia = $result['old_media'] ?? null;
            if ($oldMedia !== null) {
                $this->cleanupMedia([
                    'video_path' => $oldMedia['video_path'],
                    'thumbnail_path' => (string) ($oldMedia['thumbnail_path'] ?? ''),
                ]);
            }

            DeliverableUploaded::dispatch($result['deliverable']);
        }

        return $result;
    }

    /**
     * Validation Producteur d'un livrable (AC2/AC4). Unboxing validé → démarre le
     * chrono Avis (tunnel avis_pending) ; Avis validé → clôture : tunnel `completed`.
     * Pour un BOOKING (RH.3, supersède D-4.3.a), dans la MÊME transaction : release
     * escrow → wallet Face (hybride ; produit-seul no-op) + `BookingStatus → Completed`
     * (calque completeBooking). Pour une CANDIDATURE (mission), tunnel-only — pas
     * d'escrow per-engagement (→ ugc-epic-rh-mission). Idempotent (re-valider un
     * non-in_review → invalid_status).
     *
     * @return array{outcome: string, deliverable?: Deliverable}
     */
    public function validate(Deliverable $deliverable): array
    {
        $result = DB::transaction(function () use ($deliverable): array {
            $context = $this->lockReviewContext($deliverable);
            if (is_string($context)) {
                return ['outcome' => $context];
            }
            [$shipment, $fresh, $owner] = $context;

            $fresh->update([
                'validation_status' => DeliverableValidationStatus::Validated,
                'validated_at' => now(),
                'review_note' => null,
            ]);

            // Unboxing validé → démarre le chrono Avis (avis_pending) ;
            // Avis validé → clôture (completed).
            $shipment->update([
                'tunnel_status' => $fresh->kind === DeliverableKind::Unboxing
                    ? UgcTunnelStatus::AvisPending
                    : UgcTunnelStatus::Completed,
                // D-4.5.e : ré-arme l'escalade pour le NOUVEAU chrono (Avis) — sans
                // ce reset la Face hériterait du compteur Unboxing et sauterait
                // ambre/orange sur l'Avis. No-op à la clôture (completed n'est plus
                // escaladé) et au reject/retouche (même chrono → compteur conservé).
                'last_notified_threshold' => 0,
            ]);

            // RH.3 : valider l'Avis clôture le deal. Pour un booking, dans la MÊME
            // transaction (atomicité argent/tunnel) : release escrow → wallet Face
            // (hybride ; produit-seul no-op) + BookingStatus → Completed (calque
            // completeBooking). Mission (Candidature) : tunnel-only — pas d'escrow
            // per-engagement (→ ugc-epic-rh-mission).
            if ($fresh->kind === DeliverableKind::Avis && $owner instanceof Booking) {
                app(BookingService::class)->completeUgcBooking($owner);
            }

            return ['outcome' => 'validated', 'deliverable' => $fresh];
        });

        if ($result['outcome'] === 'validated') {
            DeliverableValidated::dispatch($result['deliverable']); // post-commit
        }

        return $result;
    }

    /**
     * Rejet Producteur d'un livrable (AC5) : statut rejected, motif enregistré,
     * fenêtre d'upload du même kind rouverte, chrono Face CONSERVÉ (D-4.3.b).
     *
     * @return array{outcome: string, deliverable?: Deliverable}
     */
    public function reject(Deliverable $deliverable, string $note): array
    {
        return $this->applyRejection($deliverable, DeliverableValidationStatus::Rejected, $note);
    }

    /**
     * Demande de retouche Producteur (AC5) : identique au rejet côté tunnel/chrono
     * (D-4.3.b), ne diffère que par le statut retouche_requested + l'event/notif.
     *
     * @return array{outcome: string, deliverable?: Deliverable}
     */
    public function requestRetouche(Deliverable $deliverable, string $note): array
    {
        return $this->applyRejection($deliverable, DeliverableValidationStatus::RetoucheRequested, $note);
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
     * transactionnelle de upload reste testée sans ffmpeg réel).
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

    /**
     * Rejet OU retouche (mutualisés — ne diffèrent que par le statut + l'event,
     * D-4.3.i). Lock livrable + shipment, idempotence in_review, cohérence tunnel,
     * gardes owner/refund ; rouvre la fenêtre du même kind, chrono CONSERVÉ ;
     * dispatch event post-commit.
     *
     * @return array{outcome: string, deliverable?: Deliverable}
     */
    private function applyRejection(Deliverable $deliverable, DeliverableValidationStatus $status, string $note): array
    {
        $result = DB::transaction(function () use ($deliverable, $status, $note): array {
            $context = $this->lockReviewContext($deliverable);
            if (is_string($context)) {
                return ['outcome' => $context];
            }
            [$shipment, $fresh] = $context;

            $fresh->update([
                'validation_status' => $status,
                'review_note' => $note,
                'validated_at' => null,
            ]);

            // Rouvre la fenêtre d'upload du MÊME kind ; deadline_at INCHANGÉ (D-4.3.b).
            $shipment->update([
                'tunnel_status' => $fresh->kind === DeliverableKind::Unboxing
                    ? UgcTunnelStatus::Received
                    : UgcTunnelStatus::AvisPending,
            ]);

            return [
                'outcome' => $status === DeliverableValidationStatus::Rejected ? 'rejected' : 'retouche_requested',
                'deliverable' => $fresh,
            ];
        });

        if ($result['outcome'] === 'rejected') {
            DeliverableRejected::dispatch($result['deliverable']); // post-commit
        } elseif ($result['outcome'] === 'retouche_requested') {
            DeliverableRetoucheRequested::dispatch($result['deliverable']);
        }

        return $result;
    }

    /**
     * Lock livrable + shipment, gardes owner/refund, préconditions review
     * (idempotence in_review + cohérence tunnel ↔ kind). Renvoie le contexte
     * verrouillé ou un code d'outcome ('invalid_status' | 'refund_in_progress').
     *
     * @return array{0: Shipment, 1: Deliverable, 2: Booking|Candidature}|string
     */
    private function lockReviewContext(Deliverable $deliverable): array|string
    {
        /** @var Deliverable $fresh */
        $fresh = Deliverable::query()->lockForUpdate()->findOrFail($deliverable->id);

        /** @var Shipment|null $shipment */
        $shipment = Shipment::query()
            ->where('owner_type', $fresh->owner_type)
            ->where('owner_id', $fresh->owner_id)
            ->lockForUpdate()
            ->first();

        if ($shipment === null) {
            return 'invalid_status';
        }

        // Idempotence : seul un in_review est révisable (re-statuer = 422, no-op).
        if ($fresh->validation_status !== DeliverableValidationStatus::InReview) {
            return 'invalid_status';
        }

        // Cohérence tunnel ↔ kind.
        $expected = $fresh->kind === DeliverableKind::Unboxing
            ? UgcTunnelStatus::UnboxingInReview
            : UgcTunnelStatus::AvisInReview;
        if ($shipment->tunnel_status !== $expected) {
            return 'invalid_status';
        }

        $owner = $fresh->owner;
        if (! $owner instanceof Booking && ! $owner instanceof Candidature) {
            return 'invalid_status';
        }

        // Gardes owner status + refund (parité upload/markReceived).
        $guard = $owner instanceof Booking ? $this->guardBooking($owner) : $this->guardCandidature($owner);
        if ($guard !== null) {
            return $guard; // 'invalid_status' | 'refund_in_progress'
        }

        return [$shipment, $fresh, $owner];
    }

    /**
     * Ligne Deliverable du kind donné pour le deal du shipment (query par les
     * clés morph — pas besoin de charger la relation owner). Généralise l'ancien
     * unboxingExistsFor : 4.3 ajoute le chemin Avis.
     */
    private function deliverableFor(Shipment $shipment, DeliverableKind $kind): ?Deliverable
    {
        return Deliverable::query()
            ->where('owner_type', $shipment->owner_type)
            ->where('owner_id', $shipment->owner_id)
            ->where('kind', $kind)
            ->first();
    }

    /**
     * @param  array{video_path: string, thumbnail_path: string}  $media
     */
    private function cleanupMedia(array $media): void
    {
        $disk = Storage::disk((string) config('ugc.storage_disk', 'local'));
        $disk->delete($media['video_path']);
        if ($media['thumbnail_path'] !== '') {
            $disk->delete($media['thumbnail_path']);
        }
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

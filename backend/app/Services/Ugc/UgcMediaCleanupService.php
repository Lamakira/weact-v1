<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\ProductPhoto;
use App\Models\Shipment;
use App\Models\User;
use App\Support\ImageVariantGenerator;

/**
 * Nettoyage explicite des médias UGC (product_photos + shipments + deliverables)
 * au hard-delete d'une entité racine (Mission / Face-user / Producer-user).
 *
 * Ces 3 tables enfants sont polymorphes SANS FK : une cascade DB ne les touche
 * jamais et ne réveille aucun event Eloquent. Le nettoyage est donc appelé
 * explicitement aux 3 sites de hard-delete, DANS leur transaction et AVANT la
 * cascade, pour supprimer les fichiers (disque public/privé, résolu par-row) ET
 * les rows tant que l'owner existe encore — sinon rows et fichiers restent
 * orphelins (ce que la commande ugc:purge-orphan-media rattrape a posteriori).
 *
 * Idempotent et défensif (calque ProductPhotoService::detachAll /
 * ImageVariantGenerator::deleteFiles) : un fichier déjà absent est ignoré, une
 * entité sans média est un no-op.
 */
class UgcMediaCleanupService
{
    public function __construct(
        private readonly ImageVariantGenerator $imageVariantGenerator,
        private readonly UgcDeliverableService $deliverableService,
    ) {}

    /**
     * Nettoie les médias d'une mission supprimée : ses product_photos (disque
     * public) ET, pour chaque candidature, son shipment (+ photos de réception)
     * et ses deliverables. Appelé par MissionService::deleteMission.
     */
    public function purgeForMission(Mission $mission): void
    {
        $this->purgeOwner($mission);

        foreach ($mission->candidatures()->get() as $candidature) {
            /** @var Candidature $candidature */
            $this->purgeOwner($candidature);
        }
    }

    /**
     * Nettoie les médias rattachés à une Face supprimée : les bookings dont elle
     * est la Face (bookings.face_id = users.id) et les candidatures de son profil
     * (candidatures.face_id = faces.id, résolues via userable). No-op si null.
     */
    public function purgeForFaceUser(?User $user): void
    {
        if ($user === null) {
            return;
        }

        foreach (Booking::query()->where('face_id', $user->id)->get() as $booking) {
            $this->purgeOwner($booking);
        }

        $face = $user->userable;
        if ($face instanceof Face) {
            foreach ($face->candidatures()->get() as $candidature) {
                /** @var Candidature $candidature */
                $this->purgeOwner($candidature);
            }
        }
    }

    /**
     * Nettoie les médias rattachés à un Producteur supprimé : les bookings dont
     * il est le Producteur (bookings.producer_id = users.id) et, pour chaque
     * mission de son profil, tout le sous-arbre (product_photos mission publiques
     * + candidatures) via purgeForMission. C'est le chemin admin qui contourne
     * deleteMission (la cascade Producer→Mission ne le déclenche pas). No-op si
     * null.
     */
    public function purgeForProducerUser(?User $user): void
    {
        if ($user === null) {
            return;
        }

        foreach (Booking::query()->where('producer_id', $user->id)->get() as $booking) {
            $this->purgeOwner($booking);
        }

        $producer = $user->userable;
        if ($producer instanceof Producer) {
            foreach ($producer->missions()->get() as $mission) {
                /** @var Mission $mission */
                $this->purgeForMission($mission);
            }
        }
    }

    /**
     * Supprime fichiers + rows de tous les médias directement portés par un
     * owner : ses product_photos (Booking privé | Mission public | Shipment
     * réception), ses deliverables (Booking | Candidature) et — pour un Booking |
     * Candidature — son shipment.
     *
     * Les photos de réception vivent sur le Shipment (owner distinct du
     * Booking/Candidature), donc la récursion sur le shipment les purge sans
     * jamais recouvrir les product_photos du Booking : aucun double-nettoyage.
     * Idempotent : un owner sans média est un no-op.
     */
    private function purgeOwner(Booking|Candidature|Shipment|Mission $owner): void
    {
        // product_photos : Booking (privé), Mission (public) et Shipment (photos
        // de réception, privé) portent la relation ; une Candidature n'en porte pas.
        if (! $owner instanceof Candidature) {
            foreach ($owner->productPhotos()->get() as $photo) {
                /** @var ProductPhoto $photo */
                $this->imageVariantGenerator->deleteFiles($photo);
                $photo->delete();
            }
        }

        // deliverables + shipment : portés par Booking | Candidature.
        if ($owner instanceof Booking || $owner instanceof Candidature) {
            foreach ($owner->deliverables()->get() as $deliverable) {
                $this->deliverableService->deleteFiles($deliverable);
                $deliverable->delete();
            }

            // Le Shipment n'a pas de fichier propre : on purge d'abord ses photos
            // de réception (récursion), puis la row.
            $shipment = $owner->shipment;
            if ($shipment !== null) {
                $this->purgeOwner($shipment);
                $shipment->delete();
            }
        }
    }
}

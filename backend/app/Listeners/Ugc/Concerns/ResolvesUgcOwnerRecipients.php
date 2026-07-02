<?php

declare(strict_types=1);

namespace App\Listeners\Ugc\Concerns;

use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;

/**
 * Résolution owner→destinataire pour les emails du tunnel UGC (ugc-7-1, D-7.1.b).
 *
 * Les 6 events du tunnel portent un Shipment/Deliverable dont l'`owner()` est
 * polymorphe (Booking | Candidature). L'`email` vit TOUJOURS sur le `User`
 * (jamais sur Face/Producer). La jointure vers le User diffère selon le type
 * d'owner (« piège FK n°1 / 2.4 ») :
 *   - Booking     : face_id / producer_id = users.id   → $owner->face/producer->email (direct)
 *   - Candidature : candidature.face_id = faces.id, mission.producer_id = producers.id
 *                   → hop via userable_type/userable_id sur User
 *
 * Miroir EXACT de la résolution des 6 listeners in-app `Notify*` (qui dérivent
 * le `users.id` du destinataire), en renvoyant l'`email` (->value('email')) au
 * lieu de l'`id`. Consommé UNIQUEMENT par les 6 nouveaux listeners email — les
 * 6 in-app restent strictement intouchés (additif, D-7.1.a).
 *
 * Garde mission-null UNIFORME (D-7.1.b) : pour une candidature à `mission` null,
 * `faceEmailFor` ET `producerEmailFor` renvoient '' ⇒ le listener saute via sa
 * garde email-vide (un email sans nom produit/URL n'a pas de sens). Conséquence :
 * les helpers d'affichage ne s'exécutent qu'avec `mission` non-null (candidature)
 * ⇒ déréf de `$owner->mission->…` sûr, jamais de throw ⇒ tests déterministes.
 */
trait ResolvesUgcOwnerRecipients
{
    /**
     * Charge et NARROW l'owner polymorphe d'un Shipment/Deliverable vers le type
     * exact attendu par les helpers ci-dessous. Un owner d'un autre type (jamais
     * en pratique) ⇒ null ⇒ le listener saute via la garde email-vide.
     */
    protected function ugcOwnerFrom(Shipment|Deliverable $carrier): Booking|Candidature|null
    {
        $carrier->loadMissing('owner');
        $owner = $carrier->owner;

        return $owner instanceof Booking || $owner instanceof Candidature ? $owner : null;
    }

    protected function faceEmailFor(Booking|Candidature|null $owner): string
    {
        if ($owner instanceof Booking) {
            $owner->loadMissing('face');

            return trim((string) $owner->face?->email); // face_id = users.id
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission');

            if ($owner->mission === null) {
                return ''; // pas de mission ⇒ contenu email non constructible ⇒ skip (miroir in-app Face)
            }

            return trim((string) User::query()
                ->where('userable_type', Face::class)
                ->where('userable_id', $owner->face_id) // candidature.face_id = faces.id
                ->value('email'));
        }

        return '';
    }

    protected function producerEmailFor(Booking|Candidature|null $owner): string
    {
        if ($owner instanceof Booking) {
            $owner->loadMissing('producer');

            return trim((string) $owner->producer?->email); // producer_id = users.id
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission');

            if ($owner->mission === null) {
                return '';
            }

            return trim((string) User::query()
                ->where('userable_type', Producer::class)
                ->where('userable_id', $owner->mission->producer_id) // mission.producer_id = producers.id
                ->value('email'));
        }

        return '';
    }

    /**
     * Nom du produit. Appelé post-garde email-vide ⇒ pour une candidature, `mission`
     * est déjà non-null (faceEmailFor/producerEmailFor ont renvoyé '' sinon).
     */
    protected function productNameFor(Booking|Candidature|null $owner): string
    {
        if ($owner instanceof Booking) {
            return (string) $owner->nom_produit;
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission');
            $mission = $owner->mission;

            return $mission !== null ? (string) $mission->nom_produit : '';
        }

        return '';
    }

    protected function producerNameFor(Booking|Candidature|null $owner): string
    {
        if ($owner instanceof Booking) {
            $owner->loadMissing('producer.userable');

            return (string) data_get($owner, 'producer.userable.display_name', ''); // User→userable→Producer
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission.producer');

            return (string) data_get($owner, 'mission.producer.display_name', ''); // Mission→producer (belongsTo Producer)
        }

        return '';
    }

    protected function faceNameFor(Booking|Candidature|null $owner): string
    {
        if ($owner instanceof Booking) {
            $owner->loadMissing('face.userable');

            return (string) data_get($owner, 'face.userable.display_name', ''); // User→userable→Face
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('face');

            return (string) data_get($owner, 'face.display_name', ''); // candidature.face = belongsTo(Face)
        }

        return '';
    }

    /**
     * URL CTA = deep-link du deal, qui dépend du RÔLE destinataire (pas dérivable
     * de $owner seul) ⇒ 2 helpers. Mapping repris des `$url` in-app jumeaux, préfixé
     * par frontend_url (le in-app stocke des URL relatives ; l'email a besoin de l'absolu).
     */
    protected function faceDealUrlFor(Booking|Candidature|null $owner): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        if ($owner instanceof Booking) {
            return $base.'/face/bookings/'.$owner->uuid;
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission');
            $mission = $owner->mission;

            return $base.'/face/missions/'.($mission !== null ? $mission->uuid : '');
        }

        return $base;
    }

    protected function producerDealUrlFor(Booking|Candidature|null $owner): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        if ($owner instanceof Booking) {
            return $base.'/producer/bookings/'.$owner->uuid;
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission');
            $mission = $owner->mission;

            return $base.'/producer/missions/'.($mission !== null ? $mission->uuid : '').'/candidatures';
        }

        return $base;
    }
}

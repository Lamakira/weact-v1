/**
 * Shared UGC commission helpers (front mirror of `backend/config/ugc.php`
 * + `App\Services\Ugc\UgcCommissionService::compute`).
 *
 * These constants/helpers are display-only: the persisted commission is ALWAYS
 * recomputed server-side. The front never sends `commission_ugc`.
 */

import { z } from 'zod'

export const UGC_COMMISSION_RATE = 0.1
export const UGC_COMMISSION_FLOOR = 2500
export const UGC_PRODUCT_ONLY_VIDEO_COUNT = 2

/** Miroir de backend/config/ugc.php `deliverable_days.unboxing` — copy statique
 *  uniquement (« 7 jours ») : la deadline affichée vient TOUJOURS du serveur
 *  (`unboxing_deadline_at`), jamais d'un calcul front (NFR3, D-3.4.i). */
export const UGC_UNBOXING_DAYS = 7

export type UgcCompensationType = 'product' | 'hybrid'

/** Commission WeAct (live preview ; le serveur reste autoritatif). */
export function computeUgcCommission(productValue: number): number {
  return Math.max(UGC_COMMISSION_FLOOR, Math.round((productValue || 0) * UGC_COMMISSION_RATE))
}

/** Kinds de la primitive StatusPill (UX-DR3) — états du tunnel booking UGC. */
export type StatusPillKind =
  | 'pending'
  | 'paid'
  | 'accepted'
  | 'shipped'
  | 'received'
  | 'delivered'
  | 'completed'
  | 'overdue'
  | 'suspended'

// ---------- Shipment (tunnel UGC étape 3+, story 3.2) ----------

/**
 * Expédition d'un deal UGC — miroir de ShipmentResource (3.1).
 * `tunnel_status` est volontairement un `string` OUVERT (pas d'union fermée) :
 * l'enum backend UgcTunnelStatus a des cases réservés (épics 4-5) qui doivent
 * arriver sans redéploiement front (règle fan-out 3.1).
 */
export interface Shipment {
  id: string
  transporteur: string
  numero_suivi: string
  note_envoi: string | null
  tunnel_status: string
  tunnel_status_label: string
  shipped_at: string
  recu_le: string | null
  /** Échéance Unboxing dérivée serveur (recu_le + config) — null avant réception (3.3 AC7). */
  unboxing_deadline_at: string | null
  destinataire: {
    nom: string
    ville: string | null
    pays: string | null
  }
  created_at: string
}

export interface ConfirmShipmentPayload {
  transporteur: string
  numero_suivi: string
  note_envoi?: string
}

export interface ShipmentResponse {
  data: Shipment
  message?: string
}

/** Chips transporteur de l'écran 4A — sucre UI (D-3.1.e), le serveur reçoit du texte libre. */
export const UGC_CARRIER_CHIPS = ['DHL', 'Chronopost', 'Gozem', 'Autre'] as const

/** Miroir client de ConfirmShipmentRequest (3.1) — le serveur reste autoritatif. */
export const ugcShipmentSchema = z.object({
  transporteur: z
    .string()
    .trim()
    .min(1, 'Le transporteur est obligatoire')
    .max(100, 'Le transporteur ne peut pas dépasser 100 caractères'),
  numero_suivi: z
    .string()
    .trim()
    .min(1, 'Le numéro de suivi est obligatoire')
    .max(100, 'Le numéro de suivi ne peut pas dépasser 100 caractères'),
  note_envoi: z
    .string()
    .trim()
    .max(500, 'La note ne peut pas dépasser 500 caractères')
    .optional(),
})

/**
 * Couleur de pastille par statut tunnel. Default OBLIGATOIRE : les cases
 * réservés (épics 4-5) doivent rendre un état neutre, jamais throw.
 * Le label affiché vient TOUJOURS de tunnel_status_label (serveur).
 */
export function tunnelStatusToPillKind(status: string): StatusPillKind {
  switch (status) {
    case 'shipped':
      return 'shipped'
    case 'received':
    case 'unboxing_in_review':
    case 'avis_in_review':
      return 'received'
    case 'completed':
      return 'completed'
    case 'overdue':
      return 'overdue'
    case 'suspended':
      return 'suspended'
    default:
      return 'pending'
  }
}

/**
 * Branche shipment commune aux deux helpers de step (une seule table de
 * mapping). Inconnu → 4 (post-expédition, jamais < 4) — cases réservés 4-5.
 */
function shipmentTunnelStep(shipment: Shipment): number {
  switch (shipment.tunnel_status) {
    case 'shipped':
      return 4
    case 'received':
    case 'unboxing_in_review':
      return 5
    case 'avis_in_review':
      return 6
    case 'completed':
      return 7
    default:
      return 4
  }
}

/**
 * Étape courante (1-6) de la timeline UGC ; 7 = tunnel terminé, 0 = neutre
 * (deal mort : refusé/expiré/annulé — la carte « raison » couvre déjà).
 * L'amont se lit sur le statut booking, l'aval sur shipment.tunnel_status
 * (D-3.1.a/D-3.1.c). Defaults obligatoires (cases réservés).
 */
export function ugcTunnelStep(bookingStatus: string, shipment?: Shipment | null): number {
  if (shipment) {
    return shipmentTunnelStep(shipment)
  }

  switch (bookingStatus) {
    case 'pending':
      return 1
    case 'commission_paid':
      return 2
    case 'accepted':
      return 3
    case 'completed':
      return 7
    default:
      return 0
  }
}

/**
 * Étape timeline UGC d'une CANDIDATURE (mission UGC, story 3.4). L'amont se lit
 * sur le statut candidature (la commission mission est payée AU PUBLISH → une
 * candidature vivante est au moins à l'étape 2), l'aval sur le shipment.
 * 0 = neutre (rejetée/annulée/inconnue). Ne PAS détourner ugcTunnelStep pour
 * une candidature (statuts amont différents — note 3.2 reconduite).
 */
export function ugcCandidatureTunnelStep(candidatureStatus: string, shipment?: Shipment | null): number {
  if (shipment) {
    return shipmentTunnelStep(shipment)
  }

  switch (candidatureStatus) {
    case 'pending':
    case 'accepted':
      return 2
    case 'confirmed':
    case 'in_progress':
      return 3
    case 'completed':
      return 7
    default:
      return 0
  }
}

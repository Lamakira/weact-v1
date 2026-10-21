import { formatMoney } from '@/utils/money'
import type { FaceCandidature } from '../types'

/**
 * Présentation d'une carte de « Mes candidatures » du point de vue de la Face (lot F4) :
 * libellé de statut, faits en libellé / valeur et prochaine étape. Fonctions pures, sans champ vide :
 * une ligne sans valeur n'est pas rendue.
 */

export interface CandidatureFact {
  key: string
  label: string
  value: string
}

/** Discriminant UGC côté Face : `type_compensation` renseigné (cf. isUgcMission). */
export function isUgcCandidature(candidature: FaceCandidature): boolean {
  return candidature.mission.type_compensation != null
}

/**
 * Libellé de statut vu par la Face : « À confirmer par vous » quand le Producteur l'a retenue,
 * « Non retenue » quand il ne l'a pas choisie. Les autres libellés viennent du serveur.
 */
export function candidatureFaceStatusLabel(candidature: FaceCandidature): string {
  const serverLabel = candidature.display_status_label ?? candidature.status_label
  const display = candidature.display_status ?? candidature.status

  if (display === 'accepted') return 'À confirmer par vous'
  if (display === 'rejected') return 'Non retenue'
  return serverLabel
}

/** « sam. 5 déc. » */
function formatShootDate(value: string | null): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }).format(date)
}

/** « UGC · 2 vidéos » (« UGC » seul quand le nombre de vidéos est inconnu). */
function ugcTypeText(candidature: FaceCandidature): string {
  const videos = candidature.mission.nombre_videos
  return videos ? `UGC · ${videos} vidéo${videos > 1 ? 's' : ''}` : 'UGC'
}

/** « Produit : crème Jolyderm + 25 000 F CFA » ; null si rien n'est connu. */
function ugcReceivesText(candidature: FaceCandidature): string | null {
  const { nom_produit: product, montant_remuneration: cash } = candidature.mission
  const parts: string[] = []
  if (product) parts.push(`Produit : ${product}`)
  if (cash && cash > 0) parts.push(formatMoney(cash))
  return parts.length > 0 ? parts.join(' + ') : null
}

/** Ce qui vient ensuite pour la Face ; null quand plus rien n'est attendu (terminée, refusée, annulée, expirée). */
export function candidatureNextStep(candidature: FaceCandidature): string | null {
  const display = candidature.display_status ?? candidature.status
  const ugc = isUgcCandidature(candidature)

  switch (display) {
    case 'selection_payment_pending':
      return 'Paiement de la sélection par le Producteur'
    case 'pending':
      return 'Réponse du Producteur'
    case 'accepted':
      return ugc ? 'Reconfirmer votre participation' : 'Confirmer votre participation'
    case 'confirmed':
    case 'in_progress': {
      if (!ugc) return display === 'confirmed' ? 'Tournage' : 'Fin du tournage'
      switch (candidature.shipment?.tunnel_status) {
        case undefined:
          return 'Expédition du produit'
        case 'shipped':
          return 'Réception du produit'
        case 'received':
        case 'avis_pending':
          return 'Envoi de vos vidéos'
        case 'unboxing_in_review':
        case 'avis_in_review':
          return 'Validation par le Producteur'
        default:
          return null
      }
    }
    default:
      return null
  }
}

/**
 * Faits de la carte, en libellé / valeur : UGC (Producteur, Type, Vous recevez, Prochaine étape)
 * ou standard (Producteur, Tournage, Lieu, Rémunération).
 */
export function candidatureFacts(candidature: FaceCandidature): CandidatureFact[] {
  const facts: CandidatureFact[] = [{ key: 'producer', label: 'Producteur', value: candidature.producer.display_name }]

  if (isUgcCandidature(candidature)) {
    facts.push({ key: 'type', label: 'Type', value: ugcTypeText(candidature) })
    const receives = ugcReceivesText(candidature)
    if (receives) facts.push({ key: 'receives', label: 'Vous recevez', value: receives })
    const next = candidatureNextStep(candidature)
    if (next) facts.push({ key: 'next', label: 'Prochaine étape', value: next })
    return facts
  }

  const shoot = formatShootDate(candidature.mission.date_tournage)
  if (shoot) facts.push({ key: 'shoot', label: 'Tournage', value: shoot })
  if (candidature.mission.lieu) facts.push({ key: 'place', label: 'Lieu', value: candidature.mission.lieu })
  facts.push({ key: 'pay', label: 'Rémunération', value: formatMoney(candidature.mission.budget) })
  return facts
}

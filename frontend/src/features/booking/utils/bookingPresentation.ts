import { BookingStatus, type Booking, type BookingFaceUserable, type BookingProducerUserable } from '../types'
import { BOOKING_BUSINESS_TIMEZONE, formatBookingDeadline } from './formatBookingDeadline'

/**
 * Présentation de la page booking selon le statut ET le rôle de celui qui regarde
 * (lot C6) : titre, pastille, étapes et bloc « Et ensuite ? ». Fonctions pures :
 * toute la copie vit ici, la page ne fait que l'afficher.
 */

export type BookingRole = 'producer' | 'face'
export type PillTone = 'info' | 'warn' | 'ok' | 'mute' | 'danger'

export interface BookingPill {
  tone: PillTone
  label: string
}

export interface BookingNext {
  headline: string
  text: string
  /** Qui doit agir (ou ce qui vient ensuite). */
  who: string
  /** Échéance ISO à décompter dans la puce, si le backend en fournit une. */
  deadlineAt: string | null
}

export interface BookingSteps {
  labels: string[]
  /** Index de l'étape courante (0 = Demande). */
  current: number
  /** Le parcours s'est arrêté ici (refus, expiration, annulation, absence). */
  halted: boolean
}

export interface BookingView {
  title: string
  pill: BookingPill
  next: BookingNext
  steps: BookingSteps
}

const CASH_STEPS = ['Demande', 'Acceptation', 'Paiement', 'Tournage', 'Terminé']
const UGC_STEPS = ['Demande', 'Commission', 'Acceptation', 'Livraison', 'Terminé']

const YOU_ACT = "À vous d'agir"
const FACE_ACTS = 'La Face doit agir'
const PRODUCER_ACTS = 'Le Producteur doit agir'

const DEAD_STATUSES: string[] = [
  BookingStatus.REFUSED,
  BookingStatus.EXPIRED,
  BookingStatus.CANCELLED_BY_PRODUCER,
  BookingStatus.CANCELLED_BY_FACE,
  BookingStatus.NO_SHOW,
]

export function formatFcfa(amount: number | null | undefined): string {
  return `${new Intl.NumberFormat('fr-FR').format(amount ?? 0)} FCFA`
}

/** « jeu. 23 oct. » dans le fuseau métier. */
export function formatShortDate(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    timeZone: BOOKING_BUSINESS_TIMEZONE,
  }).format(new Date(iso))
}

/** « Reste 1 j 2 h », « Reste 3 h 40 », « Reste 42 min » ; null une fois l'échéance passée. */
export function formatRemaining(deadlineIso: string, now: number): string | null {
  const ms = new Date(deadlineIso).getTime() - now
  if (!Number.isFinite(ms) || ms <= 0) return null

  const totalMinutes = Math.floor(ms / 60000)
  const days = Math.floor(totalMinutes / 1440)
  const hours = Math.floor((totalMinutes % 1440) / 60)
  const minutes = totalMinutes % 60

  if (days >= 1) return `Reste ${days} j ${hours} h`
  if (hours >= 1) return `Reste ${hours} h ${String(minutes).padStart(2, '0')}`
  return `Reste ${Math.max(minutes, 1)} min`
}

function cap(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

export function faceFirstName(booking: Booking): string {
  const userable = booking.face?.userable as BookingFaceUserable | undefined
  return userable?.prenom?.trim() || 'cette Face'
}

export function faceFullName(booking: Booking): string {
  const userable = booking.face?.userable as BookingFaceUserable | undefined
  const full = `${userable?.prenom ?? ''} ${userable?.nom ?? ''}`.trim()
  return full || userable?.username || 'Face'
}

export function producerName(booking: Booking): string {
  const userable = booking.producer?.userable as BookingProducerUserable | undefined
  return userable?.display_name?.trim() || 'ce Producteur'
}

/** Nom court de « l'autre partie » tel qu'il apparaît dans les titres. */
export function otherShortName(booking: Booking, role: BookingRole): string {
  return role === 'producer' ? faceFirstName(booking) : producerName(booking)
}

function isUgc(booking: Booking): boolean {
  return booking.type_contenu === 'UGC'
}

function stepsFor(booking: Booking): BookingSteps {
  const ugc = isUgc(booking)
  const labels = ugc ? UGC_STEPS : CASH_STEPS
  const status = booking.status

  if (DEAD_STATUSES.includes(status)) {
    const accepted = booking.accepted_at !== null
    let current = 1
    if (status === BookingStatus.NO_SHOW) {
      current = 3
    } else if (status === BookingStatus.EXPIRED) {
      current = !ugc && accepted ? 2 : 1
    } else if (status === BookingStatus.CANCELLED_BY_PRODUCER || status === BookingStatus.CANCELLED_BY_FACE) {
      current = ugc ? (accepted ? 3 : 1) : booking.was_paid ? 3 : accepted ? 2 : 1
    }
    return { labels, current, halted: true }
  }

  let current: number
  switch (status) {
    case BookingStatus.PENDING:
      current = 1
      break
    case BookingStatus.COMMISSION_PAID:
      current = 2
      break
    case BookingStatus.ACCEPTED:
      current = ugc ? 3 : 2
      break
    case BookingStatus.COMPLETED:
      current = 4
      break
    default:
      // paid, in_progress, confirmed_by_face, confirmed_by_producer
      current = 3
  }

  return { labels, current, halted: false }
}

function titleFor(booking: Booking, role: BookingRole): string {
  const other = otherShortName(booking, role)
  const ugc = isUgc(booking)
  const producer = role === 'producer'

  switch (booking.status) {
    case BookingStatus.PENDING:
      if (producer) return ugc ? `Demande à ${other} : commission à régler` : `Demande envoyée à ${other}`
      return `Nouvelle demande de ${other}`
    case BookingStatus.COMMISSION_PAID:
      return producer ? `Commission réglée : en attente de ${other}` : `Nouvelle demande de ${other}`
    case BookingStatus.ACCEPTED:
      if (ugc) {
        if (producer) return booking.shipment ? `Produit en route vers ${other}` : `${cap(other)} a accepté : expédiez le produit`
        return booking.shipment ? 'Booking accepté : produit expédié' : "Booking accepté : en attente de l'expédition"
      }
      if (producer) {
        const until = booking.payment_deadline
          ? new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: BOOKING_BUSINESS_TIMEZONE })
              .format(new Date(booking.payment_deadline))
          : null
        return until ? `${cap(other)} a accepté : payez avant ${until}` : `${cap(other)} a accepté : payez pour confirmer`
      }
      return 'Booking accepté : en attente du paiement'
    case BookingStatus.PAID:
    case BookingStatus.IN_PROGRESS:
    case BookingStatus.CONFIRMED_BY_FACE:
    case BookingStatus.CONFIRMED_BY_PRODUCER:
      return `Booking confirmé avec ${other}`
    case BookingStatus.COMPLETED:
      return `Booking terminé avec ${other}`
    case BookingStatus.REFUSED:
      return producer ? `${cap(other)} a refusé la demande` : 'Demande refusée'
    case BookingStatus.EXPIRED:
      if (ugc) return 'Demande expirée'
      if (booking.accepted_at !== null) {
        return producer ? 'Demande expirée : paiement non effectué' : 'Demande expirée : paiement non reçu'
      }
      return producer ? `Demande expirée sans réponse de ${other}` : 'Demande expirée sans réponse'
    case BookingStatus.CANCELLED_BY_PRODUCER:
      return producer ? 'Booking annulé' : `Booking annulé par ${other}`
    case BookingStatus.CANCELLED_BY_FACE:
      return producer ? `${cap(other)} a annulé le booking` : 'Vous avez annulé le booking'
    case BookingStatus.NO_SHOW:
      return producer ? `Absence de ${other} signalée` : 'Votre absence a été signalée'
    default:
      return 'Booking'
  }
}

function pillFor(booking: Booking, role: BookingRole): BookingPill {
  const producer = role === 'producer'
  const ugc = isUgc(booking)

  switch (booking.status) {
    case BookingStatus.PENDING:
      if (producer) return { tone: 'warn', label: ugc ? 'Commission à régler' : 'En attente de réponse' }
      return { tone: 'warn', label: 'Réponse attendue' }
    case BookingStatus.COMMISSION_PAID:
      return producer ? { tone: 'info', label: 'Commission réglée' } : { tone: 'warn', label: 'Réponse attendue' }
    case BookingStatus.ACCEPTED:
      if (ugc) return { tone: 'info', label: 'Livraison en cours' }
      return producer ? { tone: 'warn', label: 'À payer' } : { tone: 'warn', label: 'Paiement attendu' }
    case BookingStatus.PAID:
    case BookingStatus.IN_PROGRESS:
      return { tone: 'ok', label: 'Paiement sécurisé' }
    case BookingStatus.CONFIRMED_BY_FACE:
      return producer ? { tone: 'warn', label: 'Confirmation attendue' } : { tone: 'ok', label: 'Confirmé par vous' }
    case BookingStatus.CONFIRMED_BY_PRODUCER:
      return producer ? { tone: 'ok', label: 'Confirmé par vous' } : { tone: 'warn', label: 'Confirmation attendue' }
    case BookingStatus.COMPLETED:
      return { tone: 'ok', label: 'Terminé' }
    case BookingStatus.REFUSED:
      return { tone: 'danger', label: 'Refusée' }
    case BookingStatus.EXPIRED:
      return { tone: 'mute', label: 'Expirée' }
    case BookingStatus.CANCELLED_BY_PRODUCER:
    case BookingStatus.CANCELLED_BY_FACE:
      return { tone: 'danger', label: 'Annulée' }
    case BookingStatus.NO_SHOW:
      return { tone: 'danger', label: 'Absence signalée' }
    default:
      return { tone: 'mute', label: booking.status_label }
  }
}

function nextFor(booking: Booking, role: BookingRole): BookingNext {
  const other = otherShortName(booking, role)
  const producer = role === 'producer'
  const ugc = isUgc(booking)
  const none = (headline: string, text: string, who: string): BookingNext => ({ headline, text, who, deadlineAt: null })

  switch (booking.status) {
    case BookingStatus.PENDING: {
      if (ugc) {
        const deadlineAt = booking.ugc_commission_payment_deadline ?? null
        if (producer) {
          return {
            headline: 'Réglez la commission pour lancer la demande',
            text: "La Face pourra accepter dès que la commission est réglée. Sans règlement avant l'échéance, la demande expire.",
            who: YOU_ACT,
            deadlineAt,
          }
        }
        return {
          headline: 'Le Producteur doit d\'abord régler la commission',
          text: 'Vous pourrez accepter dès que la commission est réglée. Vous serez notifié dès que la demande est prête.',
          who: PRODUCER_ACTS,
          deadlineAt,
        }
      }
      const deadlineAt = booking.face_response_deadline ?? null
      const label = deadlineAt ? formatBookingDeadline(deadlineAt) : null
      if (producer) {
        return {
          headline: label ? `${cap(other)} a jusqu'au ${label} pour répondre` : `En attente de la réponse de ${other}`,
          text: "Si la Face accepte, vous aurez 24 h pour payer. La messagerie s'ouvre après le paiement.",
          who: FACE_ACTS,
          deadlineAt,
        }
      }
      return {
        headline: label ? `Répondez avant le ${label}` : 'Répondez à cette demande',
        text: "Après votre acceptation, le Producteur a 24 h pour payer. L'argent est bloqué en séquestre jusqu'au tournage.",
        who: YOU_ACT,
        deadlineAt,
      }
    }

    case BookingStatus.COMMISSION_PAID:
      if (producer) {
        return none(
          'Commission réglée : la Face doit répondre',
          'Vous serez notifié dès sa décision. Si la Face refuse, la commission vous est remboursée.',
          FACE_ACTS,
        )
      }
      return none(
        'Répondez à cette demande',
        'Si vous acceptez, vous vous engagez sur les délais de livraison et de dépôt des vidéos.',
        YOU_ACT,
      )

    case BookingStatus.ACCEPTED: {
      if (ugc) {
        if (producer) {
          return booking.shipment
            ? none(`Le colis est en route vers ${other}`, 'La Face confirmera la réception avec des photos, puis déposera ses vidéos.', FACE_ACTS)
            : none(
                "Expédiez le produit",
                'Renseignez le transporteur et le numéro de suivi : la Face pourra ainsi suivre son colis.',
                YOU_ACT,
              )
        }
        return booking.shipment
          ? none(
              'Confirmez la réception à l\'arrivée du colis',
              'Suivez la livraison et le dépôt de vos vidéos dans la carte de suivi ci-dessous.',
              YOU_ACT,
            )
          : none('Le Producteur prépare l\'envoi', 'Vous serez notifié dès l\'expédition du produit.', PRODUCER_ACTS)
      }
      const deadlineAt = booking.payment_deadline ?? null
      if (producer) {
        return {
          headline: 'Payez pour confirmer le booking',
          text: `Sans paiement avant l'échéance, la demande expire et ${other} redevient disponible sur ces dates. La messagerie s'ouvre dès le paiement.`,
          who: YOU_ACT,
          deadlineAt,
        }
      }
      return {
        headline: deadlineAt ? `Le Producteur doit payer avant le ${formatBookingDeadline(deadlineAt)}` : 'Le Producteur doit payer',
        text: "Vous serez notifié dès le paiement. La messagerie s'ouvrira à ce moment-là.",
        who: PRODUCER_ACTS,
        deadlineAt,
      }
    }

    case BookingStatus.PAID:
    case BookingStatus.IN_PROGRESS: {
      const deadlineAt = booking.auto_completion_at ?? null
      const auto = deadlineAt ? ` Sans action de votre part, la clôture est automatique le ${formatShortDate(deadlineAt)}.` : ''
      // Tournage commencé (jour J ou passé) : on ne promet plus un « rendez-vous », on demande la confirmation.
      const shootStarted = booking.date_debut ? new Date(booking.date_debut).getTime() <= Date.now() : false
      const headline = !booking.date_debut
        ? 'Booking confirmé'
        : shootStarted
          ? `Tournage du ${formatShortDate(booking.date_debut)}\u00a0: confirmez qu'il a eu lieu`
          : `Rendez-vous ${formatShortDate(booking.date_debut)}`
      const who = shootStarted ? YOU_ACT : 'Prochaine étape : le tournage'
      if (producer) {
        return {
          headline,
          text: shootStarted
            ? `Confirmez la réalisation : le paiement est alors versé à ${other}. En cas d'absence, signalez-la.${auto}`
            : `Après le tournage, confirmez qu'il a eu lieu : le paiement est alors versé à ${other}.${auto}`,
          who,
          deadlineAt,
        }
      }
      return {
        headline,
        text: shootStarted
          ? `Confirmez la réalisation pour recevoir ${formatFcfa(booking.montant_face_recoit)} sur votre portefeuille.${auto}`
          : `${formatFcfa(booking.montant_face_recoit)} sont bloqués en séquestre pour vous. Après le tournage, ils sont versés sur votre portefeuille.${auto}`,
        who,
        deadlineAt,
      }
    }

    case BookingStatus.CONFIRMED_BY_FACE: {
      const deadlineAt = booking.auto_completion_at ?? null
      if (producer) {
        return {
          headline: 'La Face a confirmé le tournage',
          text: 'Confirmez à votre tour pour verser le paiement, ou signalez une absence.',
          who: YOU_ACT,
          deadlineAt,
        }
      }
      return {
        headline: 'Vous avez confirmé le tournage',
        text: 'En attente de la confirmation du Producteur. Sans action de sa part, la clôture est automatique.',
        who: PRODUCER_ACTS,
        deadlineAt,
      }
    }

    case BookingStatus.CONFIRMED_BY_PRODUCER:
      if (producer) {
        return none(
          'Vous avez confirmé le tournage',
          'En attente de la confirmation de la Face. Le paiement lui est versé dès sa confirmation.',
          FACE_ACTS,
        )
      }
      return none('Le Producteur a confirmé le tournage', 'Confirmez à votre tour pour recevoir votre paiement.', YOU_ACT)

    case BookingStatus.COMPLETED:
      if (ugc) {
        return none('Deal terminé', 'Les vidéos ont été validées. Merci pour cette collaboration.', booking.can_rate ? 'Facultatif' : 'Terminé')
      }
      if (producer) {
        return none(
          "Merci, c'est terminé",
          `Le paiement a été versé à ${other}. Votre avis l'aide à décrocher d'autres bookings.`,
          booking.can_rate ? 'Facultatif' : 'Terminé',
        )
      }
      return none(
        `${formatFcfa(booking.montant_face_recoit)} versés sur votre portefeuille`,
        'Vous pouvez les retirer depuis votre portefeuille. Votre avis aide le Producteur à être reconnu.',
        booking.can_rate ? 'Facultatif' : 'Terminé',
      )

    case BookingStatus.REFUSED:
      return producer
        ? none(
            `${cap(other)} a refusé la demande`,
            ugc ? 'Si vous aviez réglé la commission, elle vous est remboursée. Vous pouvez contacter d\'autres Faces.' : "Aucun paiement n'a été prélevé. Vous pouvez contacter d'autres Faces.",
            'Demande close',
          )
        : none('Vous avez refusé cette demande', 'Rien d\'autre à faire.', 'Demande close')

    case BookingStatus.EXPIRED: {
      let reason: string
      if (ugc) reason = "La demande n'a pas abouti dans les délais."
      else if (booking.accepted_at !== null) reason = producer ? "Le paiement n'a pas été effectué à temps." : "Le Producteur n'a pas payé à temps."
      else reason = producer ? `${cap(other)} n'a pas répondu à temps.` : "Vous n'avez pas répondu à temps."
      return none('La demande a expiré', `${reason} Aucun montant n'a été engagé.`, 'Demande close')
    }

    case BookingStatus.CANCELLED_BY_PRODUCER:
      return none(
        producer ? 'Vous avez annulé ce booking' : 'Le Producteur a annulé ce booking',
        'Le motif et le règlement éventuel figurent ci-dessous.',
        'Booking clos',
      )

    case BookingStatus.CANCELLED_BY_FACE:
      return none(
        producer ? `${cap(other)} a annulé ce booking` : 'Vous avez annulé ce booking',
        'Le motif et le règlement éventuel figurent ci-dessous.',
        'Booking clos',
      )

    case BookingStatus.NO_SHOW:
      return none(
        producer ? `Vous avez signalé l'absence de ${other}` : 'Votre absence a été signalée',
        'Le règlement suit la procédure indiquée ci-dessous.',
        'Règlement en cours',
      )

    default:
      return none('Booking', '', '')
  }
}

export function buildBookingView(booking: Booking, role: BookingRole): BookingView {
  return {
    title: titleFor(booking, role),
    pill: pillFor(booking, role),
    next: nextFor(booking, role),
    steps: stepsFor(booking),
  }
}

/** Sous-titre court d'une ligne de la liste des bookings (carte). */
export function bookingListSubtitle(booking: Booking, role: BookingRole): string {
  const producer = role === 'producer'
  const ugc = isUgc(booking)

  switch (booking.status) {
    case BookingStatus.PENDING:
      if (ugc) return producer ? 'Commission à régler' : 'En attente de la commission'
      return producer ? 'En attente de sa réponse' : 'À vous de répondre'
    case BookingStatus.COMMISSION_PAID:
      return producer ? 'En attente de sa réponse' : 'À vous de répondre'
    case BookingStatus.ACCEPTED:
      if (ugc) return booking.shipment ? 'Produit expédié' : "En attente de l'expédition"
      return producer ? 'À payer' : 'En attente du paiement'
    case BookingStatus.PAID:
    case BookingStatus.IN_PROGRESS:
      return 'Confirmé, paiement sécurisé'
    case BookingStatus.CONFIRMED_BY_FACE:
      return producer ? 'À confirmer de votre côté' : 'Vous avez confirmé'
    case BookingStatus.CONFIRMED_BY_PRODUCER:
      return producer ? 'Vous avez confirmé' : 'À confirmer de votre côté'
    case BookingStatus.COMPLETED:
      return 'Terminé'
    case BookingStatus.REFUSED:
      return 'Demande refusée'
    case BookingStatus.EXPIRED:
      return 'Demande expirée'
    case BookingStatus.CANCELLED_BY_PRODUCER:
    case BookingStatus.CANCELLED_BY_FACE:
      return 'Booking annulé'
    case BookingStatus.NO_SHOW:
      return 'Absence signalée'
    default:
      return 'Booking'
  }
}

/** « jeu. 23 oct. à 14h00 » dans le fuseau métier (échéances des cartes de la liste). */
export function formatDeadlineLong(iso: string): string {
  const time = new Intl.DateTimeFormat('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: BOOKING_BUSINESS_TIMEZONE,
  })
    .format(new Date(iso))
    .replace(':', 'h')
  return `${formatShortDate(iso)} à ${time}`
}

/**
 * Action principale d'une ligne de la liste des bookings (lot D7) : ce que l'utilisateur doit
 * faire MAINTENANT, selon son rôle et le statut — miroir du filtre « À traiter » du backend
 * (Booking::toProcess). Elle MÈNE à la page du booking, où l'action se fait ; aucune mutation
 * depuis la liste. `deadline` : phrase d'échéance complète, quand le backend en fournit une.
 */
export interface BookingListAction {
  label: string
  deadline: string | null
}

export function bookingListAction(booking: Booking, role: BookingRole, now: number = Date.now()): BookingListAction | null {
  // Source unique : le serveur (Booking::toProcess) dit si une action est attendue (fenêtre encore ouverte…).
  if (booking.requires_my_action === false) return null
  const producer = role === 'producer'
  const ugc = isUgc(booking)
  const act = (label: string, deadlinePrefix?: string, deadlineIso?: string | null): BookingListAction => ({
    label,
    deadline: deadlinePrefix && deadlineIso ? `${deadlinePrefix} ${formatDeadlineLong(deadlineIso)}` : null,
  })
  const tunnel = booking.shipment?.tunnel_status
  const shootStarted = booking.date_debut ? new Date(booking.date_debut).getTime() <= now : false

  switch (booking.status) {
    case BookingStatus.PENDING:
      if (producer && ugc) {
        return act('Payer la commission', 'Payer la commission avant le', booking.ugc_commission_payment_deadline)
      }
      if (!producer && !ugc && booking.can_accept) {
        return act('Répondre', 'Répondre avant le', booking.face_response_deadline)
      }
      return null

    case BookingStatus.COMMISSION_PAID:
      return !producer && booking.can_accept ? act('Répondre') : null

    case BookingStatus.ACCEPTED:
      if (ugc) {
        if (producer) {
          if (!booking.shipment) return act("Confirmer l'expédition")
          return tunnel === 'unboxing_in_review' || tunnel === 'avis_in_review' ? act('Valider la vidéo') : null
        }
        if (tunnel === 'shipped') return act('Confirmer la réception')
        return tunnel === 'received' || tunnel === 'avis_pending' ? act('Envoyer la vidéo') : null
      }
      return producer && booking.can_pay ? act('Payer', 'Payer avant le', booking.payment_deadline) : null

    case BookingStatus.PAID:
      return shootStarted ? act('Confirmer la fin') : null

    case BookingStatus.CONFIRMED_BY_FACE:
      return producer ? act('Confirmer la fin') : null

    case BookingStatus.CONFIRMED_BY_PRODUCER:
      return producer ? null : act('Confirmer la fin')

    case BookingStatus.NO_SHOW:
    case BookingStatus.CANCELLED_BY_PRODUCER: {
      const due = booking.settlement_due_at
      const open = !!due && new Date(due).getTime() > now && !booking.disputed_at && !booking.dispute_resolved_at
      return !producer && open ? act('Contester', 'Contester avant le', due) : null
    }

    default:
      return null
  }
}

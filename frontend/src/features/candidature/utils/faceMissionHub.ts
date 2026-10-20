/**
 * Page mission Face « point central » (lot C5) : dérive, d'une candidature et de sa mission,
 * l'étape courante, le statut honnête, l'action principale unique et le bloc « Et ensuite ? ».
 * Fonction pure (aucun accès réseau/DOM) : testée en isolation.
 */
import type { Mission, MissionCandidature } from '@/features/mission/types'
import { formatConfirmationDeadline } from './formatConfirmationDeadline'

export const FACE_STANDARD_STEPS = ['Candidature', 'Sélection', 'Confirmation', 'Tournage', 'Payée'] as const
export const FACE_UGC_STEPS = ['Candidature', 'Acceptée', 'Reconfirmée', 'Livrables', 'Terminée'] as const

export type HubTone = 'info' | 'warn' | 'ok' | 'mute'

export type HubActionKey = 'confirm' | 'reconfirm' | 'chat' | 'cancel' | 'browse' | 'rate'

export interface HubAction {
  key: HubActionKey
  label: string
  /** Précision sous le libellé (échéance, interlocuteur). */
  sub: string
  /** `primary` = bouton plein ; `ghost` = action secondaire discrète. */
  kind: 'primary' | 'ghost'
}

export interface HubNext {
  headline: string
  text: string
  /** Qui doit agir (« À vous d'agir », « Le producteur doit agir »…). */
  who: string
  /** ISO 8601 de l'échéance qui s'applique, sinon null (pas de compte à rebours). */
  deadline: string | null
}

export interface FaceHubState {
  steps: readonly string[]
  /** Index de l'étape courante ; `steps.length` = tout est accompli. */
  currentStep: number
  /** Parcours interrompu (non retenue, expirée, annulée, clôturée) : l'étape courante n'est pas « en cours ». */
  stopped: boolean
  pill: { tone: HubTone; label: string }
  action: HubAction
  next: HubNext
  /** Le chat est ouvert pour cette candidature (statut + conversation existante). */
  chatOpen: boolean
}

const CHAT_STATUSES = ['accepted', 'confirmed', 'in_progress', 'completed']

function formatFcfa(amount: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(amount).replace(/[\u202f\u00a0]/g, ' ')} FCFA`
}

/** « jeu. 23 oct. » depuis un `YYYY-MM-DD` (interprété en date locale, sans décalage de fuseau). */
export function formatShortDate(value: string | null | undefined): string {
  if (!value) return ''
  const [y, m, d] = value.slice(0, 10).split('-').map(Number)
  if (!y || !m || !d) return ''
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }).format(
    new Date(y, m - 1, d),
  )
}

/** « Reste 1 j 16 h » / « Reste 3 h 40 » / « Reste 12 min » ; « Échéance dépassée » une fois passée. */
export function formatRemaining(deadlineIso: string, now: Date): string {
  const target = new Date(deadlineIso).getTime()
  if (isNaN(target)) return ''
  const diffMin = Math.floor((target - now.getTime()) / 60000)
  if (diffMin <= 0) return 'Échéance dépassée'
  const days = Math.floor(diffMin / 1440)
  const hours = Math.floor((diffMin % 1440) / 60)
  const minutes = diffMin % 60
  if (days > 0) return `Reste ${days} j ${hours} h`
  if (hours > 0) return `Reste ${hours} h ${String(minutes).padStart(2, '0')}`
  return `Reste ${minutes} min`
}

export function deriveFaceHubState(
  mission: Mission,
  candidature: MissionCandidature,
  options: { isUgc: boolean; producerName: string },
): FaceHubState {
  const { isUgc, producerName } = options
  const steps = isUgc ? FACE_UGC_STEPS : FACE_STANDARD_STEPS
  const status = candidature.status
  const display = candidature.display_status ?? status
  const chatOpen = CHAT_STATUSES.includes(status) && !!candidature.conversation_id
  const net = candidature.montant_face_recoit ?? null
  const shootDate = formatShortDate(mission.date_tournage)

  const chatAction: HubAction = {
    key: 'chat',
    label: 'Ouvrir le chat',
    sub: producerName ? `Producteur : ${producerName}` : '',
    kind: 'primary',
  }
  const browseAction: HubAction = { key: 'browse', label: 'Voir d’autres missions', sub: '', kind: 'ghost' }
  const cancelAction: HubAction = { key: 'cancel', label: 'Retirer ma candidature', sub: '', kind: 'ghost' }
  const closed = (headline: string, text: string): HubNext => ({ headline, text, who: 'Candidature close', deadline: null })

  // --- États « honnêtes » hors parcours nominal -------------------------------------------------
  if (status === 'rejected') {
    return {
      steps, currentStep: 1, stopped: true, chatOpen,
      pill: { tone: 'mute', label: 'Non retenue' },
      action: browseAction,
      next: closed(
        'Vous n’avez pas été retenue',
        'Le producteur a retenu d’autres profils pour cette mission. Votre profil reste visible : d’autres missions vous attendent.',
      ),
    }
  }

  if (status === 'expired') {
    const label = candidature.display_status_label || 'Expirée'
    // « Sans réponse du producteur » = jamais retenue ; « Expirée » = retenue mais non confirmée à temps.
    const neverSelected = label === 'Sans réponse du producteur'
    return {
      steps, currentStep: neverSelected ? 1 : 2, stopped: true, chatOpen,
      pill: { tone: 'mute', label },
      action: browseAction,
      next: neverSelected
        ? closed(
            'Le producteur n’a pas répondu',
            'La période de sélection est terminée sans réponse. Votre profil reste visible : d’autres missions vous attendent.',
          )
        : closed(
            'Vous n’avez pas confirmé à temps',
            'Votre place a été libérée et proposée à une autre Face. D’autres missions vous attendent.',
          ),
    }
  }

  if (status === 'cancelled') {
    return {
      steps, currentStep: 1, stopped: true, chatOpen,
      pill: { tone: 'mute', label: 'Annulée' },
      action: browseAction,
      next: closed('Candidature annulée', 'Cette candidature n’est plus active. D’autres missions vous attendent.'),
    }
  }

  if (display === 'mission_closed') {
    return {
      steps, currentStep: 1, stopped: true, chatOpen,
      pill: { tone: 'mute', label: 'Mission clôturée' },
      action: browseAction,
      next: closed(
        'Cette mission est clôturée',
        'Le producteur a clôturé la mission sans vous retenir. Votre profil reste visible : d’autres missions vous attendent.',
      ),
    }
  }

  // --- Parcours nominal --------------------------------------------------------------------------
  if (status === 'pending') {
    const paying = display === 'selection_payment_pending'
    return {
      steps, currentStep: 1, stopped: false, chatOpen,
      pill: paying
        ? { tone: 'warn', label: 'Sélection en cours de paiement' }
        : { tone: 'info', label: 'Candidature envoyée' },
      action: cancelAction,
      next: paying
        ? {
            headline: 'Le producteur finalise sa sélection',
            text: 'Vous serez prévenue par e-mail et notification si votre candidature est retenue. Rien à faire pour l’instant.',
            who: 'Le producteur doit agir',
            deadline: null,
          }
        : {
            headline: 'Le producteur étudie les candidatures',
            text: 'Vous serez prévenue par e-mail et notification si votre candidature est retenue. Réponse du producteur sous 72 h après la clôture des candidatures.',
            who: 'Le producteur doit agir',
            deadline: null,
          },
    }
  }

  if (status === 'accepted') {
    if (isUgc) {
      const deadline = candidature.reconfirm_deadline ?? null
      return {
        steps, currentStep: 2, stopped: false, chatOpen,
        pill: { tone: 'warn', label: 'À reconfirmer' },
        action: {
          key: 'reconfirm',
          label: 'Reconfirmer ma participation',
          sub: deadline ? `Avant le ${formatConfirmationDeadline(deadline)}` : '',
          kind: 'primary',
        },
        next: {
          headline: 'Votre candidature est acceptée',
          text: 'Reconfirmez votre participation : le producteur expédiera alors votre produit. Sans réponse avant l’échéance, la place est proposée à une autre Face.',
          who: 'À vous d’agir',
          deadline,
        },
      }
    }
    const deadline = candidature.confirmation_deadline ?? null
    return {
      steps, currentStep: 2, stopped: false, chatOpen,
      pill: { tone: 'warn', label: 'À confirmer' },
      action: {
        key: 'confirm',
        label: 'Confirmer ma participation',
        sub: deadline ? `Avant le ${formatConfirmationDeadline(deadline)}` : '',
        kind: 'primary',
      },
      next: {
        headline: 'Vous êtes retenue',
        text: `Le producteur a payé votre place${net !== null ? ` : ${formatFcfa(net)} vous sont réservés` : ''}. Confirmez avant l’échéance, sinon la place est proposée à une autre Face.`,
        who: 'À vous d’agir',
        deadline,
      },
    }
  }

  if (status === 'confirmed' || status === 'in_progress') {
    const pill = { tone: 'ok' as const, label: status === 'confirmed' ? 'Confirmée' : 'En cours' }
    if (isUgc) {
      const shipped = !!candidature.shipment
      return {
        steps, currentStep: 3, stopped: false, chatOpen, pill,
        action: chatOpen ? chatAction : browseAction,
        next: {
          headline: shipped ? 'Suivez votre livraison et vos vidéos' : 'Le producteur prépare l’envoi',
          text: shipped
            ? 'Confirmez la réception du produit, puis déposez vos vidéos dans les délais indiqués.'
            : 'Mission acceptée : le producteur va expédier votre produit. Les chronos démarreront à la réception.',
          who: shipped ? 'À vous d’agir' : 'Le producteur doit agir',
          deadline: null,
        },
      }
    }
    return {
      steps, currentStep: 3, stopped: false, chatOpen, pill,
      action: chatOpen ? chatAction : browseAction,
      next: {
        headline: shootDate ? `Rendez-vous le ${shootDate}` : 'Rendez-vous au tournage',
        text: `${mission.lieu ? `Tournage à ${mission.lieu}. ` : ''}Vous êtes payée dès que le producteur valide votre présence.`,
        who: 'Prochaine étape : le tournage',
        deadline: null,
      },
    }
  }

  if (status === 'completed') {
    // Notation du producteur (lot C8) : facultative, proposée seulement tant qu'elle n'a pas été donnée.
    const canRate = candidature.can_rate === true
    const paid = net !== null
      ? `${formatFcfa(net)} ont été versés sur votre portefeuille.`
      : 'Votre paiement a été versé sur votre portefeuille.'
    return {
      steps, currentStep: steps.length, stopped: false, chatOpen,
      pill: { tone: 'ok', label: isUgc ? 'Terminée' : 'Payée' },
      action: canRate
        ? { key: 'rate', label: 'Noter le producteur', sub: 'Facultatif', kind: 'primary' }
        : chatOpen ? { ...chatAction, kind: 'ghost' } : browseAction,
      next: {
        headline: 'Mission terminée',
        text: canRate
          ? `${paid} Noter le producteur est facultatif : votre avis aide les autres Faces à choisir leurs missions.`
          : paid,
        who: 'Terminé',
        deadline: null,
      },
    }
  }

  return {
    steps, currentStep: 0, stopped: true, chatOpen,
    pill: { tone: 'mute', label: candidature.display_status_label ?? candidature.status_label },
    action: browseAction,
    next: closed('Candidature', 'Retrouvez vos autres candidatures dans « Mes candidatures ».'),
  }
}

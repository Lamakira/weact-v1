import { formatMoney } from '@/utils/money'
import { getMissionActionState } from '../composables/missionActionState'
import type { Mission, MissionSummary, MissionSummaryStep } from '../types'

/**
 * Modèle de la page détail mission Producteur (lot C2) : étapes de la barre de
 * progression, action principale, bloc « Et ensuite ? » et carte Paiement. Fonctions
 * pures, alimentées par `GET /producer/missions/{id}` (`summary` du lot C1) : aucune
 * donnée n'est inventée côté client, et chaque action proposée existe côté backend.
 */

export interface StepDef {
  key: string
  label: string
}

const STANDARD_STEPS: StepDef[] = [
  { key: 'recruiting', label: 'Candidatures' },
  { key: 'payment', label: 'Paiement' },
  { key: 'confirmations', label: 'Confirmations' },
  { key: 'shooting', label: 'Tournage' },
  { key: 'completed', label: 'Terminée' },
]

const UGC_STEPS: StepDef[] = [
  { key: 'commission', label: 'Commission' },
  { key: 'recruiting', label: 'Candidatures' },
  { key: 'tunnel', label: 'Tunnel' },
  { key: 'completed', label: 'Terminée' },
]

export function isUgcMissionType(mission: Mission): boolean {
  // 'ugc' est hors de l'union TS `MissionTypeType` : comparaison volontairement en string.
  return (mission.type_mission as string) === 'ugc'
}

export function getMissionSteps(isUgc: boolean): StepDef[] {
  return isUgc ? UGC_STEPS : STANDARD_STEPS
}

/** Index de l'étape courante dans `getMissionSteps` ; -1 pour un brouillon (hors barre). */
export function getCurrentStepIndex(step: MissionSummaryStep, isUgc: boolean): number {
  if (step === 'draft') return -1
  if (isUgc) {
    return { commission: 0, recruiting: 1, tunnel: 2, completed: 3 }[step as string] ?? -1
  }
  return (
    { recruiting: 0, payment: 1, confirmations: 2, ready: 3, attendance: 3, completed: 4 }[step as string] ?? -1
  )
}

// ---------------------------------------------------------------------------
// Formats
// ---------------------------------------------------------------------------

function parseDate(iso: string | null | undefined): Date | null {
  if (!iso) return null
  const date = new Date(iso)
  return Number.isNaN(date.getTime()) ? null : date
}

/** « jeudi 17 octobre à 14h30 » (fuseau du navigateur). */
export function formatDeadlineLabel(iso: string | null | undefined): string | null {
  const date = parseDate(iso)
  if (!date) return null
  const day = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(date)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${day} à ${hours}h${minutes}`
}

/** « jeu. 23 oct. » */
export function formatShortDate(iso: string | null | undefined): string | null {
  const date = parseDate(iso)
  if (!date) return null
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }).format(date)
}

/** « Reste 14 h 22 », « Reste 1 j 16 h », « Reste 12 min » ; null sans échéance valide. */
export function formatCountdown(iso: string | null | undefined, now: Date): string | null {
  const target = parseDate(iso)
  if (!target) return null
  const totalMinutes = Math.floor((target.getTime() - now.getTime()) / 60_000)
  if (totalMinutes <= 0) return 'Échéance atteinte'
  const days = Math.floor(totalMinutes / 1440)
  const hours = Math.floor((totalMinutes % 1440) / 60)
  const minutes = totalMinutes % 60
  if (days > 0) return `Reste ${days} j ${hours} h`
  if (hours > 0) return `Reste ${hours} h ${String(minutes).padStart(2, '0')}`
  return `Reste ${minutes} min`
}

function plural(count: number, one: string, many: string): string {
  return count > 1 ? many : one
}

// ---------------------------------------------------------------------------
// Action principale
// ---------------------------------------------------------------------------

export type PrimaryActionKey =
  | 'select-faces'
  | 'resume-payment'
  | 'reconfirm-selection'
  | 'replace-freed-place'
  | 'validate-attendance'
  | 'pay-commission'
  | 'review-deliverables'
  | 'rate-faces'
  | 'republish'

export interface PrimaryAction {
  key: PrimaryActionKey
  label: string
  hint: string | null
}

export interface PaymentUiState {
  /** Un paiement en attente peut être repris (transaction vivante ou ligne relançable). */
  canResume: boolean
  /** Ligne de paiement sans transaction vivante : « Relancer » plutôt que « Reprendre ». */
  resumable: boolean
  /** Initialisation du paiement échouée (FIX-19.3) : la sélection se reconfirme. */
  initializationFailed: boolean
}

export interface DetailContext {
  mission: Mission
  summary: MissionSummary
  emailVerified: boolean
  payment: PaymentUiState
}

export function resolvePrimaryAction(ctx: DetailContext): PrimaryAction | null {
  const { mission, summary, payment } = ctx
  const isUgc = isUgcMissionType(mission)
  const state = getMissionActionState(mission, ctx.emailVerified)

  // Lot C8 : mission terminée → noter ses Faces (facultatif) tant qu'il en reste à noter.
  if (summary.step === 'completed') {
    const toRate = summary.faces_to_rate
    if (toRate <= 0) return null
    return {
      key: 'rate-faces',
      label: 'Noter mes Faces',
      hint: `${toRate} ${plural(toRate, 'Face à noter', 'Faces à noter')} · facultatif`,
    }
  }

  // Lot C9 : toutes les places remboursées → la seule suite utile est de republier la mission.
  if (summary.all_places_refunded) {
    return {
      key: 'republish',
      label: 'Republier la mission',
      hint: 'Mêmes informations, nouvelles dates',
    }
  }

  if (isUgc) {
    if (summary.step === 'commission' && state.canPayCommission) {
      const commission = mission.commission_ugc ?? 0
      return {
        key: 'pay-commission',
        label: 'Payer la commission',
        hint: commission > 0 ? formatMoney(commission) : null,
      }
    }
    if (summary.step === 'tunnel' && (summary.ugc_deliverables?.to_review ?? 0) > 0) {
      const count = summary.ugc_deliverables?.to_review ?? 0
      return { key: 'review-deliverables', label: 'Voir les livrables', hint: `${count} à valider` }
    }
    return null
  }

  switch (summary.step) {
    case 'recruiting': {
      if (mission.status !== 'published' || summary.candidatures.pending === 0) return null
      return {
        key: 'select-faces',
        label: 'Choisir mes Faces',
        hint: `Sélectionnez jusqu'à ${summary.selection.faces_wanted} ${plural(summary.selection.faces_wanted, 'Face', 'Faces')}`,
      }
    }
    case 'payment': {
      if (payment.canResume) {
        const total = summary.payment.montant_total_producteur
        return {
          key: 'resume-payment',
          label: payment.resumable ? 'Relancer le paiement' : 'Reprendre le paiement',
          hint: total !== null ? formatMoney(total) : null,
        }
      }
      if (payment.initializationFailed) {
        return { key: 'reconfirm-selection', label: 'Reconfirmer ma sélection', hint: null }
      }
      return null
    }
    case 'confirmations':
    case 'ready': {
      if (summary.selection.freed_places > 0 && mission.can_replace_freed_place === true) {
        return {
          key: 'replace-freed-place',
          label: summary.selection.freed_places > 1 ? 'Remplacer les places libérées' : 'Remplacer la place libérée',
          hint: null,
        }
      }
      return null
    }
    case 'attendance': {
      const attendance = summary.attendance
      if (attendance?.can_mark && attendance.pending > 0) {
        return {
          key: 'validate-attendance',
          label: 'Valider les présences',
          hint: `${attendance.pending} ${plural(attendance.pending, 'Face à pointer', 'Faces à pointer')}`,
        }
      }
      return null
    }
    default:
      return null
  }
}

// ---------------------------------------------------------------------------
// « Et ensuite ? »
// ---------------------------------------------------------------------------

export interface NextBlockContent {
  headline: string
  text: string
  /** Qui doit agir (« À vous d'agir », « Les Faces doivent agir »…). */
  who: string
  /** Échéance ISO alimentant le compte à rebours ; null sans échéance. */
  deadlineAt: string | null
  /** « avant le lundi 20 octobre à 16h05 » ; null sans échéance. */
  deadlineLabel: string | null
}

function block(
  headline: string,
  text: string,
  who: string,
  deadlineAt: string | null = null,
): NextBlockContent {
  const label = formatDeadlineLabel(deadlineAt)
  return { headline, text, who, deadlineAt: label ? deadlineAt : null, deadlineLabel: label ? `avant le ${label}` : null }
}

const WHO_YOU = 'À vous d\'agir'
const WHO_FACES = 'Les Faces doivent agir'

export function buildNextBlock(ctx: DetailContext): NextBlockContent {
  const { mission, summary } = ctx
  const isUgc = isUgcMissionType(mission)
  const { selection, candidatures } = summary
  const shootDate = formatShortDate(mission.date_tournage)

  if (summary.step === 'draft') {
    return block(
      'Brouillon',
      summary.can_edit
        ? 'Cette mission n\'est pas encore visible des Faces. Complétez-la depuis « Plus » puis « Modifier ».'
        : 'Cette mission n\'est pas encore visible des Faces.',
      WHO_YOU,
    )
  }

  if (summary.step === 'completed') {
    const toRate = summary.faces_to_rate
    if (toRate > 0) {
      return block(
        'Mission terminée',
        `La mission est terminée. Vous pouvez, si vous le souhaitez, noter ${toRate > 1 ? `vos ${toRate} Faces` : 'votre Face'} : c'est facultatif.`,
        'Rien d\'obligatoire',
      )
    }
    return block('Mission terminée', 'La mission est terminée. Les Faces présentes ont été payées.', 'Rien à faire')
  }

  if (summary.all_places_refunded) {
    return block(
      'Toutes les places ont été remboursées',
      'Aucune Face n\'a confirmé sa participation : le budget des places est de retour sur votre wallet. Republiez la mission avec de nouvelles dates pour retrouver des Faces ; l\'ancienne mission reste clôturée avec son historique de paiement.',
      WHO_YOU,
    )
  }

  if (isUgc) {
    if (summary.step === 'commission') {
      const commission = mission.commission_ugc ?? 0
      return block(
        'Réglez la commission pour publier',
        commission > 0
          ? `Votre mission sera visible des Faces dès le paiement de la commission (${formatMoney(commission)}). Elle n'est encaissée qu'après acceptation par la Face.`
          : 'Votre mission sera visible des Faces dès le paiement de la commission.',
        WHO_YOU,
      )
    }
    if (summary.step === 'recruiting') {
      const pending = candidatures.pending
      return pending > 0
        ? block(
            `${pending} ${plural(pending, 'candidature à examiner', 'candidatures à examiner')}`,
            'Acceptez les Faces qui vous conviennent depuis l\'onglet Candidatures.',
            WHO_YOU,
          )
        : block(
            'En attente de candidatures',
            'Les Faces peuvent postuler à votre mission.',
            'Les Faces peuvent postuler',
          )
    }
    // tunnel
    const deliverables = summary.ugc_deliverables
    const toReview = deliverables?.to_review ?? 0
    if (toReview > 0) {
      return block(
        `${toReview} ${plural(toReview, 'livrable à valider', 'livrables à valider')}`,
        'Validez la vidéo, demandez une retouche ou rejetez-la : la Face attend votre décision.',
        WHO_YOU,
      )
    }
    const validated = deliverables?.validated ?? 0
    return block(
      'Le tunnel est en cours',
      validated > 0
        ? `${validated} ${plural(validated, 'livrable validé', 'livrables validés')} pour l'instant. Les Faces reçoivent le produit puis déposent leurs vidéos.`
        : 'Les Faces reçoivent le produit puis déposent leurs vidéos. Suivez chacune d\'elles dans l\'onglet Candidatures.',
      WHO_FACES,
    )
  }

  switch (summary.step) {
    case 'recruiting': {
      if (mission.status === 'closed') {
        return block(
          'Candidatures clôturées',
          'Vous avez clôturé les candidatures. Rouvrez la mission depuis « Plus » pour en recevoir de nouvelles.',
          WHO_YOU,
        )
      }
      const limit = formatShortDate(mission.date_limite_candidature)
      const closing = limit ? ` Les candidatures ferment le ${limit}.` : ''
      return candidatures.pending > 0
        ? block(
            'Choisissez vos Faces',
            `${candidatures.pending} ${plural(candidatures.pending, 'candidature reçue', 'candidatures reçues')}. Sélectionnez jusqu'à ${selection.faces_wanted} ${plural(selection.faces_wanted, 'Face', 'Faces')} puis payez : les Faces ne sont prévenues qu'après le paiement.${closing}`,
            WHO_YOU,
          )
        : block(
            'En attente de candidatures',
            `Aucune candidature pour le moment.${closing || ' Les Faces peuvent postuler à votre mission.'}`,
            'Les Faces peuvent postuler',
          )
    }
    case 'payment':
      return block(
        'Payez votre sélection',
        `Vos ${selection.faces_selected} ${plural(selection.faces_selected, 'Face', 'Faces')} ne sont prévenues qu'après le paiement. Sans paiement avant l'échéance, la sélection est annulée et vous pouvez en refaire une.`,
        WHO_YOU,
        summary.payment.expires_at,
      )
    case 'confirmations': {
      if (selection.freed_places > 0) {
        const refund = formatDeadlineLabel(mission.freed_places_refund_at)
        return block(
          selection.freed_places > 1 ? `${selection.freed_places} places se sont libérées` : 'Une place s\'est libérée',
          `Donnez-la à une candidate non retenue, sans frais supplémentaires${refund ? `, avant le ${refund}` : ''}. Sans remplaçante confirmée à cette date, le budget de la place est crédité sur votre wallet.`,
          WHO_YOU,
          mission.freed_places_refund_at ?? null,
        )
      }
      return block(
        `${selection.faces_confirmed} ${plural(selection.faces_confirmed, 'Face', 'Faces')} sur ${selection.faces_selected} ${plural(selection.faces_confirmed, 'a confirmé', 'ont confirmé')}`,
        'Les Faces ont chacune un délai pour confirmer. Sans confirmation, la place se libère : vous pourrez la donner à une candidate non retenue ou être remboursé.',
        WHO_FACES,
      )
    }
    case 'ready': {
      if (selection.freed_places > 0) {
        return block(
          selection.freed_places > 1 ? `${selection.freed_places} places se sont libérées` : 'Une place s\'est libérée',
          'Donnez-la à une candidate non retenue, sans frais supplémentaires. Sans remplaçante confirmée avant le tournage, le budget de la place est crédité sur votre wallet.',
          WHO_YOU,
          mission.freed_places_refund_at ?? null,
        )
      }
      return block(
        shootDate ? `Rendez-vous le ${shootDate}` : 'Tout le monde a confirmé',
        `${selection.faces_confirmed} ${plural(selection.faces_confirmed, 'Face a confirmé', 'Faces ont confirmé')} leur participation.${mission.lieu ? ` Tournage : ${mission.lieu}.` : ''} Les présences se valident à partir du jour du tournage.`,
        'Prochaine étape : le tournage',
      )
    }
    case 'attendance': {
      const attendance = summary.attendance
      if (attendance?.can_mark && attendance.pending > 0) {
        return block(
          'Pointez les présences',
          'Chaque Face présente est payée à la validation. Une Face déclarée absente a 72 h pour contester ; sans contestation, sa place vous est remboursée.',
          WHO_YOU,
        )
      }
      return block(
        'Présences renseignées',
        summary.completion_blocker ?? 'Toutes les présences sont renseignées. Vous pouvez terminer la mission depuis « Plus ».',
        summary.completion_blocker ? 'En attente' : WHO_YOU,
      )
    }
    default:
      return block('Mission en cours', 'Suivez l\'avancement depuis les onglets de cette page.', 'Rien à faire')
  }
}

// ---------------------------------------------------------------------------
// Carte Paiement
// ---------------------------------------------------------------------------

export type Tone = 'info' | 'warn' | 'ok' | 'mute'

export interface PaymentCardRow {
  label: string
  value: string
}

export interface PaymentCardContent {
  rows: PaymentCardRow[]
  status: { label: string; tone: Tone }
}

export function buildPaymentCard(mission: Mission, summary: MissionSummary): PaymentCardContent {
  const isUgc = isUgcMissionType(mission)
  const rows: PaymentCardRow[] = []

  if (isUgc) {
    const commission = mission.commission_ugc ?? 0
    if (commission > 0) rows.push({ label: 'Commission', value: formatMoney(commission) })
    if ((mission.montant_remuneration ?? 0) > 0) {
      rows.push({ label: 'Cash par Face', value: formatMoney(mission.montant_remuneration ?? 0) })
    }
    if (summary.payment.montant_total_producteur !== null) {
      rows.push({ label: 'Total réglé', value: formatMoney(summary.payment.montant_total_producteur) })
    }
    if (mission.status === 'draft') return { rows, status: { label: 'Non publiée', tone: 'mute' } }
    return mission.commission_paid_at
      ? { rows, status: { label: 'Commission payée', tone: 'ok' } }
      : { rows, status: { label: 'Commission à régler', tone: 'warn' } }
  }

  rows.push({ label: 'Budget par Face', value: formatMoney(mission.budget) })
  rows.push({
    label: 'Faces sélectionnées',
    value: `${summary.selection.faces_selected} / ${summary.selection.faces_wanted}`,
  })

  const total = summary.payment.montant_total_producteur
  if (total !== null) {
    rows.push({ label: summary.payment.status === 'paid' ? 'Total payé' : 'Total à payer', value: formatMoney(total) })
  }

  switch (summary.payment.status) {
    case 'pending':
      return { rows, status: { label: 'En attente de paiement', tone: 'warn' } }
    case 'paid':
      return summary.step === 'completed'
        ? { rows, status: { label: 'Versé aux Faces', tone: 'ok' } }
        : { rows, status: { label: 'En séquestre', tone: 'info' } }
    case 'failed':
      return { rows, status: { label: 'Paiement échoué', tone: 'warn' } }
    case 'refunded':
      return { rows, status: { label: 'Remboursé', tone: 'mute' } }
    default:
      return { rows, status: { label: 'Après votre sélection', tone: 'mute' } }
  }
}

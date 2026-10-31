<script setup lang="ts">
import { formatMoney } from '@/utils/money'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Calendar,
  Clock,
  Film,
  Lock,
  MapPin,
  MessageSquare,
  Wallet,
  Loader2,
  AlertCircle,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Star,
  Package,
  Gift,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import PushMomentCard from '@/features/notification/components/PushMomentCard.vue'
import { useBookingDetail, useBookingActions } from '@/features/booking/composables'
import { useBookingRealtime } from '@/features/booking/composables/useBookingRealtime'
import {
  PaymentOverlay,
  BookingPricingBreakdown,
  BookingChat,
  CancellationDialog,
  BookingRatingForm,
  BookingSwap,
  BookingStepsBar,
  BookingNextBlock,
  BookingOtherPartyCard,
  BookingPrimaryAction,
  BookingActionBar,
} from '@/features/booking/components'
import {
  BookingStatus,
  CHAT_VIEW_STATUSES,
  UGC_CHAT_VIEW_STATUSES,
  CANCELLABLE_BY_FACE_STATUSES,
  CANCELLABLE_BY_PRODUCER_STATUSES,
  getCancellationReasonLabel,
  type BookingCancellationPayload,
  type BookingStatusType,
  type BookingRating,
  type BookingPrimaryActionModel,
} from '@/features/booking/types'
import {
  UgcPaymentOverlay,
  CommissionBreakdown,
  UgcFaceRewardSummary,
  UgcEngagementModal,
  UgcBookingTimeline,
  UgcShipmentForm,
  UgcShipmentTrackingCard,
  UgcFaceTrackingCard,
  ProductPhotoGallery,
  ProductPhotosUpload,
  ugcTunnelStep,
  UGC_UNBOXING_DAYS,
  type ConfirmShipmentPayload,
} from '@/components/ugc'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { useToast } from '@/composables/useToast'
import { usePaymentReturn } from '@/composables/usePaymentReturn'
import PaymentReturnBanner from '@/components/payment/PaymentReturnBanner.vue'
import { useUgcShipment } from '@/composables/useUgcShipment'
import { useUgcDeliverable } from '@/composables/useUgcDeliverable'
import { formatBookingDeadline } from '@/features/booking/utils/formatBookingDeadline'
import {
  buildBookingView,
  formatRemaining,
  formatShortDate,
  type BookingRole,
} from '@/features/booking/utils/bookingPresentation'
import { CONTACT_DETAILS_MESSAGE, containsContactDetails } from '@/lib/contactDetails'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const authStore = useAuthStore()
const isFace = computed(() => authStore.user?.userable_type === 'Face')
const currentUserId = computed(() => authStore.user?.id ?? 0)

const { booking, isLoading, error, notFound, fetchBooking, refresh } = useBookingDetail()
const {
  isAccepting,
  isRefusing,
  isConfirming,
  isCancelling,
  isReportingNoShow,
  isContesting,
  error: actionError,
  errorCode: actionErrorCode,
  errorStatus: actionErrorStatus,
  accept,
  refuse,
  confirm,
  cancel,
  reportNoShow,
  contest,
  clearError,
} = useBookingActions()

// Payment overlay state
const showPaymentOverlay = ref(false)

// UGC commission payment tunnel (Producer view of a pending UGC booking).
const showUgcPaymentOverlay = ref(false)
const canPayUgcCommission = computed<boolean>(
  () =>
    !isFace.value
    && booking.value?.type_contenu === 'UGC'
    && booking.value?.status === BookingStatus.PENDING,
)

// UGC engagement modal (2.4) — règles + chronos avant l'accept d'un booking UGC.
const showEngagementModal = ref(false)
// Récapitulatif avant d'accepter un booking cash (lot D9) : s'ouvre avant toute requête.
const showAcceptDialog = ref(false)
const acceptConfirmButton = ref<HTMLButtonElement | null>(null)

// Expédition UGC (3.2) — panneau écran 4A + tracking Producteur.
const {
  isSubmitting: isSubmittingShipment,
  error: shipmentError,
  errorCode: shipmentErrorCode,
  confirmShipment,
  confirmReceipt,
} = useUgcShipment()

// Upload du livrable Unboxing (4.2) — sibling de useUgcShipment, noms distincts
// pour ne pas collisionner avec l'état receipt (isSubmittingShipment).
const {
  isUploading: isUploadingDeliverable,
  uploadProgress,
  error: deliverableError,
  errorCode: deliverableErrorCode,
  uploadDeliverable,
} = useUgcDeliverable()

const isUgc = computed(() => booking.value?.type_contenu === 'UGC')
// Commission UGC déjà réglée côté Producteur : le récap n'affiche plus « À payer maintenant ».
const ugcCommissionPaid = computed(() =>
  ['commission_paid', 'accepted', 'completed'].includes(booking.value?.status ?? ''),
)

const ugcTimelineCurrent = computed(() =>
  booking.value ? ugcTunnelStep(booking.value.status, booking.value.shipment ?? null) : 0,
)

// Carte de suivi Face (3.4) — remplace la timeline H dès qu'un shipment existe (D-3.4.e).
const showReceiptModal = ref(false)
// Photos de réception (spec réception) : 1-2 obligatoires avant de confirmer.
const receiptPhotos = ref<File[]>([])
const showFaceTrackingCard = computed(
  () => isFace.value && isUgc.value && !!booking.value?.shipment,
)
const receiptModalMessage = `Joins 1 à 2 photos du produit reçu, puis confirme. Le chrono Unboxing (${UGC_UNBOXING_DAYS} jours) démarre dès la confirmation — cette action est définitive.`

function openReceiptModal(): void {
  receiptPhotos.value = []
  showReceiptModal.value = true
}

function closeReceiptModal(): void {
  showReceiptModal.value = false
  receiptPhotos.value = []
}

// Chat (3.1 AC8) : l'UGC ouvre à Accepted ; le cash garde sa constante (D-3.2.d).
const canShowChat = computed(() => {
  if (!booking.value) return false
  return isUgc.value
    ? UGC_CHAT_VIEW_STATUSES.includes(booking.value.status)
    : CHAT_VIEW_STATUSES.includes(booking.value.status)
})

// Panneau écran 4A : Producteur, deal accepté, pas encore expédié.
const canConfirmShipment = computed(
  () =>
    !isFace.value
    && isUgc.value
    && booking.value?.status === BookingStatus.ACCEPTED
    && !booking.value?.shipment,
)

// Refuse dialog state
const showRefuseDialog = ref(false)
const refuseReason = ref('')
// Erreurs de validation serveur (422) affichées sous le champ des dialogues de refus / annulation.
const refuseFieldError = ref<string | null>(null)
const cancelFieldError = ref<string | null>(null)
// Refus possible uniquement avant paiement (messagerie fermée) : pas de coordonnées dans la raison (lot B12).
const refuseContactError = computed<string | null>(() =>
  containsContactDetails(refuseReason.value) ? CONTACT_DETAILS_MESSAGE : null,
)
const showReasonField = ref(false)

// Cancellation dialog state
const showCancellationDialog = ref(false)
// No-show dialog state
const showNoShowDialog = ref(false)
// Contest dialog state (Face — fenêtre de contestation 72 h)
const showContestDialog = ref(false)
const contestMessage = ref('')
const CONTEST_MIN_LENGTH = 10
const CONTEST_MAX_LENGTH = 1000
const nowTimestamp = ref(Date.now())
let countdownTicker: ReturnType<typeof setInterval> | null = null

// Booking route param uses the public UUID (legacy numeric IDs remain supported by the API).
const bookingId = computed(() => {
  const id = route.params.id
  return typeof id === 'string' && id.length > 0 ? id : null
})

const bookingRealtimeChannelKey = computed(() => booking.value?.realtime_channel_key ?? null)

// Producer data from booking
const producerName = computed(() => {
  const producer = booking.value?.producer
  if (!producer?.userable) return 'Producteur'
  const userable = producer.userable as { display_name?: string }
  return userable.display_name || 'Producteur'
})

const faceName = computed(() => {
  const face = booking.value?.face
  if (!face?.userable) return 'Face'
  const userable = face.userable as { prenom?: string; nom?: string; username?: string }
  const fullName = `${userable.prenom ?? ''} ${userable.nom ?? ''}`.trim()
  return fullName || userable.username || 'Face'
})

const ratedName = computed(() => (isFace.value ? producerName.value : faceName.value))

// Lot C6 : la page parle au rôle de celui qui regarde (titre, pastille, « Et ensuite ? », étapes).
const role = computed<BookingRole>(() => (isFace.value ? 'face' : 'producer'))
const view = computed(() => (booking.value ? buildBookingView(booking.value, role.value) : null))
const nextCountdown = computed<string | null>(() => {
  const deadline = view.value?.next.deadlineAt
  return deadline ? formatRemaining(deadline, nowTimestamp.value) : null
})

// Délai d'annulation de la Face (fourni par le backend) : sans échéance connue, on laisse le backend trancher.
const faceCancelDeadline = computed<Date | null>(() => {
  const raw = booking.value?.face_cancellation_deadline
  return raw ? new Date(raw) : null
})

const isFaceCancelWindowOpen = computed(
  () => faceCancelDeadline.value === null || nowTimestamp.value < faceCancelDeadline.value.getTime(),
)

const formattedFaceCancelDeadline = computed(() =>
  faceCancelDeadline.value
    ? new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(
        faceCancelDeadline.value,
      )
    : '',
)

// La Face voit le délai (avant) ou l'explication (après) tant que le statut reste annulable.
const showFaceCancelInfo = computed(
  () => !!booking.value && isFace.value && faceCancelDeadline.value !== null
    && CANCELLABLE_BY_FACE_STATUSES.includes(booking.value.status),
)

const canCancelBooking = computed(() => {
  if (!booking.value) return false

  if (isFace.value) {
    return CANCELLABLE_BY_FACE_STATUSES.includes(booking.value.status) && isFaceCancelWindowOpen.value
  }

  return CANCELLABLE_BY_PRODUCER_STATUSES.includes(booking.value.status)
})

// Pas d'heure de tournage : les règles « jour J » se calculent en jours UTC (fuseau de l'application).
const DAY_MS = 24 * 60 * 60 * 1000

function startOfUtcDay(isoDate: string): number {
  const date = new Date(isoDate)
  return Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate())
}

// No-show report visibility (Producer only, paid or confirmed by Face, from the day AFTER the shoot day)
const canReportNoShow = computed(() => {
  if (!booking.value) return false
  if (isFace.value) return false
  if (booking.value.type_contenu === 'UGC') return false
  if (booking.value.status !== BookingStatus.PAID && booking.value.status !== BookingStatus.CONFIRMED_BY_FACE) return false
  if (!booking.value.date_debut) return false
  return nowTimestamp.value >= startOfUtcDay(booking.value.date_debut) + DAY_MS
})

// Producer cancelling a paid booking from the shoot day: funds held 72 h instead of an immediate refund.
const isLateProducerCancel = computed(() => {
  if (!booking.value || isFace.value) return false
  if (booking.value.status !== BookingStatus.PAID || !booking.value.date_debut) return false
  return nowTimestamp.value >= startOfUtcDay(booking.value.date_debut)
})

// Règlement en attente : absence signalée / annulation tardive, fenêtre de 72 h non tranchée.
const isPendingSettlement = computed(
  () =>
    !!booking.value
    && (booking.value.status === BookingStatus.NO_SHOW || booking.value.status === BookingStatus.CANCELLED_BY_PRODUCER)
    && !!booking.value.settlement_due_at
    && !booking.value.dispute_resolved_at,
)

const isDisputed = computed(() => isPendingSettlement.value && !!booking.value?.disputed_at)

const settlementDueLabel = computed(() => {
  const due = booking.value?.settlement_due_at
  if (!due) return ''
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(due))
})

// Délai écoulé, non contesté : le règlement automatique part au prochain passage du job horaire.
const isWindowOver = computed(
  () =>
    isPendingSettlement.value
    && !booking.value?.disputed_at
    && new Date(booking.value?.settlement_due_at ?? 0).getTime() <= nowTimestamp.value,
)

const canContest = computed(
  () =>
    isFace.value
    && isPendingSettlement.value
    && !booking.value?.disputed_at
    && new Date(booking.value?.settlement_due_at ?? 0).getTime() > nowTimestamp.value,
)

const disputeOutcomeLabel = computed<string | null>(() => {
  const current = booking.value
  if (!current?.dispute_resolved_at || !current.dispute_outcome) return null

  const contested = !!current.disputed_at
  // Deuxième personne pour le lecteur ; sans contestation, aucun administrateur n'est intervenu.
  if (current.dispute_outcome === 'favor_face') {
    return isFace.value
      ? 'L\'administrateur a tranché en votre faveur : le paiement vous est versé.'
      : 'L\'administrateur a tranché en faveur de la Face : le paiement lui est versé.'
  }
  if (isFace.value) {
    return contested
      ? 'L\'administrateur a tranché en faveur du Producteur.'
      : 'Délai de contestation écoulé : le Producteur a été remboursé.'
  }
  return contested
    ? 'L\'administrateur a tranché en votre faveur : vous avez été remboursé.'
    : 'Aucune contestation : vous avez été remboursé.'
})

const canSubmitContest = computed(() => {
  const length = contestMessage.value.trim().length
  return length >= CONTEST_MIN_LENGTH && length <= CONTEST_MAX_LENGTH && !isContesting.value
})

// Confirm button visibility and label (role-aware)
const confirmLabel = computed<string | null>(() => {
  const status = booking.value?.status as BookingStatusType | undefined
  if (status === BookingStatus.PAID) return 'Confirmer la réalisation'
  // Face sees "Confirmer aussi" when Producer already confirmed
  if (isFace.value && status === BookingStatus.CONFIRMED_BY_PRODUCER) return 'Confirmer aussi'
  // Producer sees "Confirmer aussi" when Face already confirmed
  if (!isFace.value && status === BookingStatus.CONFIRMED_BY_FACE) return 'Confirmer aussi'
  return null
})

// Lot B10 : échéances fournies par le backend tant que la demande attend une réponse
// (Face) ou le règlement de la commission UGC (Producer). Masquées une fois dépassées.
const responseDeadlineText = computed<string | null>(() => {
  const raw = booking.value?.face_response_deadline
  if (!raw || booking.value?.status !== BookingStatus.PENDING) return null
  if (new Date(raw).getTime() <= nowTimestamp.value) return null
  const label = formatBookingDeadline(raw)
  return isFace.value
    ? `Répondez avant le ${label}, sinon la demande expire.`
    : `La Face a jusqu'au ${label} pour répondre à votre demande.`
})

const ugcCommissionDeadlineText = computed<string | null>(() => {
  const raw = booking.value?.ugc_commission_payment_deadline
  if (!raw || booking.value?.status !== BookingStatus.PENDING) return null
  if (new Date(raw).getTime() <= nowTimestamp.value) return null
  const label = formatBookingDeadline(raw)
  return isFace.value
    ? `Le Producteur doit régler la commission avant le ${label}, sinon la demande expire.`
    : `Réglez la commission avant le ${label}, sinon la demande expire.`
})

const isBeforeShootingDate = computed<boolean>(() => {
  if (!booking.value?.date_debut) return false
  return new Date(booking.value.date_debut).getTime() > nowTimestamp.value
})

// Lot B11 : échéance fournie par le backend (accepted_at + 24 h, source unique) ; repli
// sur le calcul local pour une réponse qui ne la porterait pas.
const paymentDeadline = computed<Date | null>(() => {
  if (booking.value?.payment_deadline) return new Date(booking.value.payment_deadline)
  if (!booking.value?.accepted_at) return null
  return new Date(new Date(booking.value.accepted_at).getTime() + 24 * 60 * 60 * 1000)
})

const shouldShowPaymentDeadline = computed<boolean>(() => {
  // UGC (2.4) : aucun paiement cash n'est attendu après acceptation.
  // Lot B11 : la Face voit aussi l'échéance (le chat s'ouvre au paiement).
  return booking.value?.status === BookingStatus.ACCEPTED
    && booking.value?.type_contenu !== 'UGC'
    && !!booking.value.accepted_at
})

const isPaymentOverdue = computed<boolean>(() => {
  if (!paymentDeadline.value) return false
  return paymentDeadline.value.getTime() <= nowTimestamp.value
})

const formattedPaymentDeadline = computed<string>(() => {
  if (!paymentDeadline.value) return ''
  return formatBookingDeadline(paymentDeadline.value.toISOString())
})

// Lot B11 : le bouton « Payer maintenant » disparaît à l'échéance exacte, sans attendre
// le prochain tic de la minute (le backend refuse tout paiement à partir de cet instant).
let paymentDeadlineTimer: ReturnType<typeof setTimeout> | null = null

function clearPaymentDeadlineTimer(): void {
  if (paymentDeadlineTimer !== null) {
    clearTimeout(paymentDeadlineTimer)
    paymentDeadlineTimer = null
  }
}

watch(
  paymentDeadline,
  (deadline) => {
    clearPaymentDeadlineTimer()
    if (!deadline) return
    const delay = deadline.getTime() - Date.now()
    // setTimeout plafonne à ~24,8 jours ; l'échéance de paiement est à 24 h au plus.
    if (delay <= 0 || delay > 2 * 24 * 60 * 60 * 1000) return
    paymentDeadlineTimer = setTimeout(() => {
      nowTimestamp.value = Date.now()
    }, delay)
  },
  { immediate: true },
)

// Lot C6 : UNE action principale par état (en haut à droite, ou dans la barre collée en bas
// sur téléphone). Les actions secondaires (refuser, signaler une absence, contester…)
// restent disponibles dans la page, en retrait. Premier cas qui s'applique, dans l'ordre.
const primaryAction = computed<BookingPrimaryActionModel | null>(() => {
  const current = booking.value
  if (!current) return null

  if (current.can_pay && !isPaymentOverdue.value) {
    return { key: 'pay', label: `Payer ${formatMoney(current.montant_total_producteur)}`, icon: Wallet }
  }
  if (canPayUgcCommission.value) {
    return {
      key: 'pay-commission',
      label: `Payer la commission · ${formatMoney(current.commission_ugc)}`,
      icon: Wallet,
      testid: 'ugc-pay-commission-cta',
    }
  }
  if (current.can_accept) {
    return { key: 'accept', label: 'Accepter', icon: CheckCircle, loading: isAccepting.value }
  }
  if (confirmLabel.value && !isBeforeShootingDate.value) {
    return { key: 'confirm', label: confirmLabel.value, icon: CheckCircle, loading: isConfirming.value }
  }
  if (canConfirmShipment.value) {
    return { key: 'ship', label: "Confirmer l'expédition", icon: Package }
  }
  if (current.status === BookingStatus.COMPLETED && current.can_rate) {
    return { key: 'rate', label: `Noter ${ratedName.value}`, icon: Star }
  }
  if (canShowChat.value && current.status !== BookingStatus.COMPLETED) {
    return { key: 'chat', label: 'Ouvrir le chat', icon: MessageSquare }
  }
  if (canCancelBooking.value) {
    return {
      key: 'cancel',
      label: !isFace.value && current.status === BookingStatus.PENDING ? 'Annuler la demande' : 'Annuler le booking',
      kind: 'ghost',
      testid: 'face-cancel-btn',
    }
  }
  return null
})

// Messagerie fermée avant paiement (UGC : avant l'acceptation) — voulu, anti-contournement.
const messagingLockedText = computed<string | null>(() => {
  const status = booking.value?.status
  if (!status || canShowChat.value) return null
  if (status !== BookingStatus.PENDING && status !== BookingStatus.ACCEPTED && status !== BookingStatus.COMMISSION_PAID) {
    return null
  }
  return isUgc.value
    ? "La messagerie s'ouvre quand la Face accepte la demande."
    : "La messagerie s'ouvre après le paiement. Avant, les échanges passent par le brief et la demande."
})

const actionBarSub = computed<string>(() => nextCountdown.value ?? view.value?.next.who ?? '')

// Ancres des actions qui ne font que mener à une zone de la page.
const chatSection = ref<HTMLElement | null>(null)
const ratingSection = ref<HTMLElement | null>(null)
const shipmentPanel = ref<HTMLElement | null>(null)

function scrollToSection(target: HTMLElement | null): void {
  if (!target || typeof target.scrollIntoView !== 'function') return
  const reduce = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' })
}

function handlePrimaryAction(): void {
  switch (primaryAction.value?.key) {
    case 'pay':
      showPaymentOverlay.value = true
      break
    case 'pay-commission':
      showUgcPaymentOverlay.value = true
      break
    case 'accept':
      handleAccept()
      break
    case 'confirm':
      void handleConfirm()
      break
    case 'ship':
      scrollToSection(shipmentPanel.value)
      break
    case 'rate':
      scrollToSection(ratingSection.value)
      break
    case 'chat':
      // Sur grand écran le chat est déjà visible à droite : on place le curseur dans la saisie.
      scrollToSection(chatSection.value)
      chatSection.value?.querySelector('textarea')?.focus({ preventScroll: true })
      break
    case 'cancel':
      showCancellationDialog.value = true
      break
  }
}

/**
 * FORMAT HELPERS
 */
function formatDate(dateString: string | null): string {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(dateString))
}

/**
 * ACTIONS
 */
function goBack(): void {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push({ name: isFace.value ? 'face-bookings' : 'producer-bookings' })
  }
}

async function doAccept(): Promise<void> {
  if (!booking.value) return
  clearError()

  const result = await accept(booking.value.id)
  if (result) {
    booking.value = result
    toast.success('Booking accepté avec succès !')
    return
  }

  // Paywall FR5 (2.4) : Face non éligible → invitation à s'abonner.
  if (actionErrorCode.value === 'UGC_SUBSCRIPTION_REQUIRED') {
    toast.info(actionError.value || "L'accès aux missions UGC est réservé aux Faces abonnées Pro ou Élite.")
    router.push({ name: 'pricing', query: { redirect: route.fullPath } })
    return
  }

  toast.error(actionError.value || 'Erreur lors de l\'acceptation')
}

function handleAccept(): void {
  if (!booking.value) return

  // UGC (2.4) : l'engagement sur les délais est explicite AVANT tout appel API.
  if (booking.value.type_contenu === 'UGC') {
    showEngagementModal.value = true
    return
  }

  // Cash : récapitulatif (dates, lieu, durée, net, règles d'annulation) avant la requête.
  showAcceptDialog.value = true
}

function closeAcceptDialog(): void {
  // Pas de fermeture en plein vol : la requête part quand même.
  if (isAccepting.value) return
  showAcceptDialog.value = false
}

async function handleAcceptDialogConfirm(): Promise<void> {
  if (isAccepting.value) return
  await doAccept()
  showAcceptDialog.value = false
}

function handleAcceptDialogKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') closeAcceptDialog()
}

// Focus initial sur l'action ; Échap ferme tant que la fenêtre est ouverte.
watch(showAcceptDialog, async (open) => {
  if (open) {
    window.addEventListener('keydown', handleAcceptDialogKeydown)
    await nextTick()
    acceptConfirmButton.value?.focus()
  } else {
    window.removeEventListener('keydown', handleAcceptDialogKeydown)
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleAcceptDialogKeydown)
})

// Dates du récapitulatif : un seul jour si début et fin tombent le même jour.
const acceptRecapDates = computed<string>(() => {
  const b = booking.value
  if (!b?.date_debut) return ''
  const start = formatDate(b.date_debut)
  const end = b.date_fin ? formatDate(b.date_fin) : ''
  return end && end !== start ? `Du ${start} au ${end}` : start
})

async function handleEngagementConfirm(): Promise<void> {
  // Modal maintenue ouverte pendant l'appel (parité mission) : `:is-submitting`
  // verrouille le CTA et empêche un double-clic d'engagement.
  await doAccept()
  showEngagementModal.value = false
}

function openRefuseDialog(): void {
  refuseReason.value = ''
  refuseFieldError.value = null
  showReasonField.value = false
  showRefuseDialog.value = true
}

function closeRefuseDialog(): void {
  showRefuseDialog.value = false
  refuseReason.value = ''
  refuseFieldError.value = null
  showReasonField.value = false
}

async function handleRefuse(): Promise<void> {
  if (!booking.value || refuseContactError.value) return

  clearError()
  refuseFieldError.value = null
  const result = await refuse(booking.value.id, refuseReason.value || undefined)
  if (result) {
    booking.value = result
    closeRefuseDialog()
    toast.success('Booking refusé')
  } else if (actionErrorStatus.value === 422 && actionError.value) {
    refuseFieldError.value = actionError.value
  } else {
    toast.error(actionError.value || 'Erreur lors du refus')
  }
}

async function handleConfirm(): Promise<void> {
  if (!booking.value) return
  clearError()

  const result = await confirm(booking.value.id)
  if (result) {
    booking.value = result
    toast.success('Confirmation enregistrée !')
  } else {
    toast.error(actionError.value || 'Erreur lors de la confirmation')
  }
}

async function handleCancel(payload: BookingCancellationPayload): Promise<void> {
  if (!booking.value) return
  clearError()
  cancelFieldError.value = null

  const result = await cancel(booking.value.id, payload.reason, payload.customReason)
  if (result) {
    booking.value = result
    showCancellationDialog.value = false
    toast.success(
      result.settlement_due_at
        ? 'Booking annulé. Les fonds restent en séquestre 72 h : la Face peut contester, sinon vous serez remboursé.'
        : 'Booking annulé',
    )
  } else if (actionErrorStatus.value === 422 && actionError.value) {
    cancelFieldError.value = actionError.value
  } else {
    toast.error(actionError.value || 'Erreur lors de l\'annulation')
  }
}

async function handleReportNoShow(): Promise<void> {
  if (!booking.value) return
  clearError()

  const result = await reportNoShow(booking.value.id)
  if (result) {
    booking.value = result
    showNoShowDialog.value = false
    toast.success('Absence signalée. Les fonds restent en séquestre 72 h : sans contestation de la Face, vous serez remboursé.')
  } else {
    toast.error(actionError.value || 'Erreur lors du signalement')
  }
}

function openContestDialog(): void {
  contestMessage.value = ''
  showContestDialog.value = true
}

function closeContestDialog(): void {
  if (isContesting.value) return
  showContestDialog.value = false
  contestMessage.value = ''
}

async function handleContest(): Promise<void> {
  if (!booking.value || !canSubmitContest.value) return
  clearError()

  const result = await contest(booking.value.id, contestMessage.value.trim())
  if (result) {
    booking.value = result
    showContestDialog.value = false
    contestMessage.value = ''
    toast.success('Contestation envoyée. Un administrateur va trancher.')
    return
  }

  toast.error(actionError.value || 'Erreur lors de la contestation')

  // 403/422 : la fenêtre s'est refermée ou le booking a changé — on recharge l'état réel.
  if (actionErrorStatus.value === 403 || actionErrorStatus.value === 422) {
    showContestDialog.value = false
    contestMessage.value = ''
    if (bookingId.value) await fetchBooking(bookingId.value)
  }
}

function handleRatingSubmitted(rating: BookingRating): void {
  if (!booking.value) return

  booking.value.my_rating = rating
  booking.value.can_rate = false
  toast.success('Évaluation envoyée avec succès')
}


async function handleConfirmShipment(payload: ConfirmShipmentPayload): Promise<void> {
  if (!booking.value) return

  const shipment = await confirmShipment('booking', booking.value.id, payload)
  if (shipment) {
    // D-3.1.c : le statut booking ne change pas — l'assignation locale suffit.
    booking.value.shipment = shipment
    toast.success('Expédition confirmée')
    return
  }

  if (shipmentErrorCode.value === 'ALREADY_SHIPPED') {
    // Multi-onglets (D-3.1.b/D-3.2.e) : récupérer le shipment existant.
    toast.info(shipmentError.value || "L'expédition de ce deal a déjà été confirmée.")
    if (bookingId.value) await fetchBooking(bookingId.value)
    return
  }

  toast.error(shipmentError.value || "Erreur lors de la confirmation de l'expédition")
}

async function handleConfirmReceipt(): Promise<void> {
  // Le bouton « Confirmer » de la modale est désactivé tant qu'aucune photo n'est
  // jointe (garde UI) ; défense en profondeur ici (le backend impose 1-2 photos).
  if (!booking.value?.shipment || receiptPhotos.value.length === 0) return

  const photos = receiptPhotos.value
  closeReceiptModal()

  const shipment = await confirmReceipt(booking.value.shipment.id, photos)
  if (shipment) {
    // D-3.3.i/D-3.4.d : la 200 porte la ShipmentResource à jour — pas de refetch.
    // La ref peut avoir été vidée pendant l'await (navigation, refetch concurrent).
    if (booking.value) booking.value.shipment = shipment
    toast.success('Réception confirmée — le chrono Unboxing démarre')
    return
  }

  if (shipmentErrorCode.value === 'ALREADY_RECEIVED') {
    // Multi-onglets : récupérer l'état réel (parité D-3.2.e).
    toast.info(shipmentError.value || 'La réception de ce produit a déjà été confirmée.')
    if (bookingId.value) await fetchBooking(bookingId.value)
    return
  }

  toast.error(shipmentError.value || 'Erreur lors de la confirmation de la réception')
}

async function handleUploadDeliverable(file: File): Promise<void> {
  if (!booking.value?.shipment) return

  const deliverable = await uploadDeliverable(booking.value.shipment.id, file)
  if (deliverable) {
    toast.success('Vidéo Unboxing déposée — en attente de validation')
    // D-4.2.d : le 201 porte la DeliverableResource, PAS le shipment → refetch.
    if (bookingId.value) await fetchBooking(bookingId.value)
    return
  }
  if (deliverableErrorCode.value === 'ALREADY_UPLOADED') {
    toast.info(deliverableError.value || 'Votre vidéo Unboxing a déjà été déposée.')
    if (bookingId.value) await fetchBooking(bookingId.value)
    return
  }
  if (deliverableErrorCode.value === 'INVALID_STATUS') {
    toast.error(deliverableError.value || "La vidéo ne peut pas être déposée dans l'état actuel de ce deal.")
    if (bookingId.value) await fetchBooking(bookingId.value)
    return
  }
  toast.error(deliverableError.value || "Erreur lors de l'envoi de la vidéo Unboxing")
}

// Lot C6 : on écoute le canal du booking tant qu'il peut encore évoluer (pas seulement
// « accepté ») ; il se referme dès que le booking est terminal.
const TERMINAL_STATUSES: BookingStatusType[] = [
  BookingStatus.COMPLETED,
  BookingStatus.REFUSED,
  BookingStatus.EXPIRED,
  BookingStatus.CANCELLED_BY_FACE,
]

const isRealtimeActive = computed<boolean>(() => {
  const current = booking.value
  if (!current) return false
  if (TERMINAL_STATUSES.includes(current.status)) return false
  // Absence / annulation tardive : encore vivant tant que le règlement n'est pas tranché.
  if (
    (current.status === BookingStatus.NO_SHOW || current.status === BookingStatus.CANCELLED_BY_PRODUCER)
    && !isPendingSettlement.value
  ) {
    return false
  }
  return true
})

async function refetchBooking(): Promise<void> {
  if (bookingId.value !== null) {
    await fetchBooking(bookingId.value)
  }
}

useBookingRealtime(bookingRealtimeChannelKey, isRealtimeActive, refetchBooking)

// Return from the same-tab FedaPay checkout (?payment_return=booking|booking_commission).
const paymentReturn = usePaymentReturn({
  kinds: ['booking', 'booking_commission'],
  ids: () => ({ bookingId: bookingId.value }),
  onConfirmed: async () => {
    if (bookingId.value) await fetchBooking(bookingId.value)
  },
  onRetry: (kind) => {
    if (kind === 'booking_commission') showUgcPaymentOverlay.value = true
    else showPaymentOverlay.value = true
  },
})

onMounted(async () => {
  if (bookingId.value) {
    await fetchBooking(bookingId.value)
  }

  // A ?payment_return takes over: verify the payment, never also auto-open the tunnel.
  const isPaymentReturn = route.query.payment_return !== undefined
  if (isPaymentReturn) {
    await paymentReturn.start()
  } else if (route.query.pay === '1' && canPayUgcCommission.value) {
    // Auto-open the commission tunnel when arriving from UGC booking creation (?pay=1).
    showUgcPaymentOverlay.value = true
  }

  // Consume ?pay (opened or not): a full-reload Back to this URL must not replay the tunnel.
  if (route.query.pay !== undefined) {
    const query = { ...route.query }
    delete query.pay
    void router.replace({ query })
  }

  countdownTicker = setInterval(() => {
    nowTimestamp.value = Date.now()
  }, 60000)
})

onUnmounted(() => {
  clearPaymentDeadlineTimer()
  if (countdownTicker !== null) {
    clearInterval(countdownTicker)
    countdownTicker = null
  }
})
</script>

<template>
  <!-- Avec le chat, la page s'élargit : infos à gauche, conversation à droite (sans défiler). -->
  <div
    class="@container/page mx-auto px-4 py-6 max-sm:pb-28"
    :class="canShowChat ? 'max-w-7xl' : 'max-w-5xl'"
  >
    <!-- Back button -->
    <button
      class="booking-press flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-6 transition-colors"
      @click="goBack"
    >
      <ArrowLeft class="w-4 h-4" />
      Retour
    </button>

    <!-- Loading state (premier chargement seulement : un rechargement temps réel ne vide pas la page) -->
    <div v-if="isLoading && !booking" class="flex flex-col items-center justify-center py-20">
      <Loader2 class="w-8 h-8 text-weact animate-spin" />
      <p class="mt-3 text-sm text-gray-500">Chargement du booking...</p>
    </div>

    <!-- Not found state -->
    <div v-else-if="notFound" class="text-center py-20">
      <AlertCircle class="w-12 h-12 text-gray-300 mx-auto mb-4" />
      <h2 class="text-lg font-semibold text-gray-700 mb-1">Booking non trouvé</h2>
      <p class="text-sm text-gray-500">Ce booking n'existe pas ou a été supprimé.</p>
    </div>

    <!-- Error state -->
    <div v-else-if="error && !booking" class="rounded-lg border border-red-200 bg-red-50 p-6 text-center">
      <AlertCircle class="w-8 h-8 text-red-400 mx-auto mb-3" />
      <p class="text-sm text-red-700">{{ error }}</p>
      <button
        class="mt-3 text-sm font-medium text-red-600 hover:text-red-700 underline"
        @click="refresh"
      >
        Réessayer
      </button>
    </div>

    <!-- Booking detail content -->
    <div
      v-else-if="booking && view"
      class="grid grid-cols-1 items-start gap-6"
      :class="{ '@4xl/page:grid-cols-[minmax(0,1fr)_minmax(340px,400px)]': canShowChat }"
      data-testid="booking-layout"
    >
      <!-- Infos du booking -->
      <div class="@container/info min-w-0">
        <PaymentReturnBanner
          :state="paymentReturn.state.value"
          @retry="paymentReturn.retry"
          @dismiss="paymentReturn.dismiss"
        />

        <!-- Header : titre et pastille propres au statut ET au rôle, action principale à droite -->
        <header class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start sm:gap-x-5">
          <div class="min-w-0">
            <p class="text-xs text-gray-500">Booking · {{ booking.type_contenu }}</p>
            <BookingSwap>
              <h1
                :key="view.title"
                class="text-lg font-bold leading-tight text-balance text-gray-900 sm:text-[22px]"
                data-testid="booking-title"
              >
                {{ view.title }}
              </h1>
            </BookingSwap>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[13px] text-gray-500">
              <BookingSwap>
                <span
                  :key="view.pill.label"
                  class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="{
                    'bg-weact-50 text-weact': view.pill.tone === 'info',
                    'bg-amber-50 text-amber-800': view.pill.tone === 'warn',
                    'bg-emerald-50 text-emerald-700': view.pill.tone === 'ok',
                    'bg-gray-50 text-gray-500 border border-gray-200': view.pill.tone === 'mute',
                    'bg-red-50 text-red-700': view.pill.tone === 'danger',
                  }"
                  data-testid="booking-pill"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
                  {{ view.pill.label }}
                </span>
              </BookingSwap>
              <span v-if="booking.date_debut" class="inline-flex items-center gap-1.5">
                <Calendar class="w-3.5 h-3.5" aria-hidden="true" />
                {{ formatShortDate(booking.date_debut) }}
              </span>
              <span v-if="booking.lieu" class="inline-flex items-center gap-1.5">
                <MapPin class="w-3.5 h-3.5" aria-hidden="true" />
                {{ booking.lieu }}
              </span>
            </div>
          </div>
          <div
            v-if="primaryAction"
            class="hidden sm:flex sm:items-center sm:gap-2"
            :class="{ 'booking-primary-action--chat': primaryAction.key === 'chat' }"
            data-testid="booking-primary-action"
          >
            <BookingPrimaryAction :action="primaryAction" @click="handlePrimaryAction" />
          </div>
        </header>

        <!-- Une seule barre de progression : en UGC actif, la frise détaillée du tunnel
             (ci-dessous) la remplace ; la barre générique ne sert qu'à montrer où un
             booking UGC arrêté (refusé, annulé, expiré) s'est interrompu. -->
        <BookingStepsBar
          v-if="!isUgc || view.steps.halted"
          class="mb-4"
          :labels="view.steps.labels"
          :current="view.steps.current"
          :halted="view.steps.halted"
        />

        <!-- UGC timeline (3.2) — pleine largeur, détaille le tunnel de livraison.
             Pour la Face avec shipment, la carte de suivi (timeline V intégrée) la remplace (D-3.4.e). -->
        <div
          v-if="isUgc && !view.steps.halted && !showFaceTrackingCard"
          class="mb-4 overflow-x-auto rounded-[10px] border border-gray-200 bg-white p-5"
          data-testid="ugc-booking-timeline-card"
        >
          <h2 class="mb-4 text-sm font-semibold text-gray-700">Progression</h2>
          <UgcBookingTimeline :current="ugcTimelineCurrent" variant="horizontal" />
        </div>

        <!-- Carte de suivi Face (3.4, écran 8A) — même emplacement, pleine largeur -->
        <UgcFaceTrackingCard
          v-if="showFaceTrackingCard && booking.shipment"
          class="mb-4"
          :shipment="booking.shipment"
          :current="ugcTimelineCurrent"
          :is-submitting="isSubmittingShipment"
          :is-uploading="isUploadingDeliverable"
          :upload-progress="uploadProgress"
          :deliverables="booking.deliverables ?? []"
          @confirm-receipt="openReceiptModal"
          @upload="handleUploadDeliverable"
        />

        <div class="grid grid-cols-1 items-start gap-4 @3xl/info:grid-cols-[minmax(0,1fr)_300px]">
          <!-- Colonne principale -->
          <div class="min-w-0 space-y-4">
            <!-- L'autre partie : le Producteur voit la Face, la Face voit le Producteur -->
            <BookingOtherPartyCard :booking="booking" :role="role" />

            <!-- Booking details -->
            <div class="bg-white rounded-[10px] border border-gray-200 p-5">
              <h2 class="text-sm font-semibold text-gray-700 mb-3">Détails</h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-if="booking.date_debut" class="flex items-center gap-2.5">
                  <Calendar class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Date de début</p>
                    <p class="text-sm font-medium text-gray-900">{{ formatDate(booking.date_debut) }}</p>
                  </div>
                </div>
                <div v-if="booking.date_fin" class="flex items-center gap-2.5">
                  <Calendar class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Date de fin</p>
                    <p class="text-sm font-medium text-gray-900">{{ formatDate(booking.date_fin) }}</p>
                  </div>
                </div>
                <div v-if="booking.duree_heures" class="flex items-center gap-2.5">
                  <Clock class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Durée</p>
                    <p class="text-sm font-medium text-gray-900">max {{ booking.duree_heures }}h</p>
                  </div>
                </div>
                <div class="flex items-center gap-2.5">
                  <Film class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Type de contenu</p>
                    <p class="text-sm font-medium text-gray-900">{{ booking.type_contenu }}</p>
                  </div>
                </div>
                <div v-if="booking.lieu" class="flex items-center gap-2.5">
                  <MapPin class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Lieu de tournage</p>
                    <p class="text-sm font-medium text-gray-900">{{ booking.lieu }}</p>
                  </div>
                </div>
              </div>

              <!-- Producer message -->
              <div v-if="booking.message" class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-start gap-2.5">
                  <MessageSquare class="w-4 h-4 text-gray-400 shrink-0 mt-0.5" />
                  <div>
                    <p class="text-xs text-gray-500 mb-1">Message du producteur</p>
                    <p class="text-sm text-gray-700">{{ booking.message }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- UGC dotation summary (replaces the cash finances card for UGC bookings) -->
            <div v-if="booking.type_contenu === 'UGC'" class="bg-white rounded-[10px] border border-gray-200 p-5">
              <h2 class="text-sm font-semibold text-gray-700 mb-3">Dotation UGC</h2>
              <div class="space-y-3 mb-4">
                <div v-if="booking.nom_produit" class="flex items-center gap-2.5">
                  <Package class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Produit</p>
                    <p class="text-sm font-medium text-gray-900">{{ booking.nom_produit }}</p>
                  </div>
                </div>
                <div v-if="booking.type_compensation_label" class="flex items-center gap-2.5">
                  <Gift class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Type de compensation</p>
                    <p class="text-sm font-medium text-gray-900">{{ booking.type_compensation_label }}</p>
                  </div>
                </div>
                <div v-if="booking.nombre_videos" class="flex items-center gap-2.5">
                  <Film class="w-4 h-4 text-gray-400 shrink-0" />
                  <div>
                    <p class="text-xs text-gray-500">Vidéos attendues</p>
                    <p class="text-sm font-medium text-gray-900">{{ booking.nombre_videos }}</p>
                  </div>
                </div>
              </div>
              <!-- Photos produit (spec photos produit) — URLs signées, visibles des
                   deux parties ; aucune section sans photo (bookings pré-deploy) -->
              <ProductPhotoGallery
                v-if="booking.product_photos?.length"
                class="mb-4"
                title="Photos du produit"
                :photos="booking.product_photos"
              />
              <!-- La Face ne paie rien : jamais de commission ni de « À payer » pour elle -->
              <UgcFaceRewardSummary
                v-if="isFace"
                :nom-produit="booking.nom_produit"
                :valeur-produit="booking.valeur_produit ?? 0"
                :type-compensation="booking.type_compensation"
                :montant-face-recoit="booking.montant_face_recoit"
              />
              <CommissionBreakdown
                v-else
                :product-value="booking.valeur_produit ?? 0"
                :pay-amount="booking.montant_remuneration ?? 0"
                :on-platform="true"
                :paid="ugcCommissionPaid"
              />
            </div>

            <!-- Panneau expédition UGC (Producteur, écran 4A — 3.2) -->
            <div
              v-if="canConfirmShipment"
              ref="shipmentPanel"
              class="scroll-mt-4 rounded-[10px] border border-gray-200 bg-white p-5"
              data-testid="ugc-shipment-panel"
            >
              <p class="mb-1 text-[10px] font-bold uppercase tracking-widest text-weact">Étape 3 sur 6 · Expédition</p>
              <h2 class="text-sm font-semibold text-gray-700">Confirmer l'envoi du produit</h2>
              <p class="mb-4 mt-0.5 text-xs text-gray-500">{{ booking.nom_produit }} · Pour {{ faceName }}</p>
              <UgcShipmentForm :is-submitting="isSubmittingShipment" @submit="handleConfirmShipment" />
            </div>

            <!-- Tracking expédition UGC (Producteur post-confirmation — 3.2) -->
            <UgcShipmentTrackingCard
              v-if="!isFace && isUgc && booking.shipment"
              :shipment="booking.shipment"
            />

            <!-- Cancellation reason (if refused/cancelled) -->
            <div
              v-if="booking.cancellation_reason"
              class="bg-red-50 rounded-[10px] border border-red-200 p-5"
            >
              <h2 class="text-sm font-semibold text-red-700 mb-2">
                {{ booking.status === BookingStatus.REFUSED ? 'Raison du refus' : 'Raison de l\'annulation' }}
              </h2>
              <p class="text-sm text-red-600">{{ getCancellationReasonLabel(booking.cancellation_reason) }}</p>
              <p
                v-if="booking.custom_cancellation_reason"
                class="mt-2 text-sm text-red-700"
              >
                {{ booking.custom_cancellation_reason }}
              </p>
            </div>

            <!-- Règlement en attente (fenêtre de contestation 72 h) -->
            <div
              v-if="isPendingSettlement"
              class="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
              data-testid="settlement-banner"
            >
              <p v-if="isDisputed" data-testid="settlement-disputed">
                Contestation en cours : un administrateur va trancher.
              </p>
              <p v-else-if="isWindowOver" data-testid="settlement-over">
                Délai terminé : règlement en cours.
              </p>
              <template v-else-if="isFace">
                <p data-testid="settlement-face">
                  Vous pouvez contester jusqu'au {{ settlementDueLabel }}.
                  Sans contestation de votre part, le Producteur sera remboursé le {{ settlementDueLabel }}.
                </p>
                <button
                  v-if="canContest"
                  class="booking-press mt-3 inline-flex items-center justify-center gap-2 rounded-lg border border-amber-400 bg-white px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100 transition-colors"
                  data-testid="contest-btn"
                  @click="openContestDialog"
                >
                  <AlertTriangle class="w-4 h-4" />
                  Contester
                </button>
              </template>
              <p v-else data-testid="settlement-producer">
                Remboursement prévu le {{ settlementDueLabel }} si la Face ne conteste pas.
              </p>
            </div>
            <p
              v-else-if="disputeOutcomeLabel"
              class="rounded-[10px] border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
              data-testid="dispute-outcome"
            >
              {{ disputeOutcomeLabel }}
            </p>

            <div
              v-if="shouldShowPaymentDeadline"
              data-testid="payment-deadline"
              class="rounded-[10px] border px-4 py-3 text-sm"
              :class="isPaymentOverdue
                ? 'border-red-200 bg-red-50 text-red-800'
                : 'border-amber-200 bg-amber-50 text-amber-800'"
            >
              <template v-if="isFace">
                <span v-if="isPaymentOverdue">Délai de paiement dépassé — cette demande va bientôt expirer</span>
                <span v-else>Le Producteur doit payer avant le {{ formattedPaymentDeadline }}, sinon la demande expirera.</span>
              </template>
              <template v-else>
                <span v-if="isPaymentOverdue">Délai expiré — cette demande va bientôt expirer</span>
                <span v-else>Paiement requis avant le {{ formattedPaymentDeadline }}</span>
              </template>
            </div>

            <p
              v-if="responseDeadlineText"
              class="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
              data-testid="face-response-deadline"
            >
              {{ responseDeadlineText }}
            </p>
            <p
              v-if="ugcCommissionDeadlineText"
              class="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
              data-testid="ugc-commission-deadline"
            >
              {{ ugcCommissionDeadlineText }}
            </p>

            <!-- Confirmation tant que le tournage n'a pas eu lieu : désactivée, avec la raison.
                 (Une fois possible, « Confirmer » devient l'action principale de la page.) -->
            <div v-if="confirmLabel && isBeforeShootingDate" class="relative group">
              <button
                disabled
                class="w-full flex items-center justify-center gap-2 rounded-lg bg-weact px-4 py-3 text-sm font-semibold text-white disabled:opacity-50 disabled:cursor-not-allowed"
                title="La confirmation n'est possible qu'à partir du jour du tournage"
              >
                <CheckCircle class="w-4 h-4" />
                {{ confirmLabel }}
              </button>
              <p class="mt-2 text-sm text-amber-600">
                La confirmation n'est possible qu'à partir du jour du tournage.
              </p>
            </div>

            <!-- Actions secondaires : refuser, annuler, signaler une absence -->
            <div v-if="booking.can_refuse" class="flex gap-3">
              <button
                :disabled="isRefusing"
                class="booking-press flex-1 flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                @click="openRefuseDialog"
              >
                <XCircle class="w-4 h-4" />
                Refuser
              </button>
            </div>

            <div v-if="canCancelBooking && primaryAction?.key !== 'cancel'" class="flex gap-3">
              <button
                class="booking-press flex-1 flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors"
                data-testid="face-cancel-btn"
                @click="showCancellationDialog = true"
              >
                <XCircle class="w-4 h-4" />
                Annuler le booking
              </button>
            </div>
            <p
              v-if="showFaceCancelInfo && isFaceCancelWindowOpen"
              class="text-sm text-gray-500"
              data-testid="face-cancel-deadline"
            >
              Annulation possible jusqu'au {{ formattedFaceCancelDeadline }}
            </p>
            <p
              v-else-if="showFaceCancelInfo"
              class="text-sm text-amber-600"
              data-testid="face-cancel-closed"
            >
              Annulation impossible à moins de 24 h du jour du tournage.
            </p>

            <!-- No-show report (Producer only) -->
            <div v-if="canReportNoShow" class="flex gap-3">
              <button
                class="booking-press flex-1 flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors"
                data-testid="report-no-show-btn"
                @click="showNoShowDialog = true"
              >
                <AlertTriangle class="w-4 h-4" />
                Signaler une absence
              </button>
            </div>

            <!-- Messagerie fermée avant le paiement (anti-contournement) -->
            <div
              v-if="messagingLockedText"
              class="flex items-start gap-2.5 rounded-[10px] border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-500"
              data-testid="booking-chat-locked"
            >
              <Lock class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
              <span>{{ messagingLockedText }}</span>
            </div>

            <!-- Rating section (completed only) -->
            <div
              v-if="booking.status === BookingStatus.COMPLETED"
              ref="ratingSection"
              class="mt-1 scroll-mt-4"
            >
              <BookingRatingForm
                v-if="booking.can_rate"
                :booking-id="booking.id"
                :rated-name="ratedName"
                @rated="handleRatingSubmitted"
              />

              <section
                v-else-if="booking.my_rating"
                class="bg-white rounded-[10px] border border-gray-200 p-5"
                data-testid="booking-my-rating-readonly"
              >
                <h2 class="text-sm font-semibold text-gray-700">Votre évaluation</h2>
                <p class="mt-1 text-sm text-gray-500">Vous avez noté {{ booking.my_rating.rated.name }}.</p>

                <div class="mt-3 flex items-center gap-1.5 text-amber-400">
                  <Star
                    v-for="star in 5"
                    :key="`readonly-star-${star}`"
                    class="h-5 w-5"
                    :fill="booking.my_rating.score >= star ? 'currentColor' : 'none'"
                  />
                  <span class="ml-2 text-sm text-gray-600">{{ booking.my_rating.score }}/5</span>
                </div>

                <p
                  v-if="booking.my_rating.comment"
                  class="mt-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-700"
                >
                  {{ booking.my_rating.comment }}
                </p>
              </section>
            </div>
          </div>

          <!-- Colonne latérale : « Et ensuite ? » puis l'argent -->
          <!-- Chat à droite et infos sur une colonne : « Et ensuite ? » remonte en tête. -->
        <aside class="booking-side space-y-4 @3xl/info:sticky @3xl/info:top-3">
            <BookingNextBlock :next="view.next" :now="nowTimestamp" />

            <!-- Financial section (cash bookings only) -->
            <div v-if="booking.type_contenu !== 'UGC'" class="bg-white rounded-[10px] border border-gray-200 p-4">
              <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ isFace ? 'Votre rémunération' : 'À payer' }}</h2>
              <BookingPricingBreakdown
                :tarif-base="booking.tarif_base"
                :role="isFace ? 'face' : 'producer'"
                :montant-face-recoit="booking.montant_face_recoit"
                :montant-total-producteur="booking.montant_total_producteur"
              />
            </div>
          </aside>
        </div>
      </div>

      <!-- Booking Chat Section (AC6: only for eligible statuses ; UGC dès Accepted — 3.2).
           Sur grand écran, colonne de droite collée en haut, à hauteur d'écran. -->
      <section
        v-if="canShowChat"
        ref="chatSection"
        class="scroll-mt-4 @4xl/page:sticky @4xl/page:top-6"
        data-testid="booking-chat-section"
      >
        <!-- Face : proposition d'activer les notifications pour ne pas rater les messages -->
        <PushMomentCard
          v-if="isFace"
          class="mb-3"
          :producer-name="producerName === 'Producteur' ? null : producerName"
          scope="booking"
          :scope-id="booking.id"
        />
        <div class="booking-chat-box bg-white rounded-[24px] shadow-sm overflow-hidden">
          <BookingChat :booking="booking" :current-user-id="currentUserId" />
        </div>
      </section>

      <!-- Barre d'action collée en bas (téléphone) -->
      <BookingActionBar
        v-if="primaryAction"
        :headline="view.next.headline"
        :sub="actionBarSub"
        :action="primaryAction"
        @action="handlePrimaryAction"
      />
    </div>

    <!-- Payment overlay -->
    <PaymentOverlay
      v-if="booking"
      v-model="showPaymentOverlay"
      :booking="booking"
    />

    <!-- UGC engagement modal (Face accept — 2.4) -->
    <UgcEngagementModal
      v-if="booking"
      :is-open="showEngagementModal"
      :nombre-videos="booking.nombre_videos ?? null"
      :nom-produit="booking.nom_produit"
      :type-compensation="booking.type_compensation"
      :montant-face-recoit="booking.montant_face_recoit"
      :is-submitting="isAccepting"
      @confirm="handleEngagementConfirm"
      @cancel="showEngagementModal = false"
    />

    <!-- UGC commission payment tunnel -->
    <UgcPaymentOverlay
      v-if="booking && canPayUgcCommission"
      v-model="showUgcPaymentOverlay"
      kind="booking"
      :owner-id="booking.id"
      :amount="booking.montant_total_producteur ?? 0"
    />

    <CancellationDialog
      v-if="booking"
      :booking="booking"
      :is-open="showCancellationDialog"
      :is-cancelling="isCancelling"
      :is-face="isFace"
      :is-late-cancel="isLateProducerCancel"
      :server-error="cancelFieldError"
      @confirm="handleCancel"
      @cancel="showCancellationDialog = false; cancelFieldError = null"
    />

    <!-- Confirmation « Produit reçu » (3.4, D-3.4.c) — chrono 7j irréversible.
         Spec réception : 1-2 photos du produit reçu obligatoires avant de confirmer. -->
    <ConfirmModal
      :is-open="showReceiptModal"
      title="Confirmer la réception ?"
      :message="receiptModalMessage"
      confirm-text="Oui, j'ai reçu le produit"
      cancel-text="Pas encore"
      variant="warning"
      :confirm-disabled="receiptPhotos.length === 0"
      @confirm="handleConfirmReceipt"
      @cancel="closeReceiptModal"
    >
      <ProductPhotosUpload
        v-model="receiptPhotos"
        :max-photos="2"
        title="Photos du produit reçu"
        hint="(obligatoire, 1 à 2)"
      />
    </ConfirmModal>

    <!-- Récapitulatif avant d'accepter un booking cash (lot D9) -->
    <Teleport to="body">
      <div
        v-if="showAcceptDialog && booking"
        class="booking-dialog fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        data-testid="accept-recap-dialog"
        @click.self="closeAcceptDialog"
      >
        <div
          class="booking-dialog-card bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="accept-recap-title"
        >
          <h3 id="accept-recap-title" class="text-lg font-semibold text-gray-900 mb-1">Accepter ce booking</h3>
          <p class="text-sm text-gray-500 mb-4">Vérifiez les informations avant de vous engager.</p>

          <dl class="space-y-2 text-sm mb-4" data-testid="accept-recap-summary">
            <div v-if="acceptRecapDates" class="flex justify-between gap-4">
              <dt class="text-gray-500">Date</dt>
              <dd class="font-medium text-gray-900 text-right" data-testid="accept-recap-dates">{{ acceptRecapDates }}</dd>
            </div>
            <div v-if="booking.lieu" class="flex justify-between gap-4">
              <dt class="text-gray-500">Lieu</dt>
              <dd class="font-medium text-gray-900 text-right" data-testid="accept-recap-lieu">{{ booking.lieu }}</dd>
            </div>
            <div v-if="booking.duree_heures" class="flex justify-between gap-4">
              <dt class="text-gray-500">Durée</dt>
              <dd class="font-medium text-gray-900 text-right" data-testid="accept-recap-duree">max {{ booking.duree_heures }}h</dd>
            </div>
            <div class="flex justify-between gap-4 border-t border-gray-100 pt-2">
              <dt class="text-gray-500">Montant net que vous recevrez</dt>
              <dd class="font-semibold text-gray-900 text-right" data-testid="accept-recap-amount">{{ formatMoney(booking.montant_face_recoit) }}</dd>
            </div>
          </dl>

          <div class="rounded-lg bg-gray-50 p-3 mb-5" data-testid="accept-recap-rules">
            <p class="text-xs font-semibold text-gray-700 mb-2">Après votre acceptation</p>
            <ul class="list-disc pl-4 space-y-1 text-xs text-gray-600">
              <li>Le Producteur dispose de 24 h pour payer ; sans paiement dans ce délai, le booking expire.</li>
              <li>Vous pouvez annuler jusqu'à 24 h avant le jour du tournage ; passé ce délai, l'annulation n'est plus possible.</li>
              <li>Une annulation de votre part entraîne une pénalité d'un point sur votre note moyenne, et le remboursement du Producteur s'il a déjà payé.</li>
              <li>Vos autres demandes en attente sur les mêmes dates sont refusées automatiquement.</li>
            </ul>
          </div>

          <div class="flex gap-3 justify-end">
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
              :disabled="isAccepting"
              data-testid="accept-recap-back"
              @click="closeAcceptDialog"
            >
              Retour
            </button>
            <button
              ref="acceptConfirmButton"
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-semibold text-white bg-weact-500 hover:bg-weact-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isAccepting"
              data-testid="accept-recap-confirm"
              @click="handleAcceptDialogConfirm"
            >
              <Loader2 v-if="isAccepting" class="w-4 h-4 animate-spin inline mr-1" />
              Accepter le booking
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- No-show confirmation dialog -->
    <Teleport to="body">
      <div
        v-if="showNoShowDialog"
        class="booking-dialog fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="showNoShowDialog = false"
      >
        <div class="booking-dialog-card bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Signaler une absence</h3>
          <p class="text-sm text-gray-500 mb-4">
            Les fonds restent en séquestre pendant 72 h. La Face peut contester ce signalement ; un administrateur tranche alors.
            Sans contestation, le montant total est crédité automatiquement dans votre portefeuille et une pénalité est appliquée à la Face.
          </p>
          <div class="flex gap-3 justify-end">
            <button
              class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
              :disabled="isReportingNoShow"
              @click="showNoShowDialog = false"
            >
              Annuler
            </button>
            <button
              class="rounded-lg px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isReportingNoShow"
              data-testid="confirm-no-show-btn"
              @click="handleReportNoShow"
            >
              <Loader2 v-if="isReportingNoShow" class="w-4 h-4 animate-spin inline mr-1" />
              Confirmer le signalement
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Contest dialog (Face) -->
    <Teleport to="body">
      <div
        v-if="showContestDialog"
        class="booking-dialog fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        data-testid="contest-dialog"
        @click.self="closeContestDialog"
      >
        <div class="booking-dialog-card bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Contester</h3>
          <p class="text-sm text-gray-500 mb-4">
            Expliquez pourquoi vous contestez. Les fonds restent bloqués et un administrateur tranchera.
          </p>
          <label for="contest-message" class="block text-sm font-medium text-gray-700 mb-1">
            Votre message
          </label>
          <textarea
            id="contest-message"
            v-model="contestMessage"
            rows="4"
            :maxlength="CONTEST_MAX_LENGTH"
            data-testid="contest-message"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-weact focus:ring-2 focus:ring-weact/20 outline-none"
            placeholder="Décrivez ce qui s'est passé..."
          />
          <p class="mt-1 mb-4 text-xs text-gray-400">
            {{ contestMessage.trim().length }}/{{ CONTEST_MAX_LENGTH }} (minimum {{ CONTEST_MIN_LENGTH }} caractères)
          </p>
          <div class="flex gap-3 justify-end">
            <button
              class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
              :disabled="isContesting"
              @click="closeContestDialog"
            >
              Annuler
            </button>
            <button
              class="rounded-lg px-4 py-2 text-sm font-semibold text-white bg-weact hover:bg-weact/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="!canSubmitContest"
              data-testid="confirm-contest-btn"
              @click="handleContest"
            >
              <Loader2 v-if="isContesting" class="w-4 h-4 animate-spin inline mr-1" />
              Envoyer la contestation
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Refuse dialog overlay -->
    <Teleport to="body">
      <div
        v-if="showRefuseDialog"
        class="booking-dialog fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="closeRefuseDialog"
      >
        <div class="booking-dialog-card bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Refuser le booking</h3>
          <p class="text-sm text-gray-500 mb-4">
            Êtes-vous sûr de vouloir refuser cette demande ?
          </p>

          <div v-if="showReasonField" class="mb-4">
            <label for="refuse-reason" class="block text-sm font-medium text-gray-700 mb-1">
              Raison du refus
            </label>
            <textarea
              id="refuse-reason"
              v-model="refuseReason"
              rows="3"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-weact focus:ring-2 focus:ring-weact/20 outline-none"
              placeholder="Expliquez la raison..."
              maxlength="1000"
              data-testid="refuse-reason-textarea"
              :aria-invalid="refuseContactError || refuseFieldError ? 'true' : undefined"
            />
            <p
              v-if="refuseContactError || refuseFieldError"
              data-testid="refuse-reason-error"
              class="mt-1 text-sm text-red-600"
              role="alert"
            >
              {{ refuseContactError ?? refuseFieldError }}
            </p>
          </div>

          <!-- Show optional reason field toggle -->
          <button
            v-if="!showReasonField"
            class="text-sm text-gray-500 hover:text-gray-700 underline mb-4 block"
            @click="showReasonField = true"
          >
            Ajouter une raison (optionnel)
          </button>

          <div class="flex gap-3 justify-end">
            <button
              class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
              :disabled="isRefusing"
              @click="closeRefuseDialog"
            >
              Annuler
            </button>
            <button
              class="rounded-lg px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isRefusing || !!refuseContactError"
              data-testid="confirm-refuse-button"
              @click="handleRefuse"
            >
              <Loader2 v-if="isRefusing" class="w-4 h-4 animate-spin inline mr-1" />
              Confirmer le refus
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* Hauteur du chat : écran de téléphone, 600 px au-delà, puis toute la hauteur
   disponible quand il occupe la colonne de droite : écran moins barre du haut (4rem),
   marges de la zone principale et de la page, lien « Retour » (9.25rem). */
.booking-chat-box {
  height: calc(100dvh - 6.5rem);
}
@media (min-width: 640px) {
  .booking-chat-box {
    height: 600px;
  }
}
@container page (min-width: 56rem) {
  .booking-chat-box {
    height: calc(100dvh - 13.25rem);
    min-height: 28rem;
  }
}
/* Chat affiché dans la colonne de droite : « Ouvrir le chat » est redondant en en-tête. */
@container page (min-width: 56rem) {
  .booking-primary-action--chat {
    display: none;
  }
}
/* Chat à droite et infos sur une seule colonne : « Et ensuite ? » passe en tête. */
@container page (min-width: 56rem) {
  @container info (max-width: 47.99rem) {
    .booking-side {
      order: -1;
    }
  }
}
/* Lot C6 : mouvement. Seuls transform / opacity / filter s'animent. */
.booking-press {
  transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1);
}

.booking-press:not(:disabled):active {
  transform: scale(0.97);
}

/* Fenêtres : fondu du voile + carte centrée qui passe de .96 à 1 (pas d'origine liée au déclencheur). */
.booking-dialog {
  animation: booking-dialog-fade 200ms ease-out;
}

.booking-dialog-card {
  animation: booking-dialog-pop 200ms cubic-bezier(0.23, 1, 0.32, 1);
}

@keyframes booking-dialog-fade {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes booking-dialog-pop {
  from {
    opacity: 0;
    transform: scale(0.96);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .booking-press {
    transition: none;
  }

  .booking-press:not(:disabled):active {
    transform: none;
  }

  .booking-dialog-card {
    animation-name: booking-dialog-fade;
  }
}
</style>

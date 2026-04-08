<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Calendar,
  Clock,
  Film,
  MessageSquare,
  Wallet,
  Loader2,
  AlertCircle,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Star,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useBookingDetail, useBookingActions } from '@/features/booking/composables'
import {
  BookingTimeline,
  BookingStatusBadge,
  PaymentOverlay,
  BookingPricingBreakdown,
  BookingChat,
  CancellationDialog,
  BookingRatingForm,
} from '@/features/booking/components'
import {
  BookingStatus,
  CHAT_VIEW_STATUSES,
  CANCELLABLE_BY_FACE_STATUSES,
  CANCELLABLE_BY_PRODUCER_STATUSES,
  getCancellationReasonLabel,
  type BookingCancellationPayload,
  type BookingStatusType,
  type BookingRating,
} from '@/features/booking/types'
import RatingDisplay from '@/components/RatingDisplay.vue'
import { useToast } from '@/composables/useToast'

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
  error: actionError,
  accept,
  refuse,
  confirm,
  cancel,
  reportNoShow,
  clearError,
} = useBookingActions()

// Payment overlay state
const showPaymentOverlay = ref(false)

// Refuse dialog state
const showRefuseDialog = ref(false)
const refuseReason = ref('')
const showReasonField = ref(false)

// Cancellation dialog state
const showCancellationDialog = ref(false)
// No-show dialog state
const showNoShowDialog = ref(false)
const nowTimestamp = ref(Date.now())
const hasExpiryRealtimeListener = ref(false)
let countdownTicker: ReturnType<typeof setInterval> | null = null

// Get booking ID from route params
const bookingId = computed(() => {
  const id = route.params.id
  if (typeof id !== 'string') return null
  const parsed = parseInt(id, 10)
  return Number.isNaN(parsed) ? null : parsed
})

// Producer data from booking
const producerName = computed(() => {
  const producer = booking.value?.producer
  if (!producer?.userable) return 'Producteur'
  const userable = producer.userable as { display_name?: string }
  return userable.display_name || 'Producteur'
})

const producerAvatarUrl = computed(() => {
  const producer = booking.value?.producer
  if (!producer?.userable) return null
  const userable = producer.userable as { profile_photo_url?: string | null; thumbnail_url?: string | null }
  return userable.thumbnail_url || userable.profile_photo_url || null
})

const producerInitials = computed(() => producerName.value.charAt(0).toUpperCase())

const producerRating = computed(() => {
  const producer = booking.value?.producer
  if (!producer?.userable) return null
  return (producer.userable as { average_rating?: number | null }).average_rating ?? null
})

const producerRatingsCount = computed(() => {
  const producer = booking.value?.producer
  if (!producer?.userable) return 0
  return (producer.userable as { ratings_count?: number }).ratings_count ?? 0
})

const faceName = computed(() => {
  const face = booking.value?.face
  if (!face?.userable) return 'Face'
  const userable = face.userable as { prenom?: string; nom?: string; username?: string }
  const fullName = `${userable.prenom ?? ''} ${userable.nom ?? ''}`.trim()
  return fullName || userable.username || 'Face'
})

const ratedName = computed(() => (isFace.value ? producerName.value : faceName.value))

const canCancelBooking = computed(() => {
  if (!booking.value) return false

  if (isFace.value) {
    return CANCELLABLE_BY_FACE_STATUSES.includes(booking.value.status)
  }

  return CANCELLABLE_BY_PRODUCER_STATUSES.includes(booking.value.status)
})

// No-show report visibility (Producer only, paid, past date_debut)
const canReportNoShow = computed(() => {
  if (!booking.value) return false
  if (isFace.value) return false
  if (booking.value.status !== BookingStatus.PAID) return false
  return new Date(booking.value.date_debut).getTime() < nowTimestamp.value
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

const isBeforeShootingDate = computed<boolean>(() => {
  if (!booking.value?.date_debut) return false
  return new Date(booking.value.date_debut).getTime() > nowTimestamp.value
})

const paymentDeadline = computed<Date | null>(() => {
  if (!booking.value?.accepted_at) return null
  return new Date(new Date(booking.value.accepted_at).getTime() + 24 * 60 * 60 * 1000)
})

const shouldShowPaymentDeadline = computed<boolean>(() => {
  return !isFace.value
    && booking.value?.status === BookingStatus.ACCEPTED
    && !!booking.value.accepted_at
})

const isPaymentOverdue = computed<boolean>(() => {
  if (!paymentDeadline.value) return false
  return paymentDeadline.value.getTime() <= nowTimestamp.value
})

const formattedPaymentDeadline = computed<string>(() => {
  if (!paymentDeadline.value) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(paymentDeadline.value)
})

/**
 * FORMAT HELPERS
 */
function formatDate(dateString: string): string {
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
    router.push({ name: 'face-dashboard' })
  }
}

async function handleAccept(): Promise<void> {
  if (!booking.value) return
  clearError()

  const result = await accept(booking.value.id)
  if (result) {
    booking.value = result
    toast.success('Booking accepté avec succès !')
  } else {
    toast.error(actionError.value || 'Erreur lors de l\'acceptation')
  }
}

function openRefuseDialog(): void {
  refuseReason.value = ''
  showReasonField.value = false
  showRefuseDialog.value = true
}

function closeRefuseDialog(): void {
  showRefuseDialog.value = false
  refuseReason.value = ''
  showReasonField.value = false
}

async function handleRefuse(): Promise<void> {
  if (!booking.value) return

  clearError()
  const result = await refuse(booking.value.id, refuseReason.value || undefined)
  if (result) {
    booking.value = result
    closeRefuseDialog()
    toast.success('Booking refusé')
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

  const result = await cancel(booking.value.id, payload.reason, payload.customReason)
  if (result) {
    booking.value = result
    showCancellationDialog.value = false
    toast.success('Booking annulé')
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
    toast.success('Absence signalée. Le montant a été crédité dans votre portefeuille.')
  } else {
    toast.error(actionError.value || 'Erreur lors du signalement')
  }
}

function handleRatingSubmitted(rating: BookingRating): void {
  if (!booking.value) return

  booking.value.my_rating = rating
  booking.value.can_rate = false
  toast.success('Évaluation envoyée avec succès')
}

async function handlePaymentSuccess(): Promise<void> {
  showPaymentOverlay.value = false
  if (bookingId.value) {
    await fetchBooking(bookingId.value)
    toast.success('Paiement confirmé !')
  }
}

interface EchoChannel {
  listen: (event: string, callback: () => void) => EchoChannel
  stopListening: (event: string) => EchoChannel
}

async function attachExpiryRealtimeListener(): Promise<void> {
  if (hasExpiryRealtimeListener.value || bookingId.value === null) return

  try {
    const { echo } = await import('@/plugins/echo')
    ;(echo.private(`booking.${bookingId.value}`) as EchoChannel)
      .listen('.booking.expired', async () => {
        if (bookingId.value !== null) {
          await fetchBooking(bookingId.value)
        }
      })

    hasExpiryRealtimeListener.value = true
  } catch {
    hasExpiryRealtimeListener.value = false
  }
}

async function detachExpiryRealtimeListener(): Promise<void> {
  if (!hasExpiryRealtimeListener.value || bookingId.value === null) return

  try {
    const { echo } = await import('@/plugins/echo')
    // Stop only the expiry listener — do not leave the channel, as other
    // components (BookingChat) may subscribe to the same private channel.
    ;(echo.private(`booking.${bookingId.value}`) as EchoChannel)
      .stopListening('.booking.expired')
  } catch {
    // Ignore realtime cleanup errors.
  } finally {
    hasExpiryRealtimeListener.value = false
  }
}

/**
 * LIFECYCLE
 */
watch(
  () => booking.value?.status,
  async (status) => {
    if (status === BookingStatus.ACCEPTED) {
      await attachExpiryRealtimeListener()
    } else {
      await detachExpiryRealtimeListener()
    }
  },
)

onMounted(async () => {
  if (bookingId.value) {
    await fetchBooking(bookingId.value)
  }

  countdownTicker = setInterval(() => {
    nowTimestamp.value = Date.now()
  }, 60000)
})

onUnmounted(() => {
  if (countdownTicker !== null) {
    clearInterval(countdownTicker)
    countdownTicker = null
  }

  void detachExpiryRealtimeListener()
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-6">
    <!-- Back button -->
    <button
      class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-6 transition-colors"
      @click="goBack"
    >
      <ArrowLeft class="w-4 h-4" />
      Retour
    </button>

    <!-- Loading state -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
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
    <template v-else-if="booking">
      <!-- Header: Status badge + title -->
      <div class="flex items-center gap-3 mb-6">
        <BookingStatusBadge :status="booking.status" />
        <h1 class="text-xl font-bold text-gray-900">Demande de booking</h1>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: Timeline -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Progression</h2>
            <BookingTimeline :status="booking.status" :cancellation-reason="booking.cancellation_reason" />
          </div>
        </div>

        <!-- Right column: Details -->
        <div class="lg:col-span-2 space-y-5">
          <!-- Producer card -->
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Producteur</h2>
            <div class="flex items-center gap-3">
              <div
                v-if="producerAvatarUrl"
                class="w-12 h-12 rounded-full bg-cover bg-center border border-gray-200"
                :style="{ backgroundImage: `url(${producerAvatarUrl})` }"
              />
              <div v-else class="w-12 h-12 rounded-full bg-weact/10 text-weact flex items-center justify-center font-semibold text-lg">
                {{ producerInitials }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 truncate">{{ producerName }}</p>
                <RatingDisplay
                  v-if="producerRating !== null"
                  :average-rating="producerRating"
                  :review-count="producerRatingsCount"
                />
                <p v-else class="text-xs text-gray-400">Pas encore noté</p>
              </div>
            </div>
          </div>

          <!-- Booking details -->
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Détails</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="flex items-center gap-2.5">
                <Calendar class="w-4 h-4 text-gray-400 shrink-0" />
                <div>
                  <p class="text-xs text-gray-500">Date de début</p>
                  <p class="text-sm font-medium text-gray-900">{{ formatDate(booking.date_debut) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2.5">
                <Calendar class="w-4 h-4 text-gray-400 shrink-0" />
                <div>
                  <p class="text-xs text-gray-500">Date de fin</p>
                  <p class="text-sm font-medium text-gray-900">{{ formatDate(booking.date_fin) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2.5">
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

          <!-- Financial section -->
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Finances</h2>
            <BookingPricingBreakdown
              :tarif-base="booking.tarif_base"
              :role="isFace ? 'face' : 'producer'"
              :montant-face-recoit="booking.montant_face_recoit"
              :montant-total-producteur="booking.montant_total_producteur"
            />
          </div>

          <!-- Cancellation reason (if refused/cancelled) -->
          <div
            v-if="booking.cancellation_reason"
            class="bg-red-50 rounded-xl border border-red-200 p-5"
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

          <div
            v-if="shouldShowPaymentDeadline"
            class="rounded-xl border px-4 py-3 text-sm"
            :class="isPaymentOverdue
              ? 'border-red-200 bg-red-50 text-red-800'
              : 'border-amber-200 bg-amber-50 text-amber-800'"
          >
            <span v-if="isPaymentOverdue">Délai expiré — ce booking sera bientôt annulé</span>
            <span v-else>Paiement requis avant {{ formattedPaymentDeadline }}</span>
          </div>

          <!-- Confirm button (double-confirmation) -->
          <div v-if="confirmLabel" class="relative group">
            <button
              :disabled="isConfirming || isBeforeShootingDate"
              class="w-full flex items-center justify-center gap-2 rounded-lg bg-weact px-4 py-3 text-sm font-semibold text-white hover:bg-weact/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :title="isBeforeShootingDate ? 'La confirmation n\'est possible qu\'à partir du jour du tournage' : undefined"
              @click="handleConfirm"
            >
              <Loader2 v-if="isConfirming" class="w-4 h-4 animate-spin" />
              <CheckCircle v-else class="w-4 h-4" />
              {{ confirmLabel }}
            </button>
            <p v-if="isBeforeShootingDate" class="mt-2 text-sm text-amber-600">
              La confirmation n'est possible qu'à partir du jour du tournage.
            </p>
          </div>

          <!-- Booking cancellation -->
          <div v-if="canCancelBooking" class="flex gap-3">
            <button
              class="flex-1 flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors"
              @click="showCancellationDialog = true"
            >
              <XCircle class="w-4 h-4" />
              Annuler le booking
            </button>
          </div>

          <!-- No-show report (Producer only) -->
          <div v-if="canReportNoShow" class="flex gap-3">
            <button
              class="flex-1 flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors"
              data-testid="report-no-show-btn"
              @click="showNoShowDialog = true"
            >
              <AlertTriangle class="w-4 h-4" />
              Signaler une absence
            </button>
          </div>

          <!-- Action buttons -->
          <div v-if="booking.can_accept || booking.can_refuse || booking.can_pay" class="flex gap-3">
            <button
              v-if="booking.can_pay && !isPaymentOverdue"
              class="flex-1 flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors"
              @click="showPaymentOverlay = true"
            >
              <Wallet class="w-4 h-4" />
              Payer maintenant
            </button>
            <button
              v-if="booking.can_accept"
              :disabled="isAccepting"
              class="flex-1 flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              @click="handleAccept"
            >
              <Loader2 v-if="isAccepting" class="w-4 h-4 animate-spin" />
              <CheckCircle v-else class="w-4 h-4" />
              Accepter
            </button>
            <button
              v-if="booking.can_refuse"
              :disabled="isRefusing"
              class="flex-1 flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              @click="openRefuseDialog"
            >
              <XCircle class="w-4 h-4" />
              Refuser
            </button>
          </div>

          <!-- Rating section (completed only) -->
          <div
            v-if="booking.status === BookingStatus.COMPLETED"
            class="mt-1"
          >
            <BookingRatingForm
              v-if="booking.can_rate"
              :booking-id="booking.id"
              :rated-name="ratedName"
              @rated="handleRatingSubmitted"
            />

            <section
              v-else-if="booking.my_rating"
              class="bg-white rounded-xl border border-gray-200 p-5"
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
      </div>

      <!-- Booking Chat Section (AC6: only for eligible statuses) -->
      <section
        v-if="CHAT_VIEW_STATUSES.includes(booking.status)"
        class="mt-6"
        data-testid="booking-chat-section"
      >
        <div class="bg-white rounded-[24px] shadow-sm overflow-hidden h-[calc(100dvh-6.5rem)] sm:h-[600px]">
          <BookingChat :booking="booking" :current-user-id="currentUserId" />
        </div>
      </section>
    </template>

    <!-- Payment overlay -->
    <PaymentOverlay
      v-if="booking"
      v-model="showPaymentOverlay"
      :booking="booking"
      @payment-success="handlePaymentSuccess"
    />

    <CancellationDialog
      v-if="booking"
      :booking="booking"
      :is-open="showCancellationDialog"
      :is-cancelling="isCancelling"
      :is-face="isFace"
      @confirm="handleCancel"
      @cancel="showCancellationDialog = false"
    />

    <!-- No-show confirmation dialog -->
    <Teleport to="body">
      <div
        v-if="showNoShowDialog"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="showNoShowDialog = false"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Signaler une absence</h3>
          <p class="text-sm text-gray-500 mb-4">
            Cette action est irréversible. Le montant total sera crédité dans votre portefeuille et une pénalité sera appliquée à la Face.
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

    <!-- Refuse dialog overlay -->
    <Teleport to="body">
      <div
        v-if="showRefuseDialog"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="closeRefuseDialog"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
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
            />
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
              :disabled="isRefusing"
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

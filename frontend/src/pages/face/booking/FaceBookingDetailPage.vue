<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
  CheckCircle,
  XCircle,
} from 'lucide-vue-next'
import { useBookingDetail, useBookingActions } from '@/features/booking/composables'
import { BookingTimeline, BookingStatusBadge, PaymentOverlay } from '@/features/booking/components'
import { BookingStatus, type BookingStatusType } from '@/features/booking/types'
import RatingDisplay from '@/components/RatingDisplay.vue'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const { booking, isLoading, error, notFound, fetchBooking, refresh } = useBookingDetail()
const { isAccepting, isRefusing, error: actionError, accept, refuse, clearError } = useBookingActions()

// Payment overlay state
const showPaymentOverlay = ref(false)

// Refuse dialog state
const showRefuseDialog = ref(false)
const refuseReason = ref('')
const showReasonField = ref(false)

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

// Whether refuse needs a mandatory reason (paid bookings)
const refuseNeedsReason = computed(() => {
  return booking.value?.status === BookingStatus.PAID
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

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
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

  // Validate reason if needed
  if (refuseNeedsReason.value && !refuseReason.value.trim()) {
    toast.error('Veuillez indiquer la raison du refus')
    return
  }

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

async function handlePaymentSuccess(): Promise<void> {
  showPaymentOverlay.value = false
  if (bookingId.value) {
    await fetchBooking(bookingId.value)
    toast.success('Paiement confirmé !')
  }
}

/**
 * LIFECYCLE
 */
onMounted(() => {
  if (bookingId.value) {
    fetchBooking(bookingId.value)
  }
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
        <h1 class="text-xl font-bold text-gray-900">Demande de booking #{{ booking.id }}</h1>
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
                  :rating="producerRating"
                  :count="producerRatingsCount"
                  size="sm"
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
                  <p class="text-sm font-medium text-gray-900">{{ booking.duree_heures }}h</p>
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
            <div class="space-y-2">
              <div class="flex justify-between items-center text-sm">
                <span class="text-gray-500">Tarif de base</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(booking.tarif_base) }}</span>
              </div>
              <div class="flex justify-between items-center text-sm">
                <span class="text-gray-500">Commission plateforme (-15%)</span>
                <span class="text-red-500">-{{ formatCurrency(booking.tarif_base - booking.montant_face_recoit) }}</span>
              </div>
              <div class="border-t border-gray-100 pt-2 mt-2">
                <div class="flex justify-between items-center">
                  <span class="text-sm font-semibold text-gray-700">Vous recevrez</span>
                  <span class="text-lg font-bold text-emerald-600">{{ formatCurrency(booking.montant_face_recoit) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Cancellation reason (if refused/cancelled) -->
          <div
            v-if="booking.cancellation_reason"
            class="bg-red-50 rounded-xl border border-red-200 p-5"
          >
            <h2 class="text-sm font-semibold text-red-700 mb-2">Raison du refus</h2>
            <p class="text-sm text-red-600">{{ booking.cancellation_reason }}</p>
          </div>

          <!-- Action buttons -->
          <div v-if="booking.can_accept || booking.can_refuse || booking.can_pay" class="flex gap-3">
            <button
              v-if="booking.can_pay"
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
        </div>
      </div>
    </template>

    <!-- Payment overlay -->
    <PaymentOverlay
      v-if="booking"
      v-model="showPaymentOverlay"
      :booking="booking"
      @payment-success="handlePaymentSuccess"
    />

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
            {{ refuseNeedsReason
              ? 'Ce booking a déjà été payé. Veuillez indiquer la raison de votre refus.'
              : 'Êtes-vous sûr de vouloir refuser cette demande ?'
            }}
          </p>

          <div v-if="refuseNeedsReason || showReasonField" class="mb-4">
            <label for="refuse-reason" class="block text-sm font-medium text-gray-700 mb-1">
              Raison du refus{{ refuseNeedsReason ? ' *' : '' }}
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

          <!-- Show optional reason field toggle for non-paid -->
          <button
            v-if="!refuseNeedsReason && !showReasonField"
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
              :disabled="isRefusing || (refuseNeedsReason && !refuseReason.trim())"
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

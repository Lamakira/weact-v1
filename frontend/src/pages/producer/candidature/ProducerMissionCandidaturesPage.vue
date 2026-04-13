<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Loader2, AlertCircle, CheckCircle } from 'lucide-vue-next'
import { ProducerCandidaturesSection } from '@/features/candidature/components'
import { missionApi } from '@/features/mission/services/missionApi'
import { useMissionPayment } from '@/features/mission/composables'
import { useToast } from '@/composables/useToast'
import type { Mission } from '@/features/mission/types'

/**
 * Router and route
 */
const route = useRoute()
const router = useRouter()

/**
 * State
 */
const mission = ref<Mission | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)
const paymentSuccessBanner = ref(false)
// FIX-19.3 — when the mission is stuck in pending_payment but the backend
// reports no trackable FedaPay transaction, show a dedicated error banner
// instead of the "Paiement en attente de confirmation..." spinner.
const paymentInitializationFailed = ref(false)
const paymentStatusUnavailable = ref(false)
let isPageActive = true
let paymentStateRequestId = 0

/**
 * Computed: Mission ID from route params
 */
const missionId = computed(() => route.params.id as string)

const { startPolling, stopPolling } = useMissionPayment(0, missionId.value || '')
const toast = useToast()

/**
 * Decide whether to start polling the payment status and show the "pending"
 * banner. Calls the payment-status endpoint first so we can distinguish a
 * genuine pending checkout (is_trackable=true → poll + show banner) from a
 * stuck-pending record with no FedaPay transaction (is_trackable=false →
 * surface the initialization-failed banner instead). (FIX-19.3 AC #1, #2.)
 */
async function evaluatePendingPaymentState(): Promise<void> {
  if (!missionId.value || !mission.value) return

  const requestId = ++paymentStateRequestId

  try {
    const response = await missionApi.getPaymentStatus(missionId.value)
    if (!isPageActive || requestId !== paymentStateRequestId || !mission.value) return

    const data = response.data
    paymentStatusUnavailable.value = false

    if (data.status === 'paid') {
      paymentSuccessBanner.value = true
      paymentInitializationFailed.value = false
      stopPolling()

      if (mission.value.status === 'pending_payment' || data.mission_status !== mission.value.status) {
        await fetchMission()
      }

      return
    }

    if (data.mission_status !== mission.value.status) {
      paymentInitializationFailed.value = false
      stopPolling()
      await fetchMission()
      return
    }

    if (data.is_trackable) {
      paymentInitializationFailed.value = false
      paymentStatusUnavailable.value = false
      startPolling(missionId.value, async () => {
        if (!isPageActive) return
        paymentSuccessBanner.value = true
        await fetchMission()
      })
    } else {
      // No trackable transaction to poll → do NOT start polling and do NOT
      // render the "Paiement en attente de confirmation..." banner. Tell the
      // producer the initialization failed and invite a retry. This covers
      // every non-trackable payment state on a pending_payment mission
      // (pending-without-transaction, failed, refunded, or missing row) — the
      // paid / mission_status-changed branches above already returned.
      paymentInitializationFailed.value = data.mission_status === 'pending_payment'
      paymentStatusUnavailable.value = false
      stopPolling()
    }
  } catch (err: unknown) {
    if (!isPageActive || requestId !== paymentStateRequestId) return

    console.error('Failed to fetch payment status:', err)
    paymentInitializationFailed.value = false
    paymentStatusUnavailable.value = true
    stopPolling()
  }
}

/**
 * Fetch mission details for the header.
 * Evaluates whether the mission payment is trackable before auto-starting
 * polling.
 */
async function fetchMission(): Promise<void> {
  isLoading.value = true
  error.value = null

  if (!missionId.value) {
    error.value = 'ID de mission invalide'
    isLoading.value = false
    return
  }

  try {
    const response = await missionApi.getMission(missionId.value)
    if (!isPageActive) return

    mission.value = response.data

    if (mission.value.status === 'pending_payment') {
      await evaluatePendingPaymentState()
    } else {
      paymentInitializationFailed.value = false
      paymentStatusUnavailable.value = false
      stopPolling()
    }
  } catch (err: unknown) {
    if (!isPageActive) return

    console.error('Failed to fetch mission:', err)
    error.value = 'Impossible de charger les informations de la mission'
    paymentInitializationFailed.value = false
    paymentStatusUnavailable.value = false
    stopPolling()
  } finally {
    if (isPageActive) {
      isLoading.value = false
    }
  }
}

/**
 * Navigate back to missions list
 */
function goBack(): void {
  router.push({ name: 'producer-missions' })
}

/**
 * Handle checkout URL from selection confirmation.
 * Redirects to FedaPay checkout — FedaPay webhook handles the rest.
 */
function handleSelectionConfirmed(checkoutUrl: string): void {
  window.location.href = checkoutUrl
}

/**
 * Handle a failed selection/initiation attempt.
 * We re-fetch the mission so the UI reflects the post-compensation state
 * (mission back to published, or stuck pending_payment with is_trackable
 * false). (FIX-19.3.)
 */
async function handleSelectionFailed(message: string): Promise<void> {
  toast.error(message)
  await fetchMission()
}

/**
 * Lifecycle
 */
onMounted(() => {
  isPageActive = true
  void fetchMission()
})

onUnmounted(() => {
  isPageActive = false
  paymentStateRequestId += 1
  stopPolling()
})
</script>

<template>
  <div class="min-h-screen bg-background pb-8">
    <!-- Header Section -->
    <header class="border-b border-border bg-card">
      <div class="container mx-auto px-4 py-6 sm:px-6">
        <!-- Back Button -->
        <button
          type="button"
          class="mb-4 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Retour aux missions
        </button>

        <!-- Loading State for Header -->
        <div v-if="isLoading" class="flex items-center gap-3">
          <Loader2 class="h-5 w-5 animate-spin text-primary" />
          <span class="text-muted-foreground">Chargement...</span>
        </div>

        <!-- Error State for Header -->
        <div v-else-if="error" class="flex items-center gap-3 text-destructive">
          <AlertCircle class="h-5 w-5" />
          <span>{{ error }}</span>
        </div>

        <!-- Mission Header -->
        <template v-else-if="mission">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold text-foreground sm:text-3xl">
              {{ mission.titre }}
            </h1>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
              :class="{
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': mission.status === 'published',
                'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400': mission.status === 'pending_payment',
                'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': mission.status === 'closed',
                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': mission.status === 'completed',
              }"
            >
              {{ mission.status_label }}
            </span>
          </div>
          <p class="mt-1 text-muted-foreground">
            Candidatures reçues pour cette mission
          </p>
          <!-- Payment success banner -->
          <div
            v-if="paymentSuccessBanner"
            data-testid="mission-payment-success-banner"
            role="status"
            aria-live="polite"
            class="mt-3 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400"
          >
            <CheckCircle class="h-4 w-4 shrink-0" />
            Paiement confirmé ! Les fonds sont sécurisés en escrow. La mission est maintenant clôturée.
          </div>
          <!--
            Payment initialization failed banner (FIX-19.3 AC #4).
            Surfaced when the mission is flagged pending_payment but the
            backend reports no trackable FedaPay transaction, so we must NOT
            show the spinner-style "pending confirmation" banner.
          -->
          <div
            v-else-if="mission.status === 'pending_payment' && paymentInitializationFailed"
            data-testid="mission-payment-init-failed-banner"
            role="alert"
            aria-live="assertive"
            class="mt-3 flex items-start gap-2 rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400"
          >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              L'initialisation du paiement a échoué. Veuillez réessayer en reconfirmant votre sélection.
            </span>
          </div>
          <div
            v-else-if="mission.status === 'pending_payment' && paymentStatusUnavailable"
            data-testid="mission-payment-status-unavailable-banner"
            role="alert"
            aria-live="assertive"
            class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
          >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              Impossible de vérifier le statut du paiement pour le moment. Veuillez actualiser la page dans un instant.
            </span>
          </div>
          <!-- Pending payment banner (only when we have a trackable transaction to poll) -->
          <div
            v-else-if="mission.status === 'pending_payment'"
            data-testid="mission-payment-pending-banner"
            role="status"
            aria-live="polite"
            class="mt-3 flex items-center gap-2 rounded-lg bg-orange-50 px-4 py-2.5 text-sm text-orange-800 dark:bg-orange-900/20 dark:text-orange-400"
          >
            <Loader2 class="h-4 w-4 shrink-0 animate-spin" />
            Paiement en attente de confirmation...
          </div>
        </template>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto mt-6 px-4 sm:px-6">
      <!-- Show section only when we have the mission ID -->
      <ProducerCandidaturesSection
        v-if="!isLoading && !error && mission"
        :mission-id="missionId"
        :mission-budget="mission.budget"
        :mission-status="mission.status"
        :nombre-faces-voulu="mission.nombre_faces_voulu"
        :allow-retry-selection="mission.status === 'pending_payment' && paymentInitializationFailed"
        @selection-confirmed="handleSelectionConfirmed"
        @selection-failed="handleSelectionFailed"
      />
    </main>
  </div>
</template>

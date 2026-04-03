<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Loader2, AlertCircle, CheckCircle } from 'lucide-vue-next'
import { ProducerCandidaturesSection } from '@/features/candidature/components'
import { missionApi } from '@/features/mission/services/missionApi'
import { useMissionPayment } from '@/features/mission/composables'
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

/**
 * Computed: Mission ID from route params
 */
const missionId = computed(() => Number(route.params.id))

const { startPolling, stopPolling } = useMissionPayment(0, missionId.value)

/**
 * Fetch mission details for the header.
 * Starts polling automatically if mission is pending_payment.
 */
async function fetchMission(): Promise<void> {
  isLoading.value = true
  error.value = null

  // Validate missionId is a valid number
  if (isNaN(missionId.value) || missionId.value <= 0) {
    error.value = 'ID de mission invalide'
    isLoading.value = false
    return
  }

  try {
    const response = await missionApi.getMission(missionId.value)
    mission.value = response.data

    // Auto-start polling whenever mission is awaiting payment confirmation
    if (mission.value.status === 'pending_payment') {
      startPolling(missionId.value, async () => {
        paymentSuccessBanner.value = true
        await fetchMission()
      })
    }
  } catch (err: unknown) {
    console.error('Failed to fetch mission:', err)
    error.value = 'Impossible de charger les informations de la mission'
  } finally {
    isLoading.value = false
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
 * Lifecycle
 */
onMounted(() => {
  fetchMission()
})

onUnmounted(() => {
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
            class="mt-3 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400"
          >
            <CheckCircle class="h-4 w-4 shrink-0" />
            Paiement confirmé ! Les fonds sont sécurisés en escrow. La mission est maintenant clôturée.
          </div>
          <!-- Pending payment banner -->
          <div
            v-else-if="mission.status === 'pending_payment'"
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
        @selection-confirmed="handleSelectionConfirmed"
      />
    </main>
  </div>
</template>

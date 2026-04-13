<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  Loader2,
  AlertCircle,
  FileText,
  ChevronLeft,
  ChevronRight,
  Inbox,
  CheckSquare,
} from 'lucide-vue-next'
import { useProducerCandidatures, useAcceptCandidature, useRejectCandidature } from '../composables'
import { useMissionPayment } from '@/features/mission/composables'
import ProducerCandidatureCard from './ProducerCandidatureCard.vue'
import MissionSelectionSummary from '@/features/mission/components/MissionSelectionSummary.vue'
import StatusFilter from './StatusFilter.vue'
import { CandidatureStatusLabel } from '../types'
import type { CandidatureStatusType } from '../types'

/**
 * Props
 */
const props = defineProps<{
  missionId: string
  missionBudget?: number
  missionStatus?: string
  nombreFacesVoulu?: number
  allowRetrySelection?: boolean
}>()

const emit = defineEmits<{
  'selection-confirmed': [checkoutUrl: string]
  /**
   * Raised when a payment initiation attempt fails. The parent page can use
   * this hook to re-evaluate mission + payment state so the "Paiement en
   * attente de confirmation..." banner is never shown on a stuck-pending
   * mission. (FIX-19.3 AC #1, #4.)
   */
  'selection-failed': [message: string]
}>()

/**
 * Composable for candidatures list
 */
const {
  candidatures,
  isLoading,
  error,
  currentPage,
  lastPage,
  total,
  hasNextPage,
  hasPrevPage,
  isEmpty,
  statusFilter,
  fetchCandidatures,
  nextPage,
  prevPage,
  goToPage,
  setStatusFilter,
  refresh,
} = useProducerCandidatures(props.missionId)

/**
 * Composable for accepting candidatures
 */
const {
  error: acceptError,
  successMessage: acceptSuccessMessage,
  acceptCandidature,
  reset: resetAccept,
} = useAcceptCandidature()

/**
 * Composable for rejecting candidatures
 */
const {
  error: rejectError,
  successMessage: rejectSuccessMessage,
  rejectCandidature,
  reset: resetReject,
} = useRejectCandidature()

/**
 * Mission payment selection composable
 */
const {
  isConfirming,
  error: paymentError,
  pricing,
  selectedCount,
  selectedFaces,
  selectionLimitReached,
  toggleSelection,
  removeSelection,
  isSelected,
  confirmAndPay,
} = useMissionPayment(props.missionBudget ?? 0, props.missionId, props.nombreFacesVoulu)

/**
 * Whether selection mode should be active
 * Only for published missions, except the FIX-19.3 retry path where a stuck
 * pending payment must still allow the producer to reconfirm the preserved
 * selection.
 */
const isSelectionMode = computed(
  () => props.missionStatus === 'published' || props.allowRetrySelection === true
)

/**
 * Toast state for notifications
 */
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref<'success' | 'error'>('success')

/**
 * Card refs for resetting loading state
 */
const cardRefs = ref<Record<string, InstanceType<typeof ProducerCandidatureCard>>>({})

/**
 * Show toast notification
 */
function displayToast(message: string, type: 'success' | 'error'): void {
  toastMessage.value = message
  toastType.value = type
  showToast.value = true
  setTimeout(() => {
    showToast.value = false
  }, 4000)
}

/**
 * Handle accept candidature
 */
async function handleAccept(candidatureId: string): Promise<void> {
  const result = await acceptCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetAccepting()

  if (result) {
    displayToast(acceptSuccessMessage.value || 'Candidature acceptée', 'success')
    // Refresh the list to show updated status
    await refresh()
  } else {
    displayToast(acceptError.value || "Erreur lors de l'acceptation", 'error')
  }

  resetAccept()
}

/**
 * Handle reject candidature
 */
async function handleReject(candidatureId: string): Promise<void> {
  const result = await rejectCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetRejecting()

  if (result) {
    displayToast(rejectSuccessMessage.value || 'Candidature refusée', 'success')
    // Refresh the list to show updated status
    await refresh()
  } else {
    displayToast(rejectError.value || 'Erreur lors du refus', 'error')
  }

  resetReject()
}

/**
 * Handle filter change
 */
async function handleFilterChange(status: CandidatureStatusType | ''): Promise<void> {
  await setStatusFilter(status)
}

/**
 * Handle confirm and pay.
 * On failure the user's selection is preserved (see `useMissionPayment`) and
 * the parent page is notified so it can re-evaluate whether the mission is
 * stuck in pending_payment with no trackable transaction. (FIX-19.3.)
 */
async function handleConfirmAndPay(): Promise<void> {
  const result = await confirmAndPay(props.missionId)
  if (result) {
    emit('selection-confirmed', result.checkout_url)
    return
  }

  emit('selection-failed',
    paymentError.value ?? 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.',
  )
}

/**
 * Generate page numbers for pagination
 */
function getPageNumbers(): number[] {
  const pages: number[] = []
  const maxVisiblePages = 5
  let startPage = Math.max(1, currentPage.value - Math.floor(maxVisiblePages / 2))
  const endPage = Math.min(lastPage.value, startPage + maxVisiblePages - 1)

  if (endPage - startPage + 1 < maxVisiblePages) {
    startPage = Math.max(1, endPage - maxVisiblePages + 1)
  }

  for (let i = startPage; i <= endPage; i++) {
    if (i > 0) pages.push(i)
  }
  return pages
}

/**
 * Lifecycle
 */
onMounted(() => {
  fetchCandidatures(1)
})
</script>

<template>
  <section class="space-y-6">
    <!-- Header & Filter -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="space-y-1">
        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
          Candidatures
        </h2>
        <p v-if="!isLoading && !error" class="text-sm text-muted-foreground">
          {{ total }} candidature{{ total > 1 ? 's' : '' }} reçue{{ total > 1 ? 's' : '' }}
        </p>
      </div>
      <div v-if="isSelectionMode" class="flex items-center gap-2 text-sm text-muted-foreground">
        <CheckSquare class="h-4 w-4 text-primary" />
        <span>Sélectionnez les faces à retenir</span>
      </div>
    </div>

    <!-- Status Filter -->
    <StatusFilter :model-value="statusFilter" @update:model-value="handleFilterChange" />

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex flex-col items-center justify-center py-24 text-center"
    >
      <Loader2 class="h-12 w-12 animate-spin text-primary" />
      <p class="mt-4 text-muted-foreground">Chargement des candidatures...</p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 px-6 py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
        <AlertCircle class="h-10 w-10" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
      <p class="mt-2 max-w-sm text-muted-foreground">{{ error }}</p>
      <button
        type="button"
        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
        @click="refresh"
      >
        Réessayer
      </button>
    </div>

    <!-- Empty State (No Candidatures at all) -->
    <div
      v-else-if="isEmpty && !statusFilter"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-muted p-4">
        <Inbox class="h-10 w-10 text-muted-foreground" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Aucune candidature reçue</h3>
      <p class="mt-2 max-w-xs text-muted-foreground">
        Les Faces n'ont pas encore postulé à cette mission.
      </p>
    </div>

    <!-- Empty State (Filter with no results) -->
    <div
      v-else-if="isEmpty && statusFilter"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-muted p-4">
        <FileText class="h-10 w-10 text-muted-foreground" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Aucun résultat</h3>
      <p class="mt-2 max-w-xs text-muted-foreground">
        Aucune candidature avec le statut
        <span class="font-semibold">"{{ CandidatureStatusLabel[statusFilter] }}"</span>
        trouvée.
      </p>
      <button
        type="button"
        class="mt-6 text-sm font-medium text-primary hover:underline"
        @click="handleFilterChange('')"
      >
        Réinitialiser le filtre
      </button>
    </div>

    <!-- Results List -->
    <template v-else>
      <!-- Cross-page selection counter + removable chips -->
      <div
        v-if="isSelectionMode && selectedCount > 0"
        class="mb-4 rounded-lg bg-primary/10 px-4 py-3 text-sm"
      >
        <div class="flex items-center gap-2 font-medium text-primary mb-2">
          <CheckSquare class="h-4 w-4" />
          <span>{{ selectedCount }}{{ nombreFacesVoulu ? ` / ${nombreFacesVoulu}` : '' }} Face(s) sélectionnée(s)</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="face in selectedFaces"
            :key="face.candidatureId"
            class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-700 border border-gray-200 shadow-sm"
          >
            {{ face.label }}
            <button
              type="button"
              class="ml-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full text-gray-400 hover:bg-red-100 hover:text-red-600 transition-colors"
              @click="removeSelection(face.candidatureId)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
              </svg>
            </button>
          </span>
        </div>
      </div>
      <!-- Selection limit reached toast -->
      <div
        v-if="selectionLimitReached"
        class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-700"
      >
        Vous avez atteint le nombre maximum de Faces pour cette mission ({{ nombreFacesVoulu }}).
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <ProducerCandidatureCard
          v-for="candidature in candidatures"
          :key="candidature.id"
          :ref="(el) => { if (el) cardRefs[candidature.id] = el as InstanceType<typeof ProducerCandidatureCard> }"
          :candidature="candidature"
          :selection-mode="isSelectionMode"
          :is-selected="isSelected(candidature.id)"
          @accept="handleAccept"
          @reject="handleReject"
          @toggle-selection="(id: string) => toggleSelection(id, candidature.face.display_name)"
        />
      </div>

      <!-- Selection Summary Panel -->
      <div v-if="isSelectionMode && pricing" class="mt-6">
        <MissionSelectionSummary
          :pricing="pricing"
          :is-confirming="isConfirming"
          @confirm="handleConfirmAndPay"
        />
      </div>

      <!-- Pagination Controls -->
      <div
        v-if="lastPage > 1"
        class="mt-8 flex items-center justify-center gap-2"
      >
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!hasPrevPage"
          @click="prevPage"
        >
          <ChevronLeft class="h-5 w-5" />
        </button>

        <div class="flex items-center gap-1">
          <button
            v-for="page in getPageNumbers()"
            :key="page"
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium transition-colors"
            :class="[
              page === currentPage
                ? 'bg-primary text-white'
                : 'border border-border bg-card hover:bg-muted',
            ]"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!hasNextPage"
          @click="nextPage"
        >
          <ChevronRight class="h-5 w-5" />
        </button>
      </div>
    </template>

    <!-- Toast Notification -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
      >
        <div
          v-if="showToast"
          class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg"
          :class="[
            toastType === 'success'
              ? 'bg-green-600 text-white'
              : 'bg-red-600 text-white',
          ]"
        >
          <span class="text-sm font-medium">{{ toastMessage }}</span>
          <button
            type="button"
            aria-label="Fermer"
            class="ml-2 text-white/80 hover:text-white"
            @click="showToast = false"
          >
            &times;
          </button>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>

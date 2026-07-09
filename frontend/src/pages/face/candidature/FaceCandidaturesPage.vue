<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Loader2,
  AlertCircle,
  FileText,
  ChevronLeft,
  ChevronRight,
} from 'lucide-vue-next'
import {
  useFaceCandidatures,
  useConfirmCandidature,
  useReconfirmCandidature,
  useCancelCandidature,
} from '@/features/candidature/composables'
import { CandidatureCard, StatusFilter } from '@/features/candidature/components'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { useToast } from '@/composables/useToast'
import type { CandidatureStatusType } from '@/features/candidature/types'
import { CandidatureStatusLabel } from '@/features/candidature/types'

// Named so <keep-alive :include> in FaceLayout can cache this listing.
defineOptions({ name: 'FaceCandidaturesPage' })

/**
 * LOGIC & STATE MANAGEMENT
 */
const route = useRoute()
const router = useRouter()

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
} = useFaceCandidatures()

/**
 * Composable for confirming candidatures
 */
const {
  error: confirmError,
  successMessage: confirmSuccessMessage,
  confirmCandidature,
  reset: resetConfirm,
} = useConfirmCandidature()

/**
 * Composable for reconfirming UGC candidatures (8-3, D-8.3.d) — accepted → confirmed.
 */
const {
  error: reconfirmError,
  successMessage: reconfirmSuccessMessage,
  reconfirmCandidature,
  reset: resetReconfirm,
} = useReconfirmCandidature()

/**
 * Composable for cancelling candidatures
 */
const {
  error: cancelError,
  successMessage: cancelSuccessMessage,
  cancelCandidature,
  reset: resetCancel,
} = useCancelCandidature()

const toast = useToast()

/**
 * Card refs for resetting loading state
 */
const cardRefs = ref<Record<string, InstanceType<typeof CandidatureCard>>>({})

/**
 * Cancel modal state
 */
const showCancelModal = ref(false)
const candidatureToCancel = ref<string | null>(null)

/**
 * Handle confirm candidature
 */
async function handleConfirm(candidatureId: string): Promise<void> {
  // D-8.3.a : UGC (type_compensation != null) → reconfirm ; cash → confirm.
  const target = candidatures.value.find((c) => c.id === candidatureId)
  const isUgc = target?.mission.type_compensation != null

  const result = isUgc
    ? await reconfirmCandidature(candidatureId)
    : await confirmCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetConfirming()

  if (result) {
    toast.success(
      (isUgc ? reconfirmSuccessMessage.value : confirmSuccessMessage.value) || 'Participation confirmée',
    )
    // Refresh the list to show updated status
    await refresh()
  } else {
    toast.error(
      (isUgc ? reconfirmError.value : confirmError.value) || 'Erreur lors de la confirmation',
    )
  }

  if (isUgc) resetReconfirm()
  else resetConfirm()
}

/**
 * Handle cancel candidature request — opens confirmation modal
 */
function handleCancelRequest(candidatureId: string): void {
  candidatureToCancel.value = candidatureId
  showCancelModal.value = true
}

/**
 * Handle cancel confirmation from modal
 */
async function handleCancelConfirm(): Promise<void> {
  if (!candidatureToCancel.value) return

  const candidatureId = candidatureToCancel.value
  showCancelModal.value = false

  const success = await cancelCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetCancelling()

  if (success) {
    toast.success(cancelSuccessMessage.value || 'Candidature annulée')
    await refresh()
  } else {
    toast.error(cancelError.value || 'Erreur lors de l\'annulation')
  }

  candidatureToCancel.value = null
  resetCancel()
}

/**
 * Handle cancel modal dismiss
 */
function handleCancelModalClose(): void {
  if (candidatureToCancel.value) {
    cardRefs.value[candidatureToCancel.value]?.resetCancelling()
  }
  showCancelModal.value = false
  candidatureToCancel.value = null
}

/**
 * Sync filter with URL query params
 */
function syncFromUrl(): void {
  const urlStatus = route.query.status as CandidatureStatusType | '' | undefined
  const urlPage = route.query.page ? parseInt(route.query.page as string, 10) : 1

  if (urlStatus !== undefined && urlStatus !== statusFilter.value) {
    statusFilter.value = urlStatus
  }

  fetchCandidatures(urlPage)
}

/**
 * Update URL when filter or page changes
 */
function updateUrl(): void {
  const query: Record<string, string> = {}
  if (statusFilter.value) {
    query.status = statusFilter.value
  }
  if (currentPage.value > 1) {
    query.page = String(currentPage.value)
  }
  router.replace({ query })
}

/**
 * Handle filter change
 */
async function handleFilterChange(status: CandidatureStatusType | ''): Promise<void> {
  await setStatusFilter(status)
  updateUrl()
}

/**
 * Handle pagination
 */
async function handleNextPage(): Promise<void> {
  await nextPage()
  updateUrl()
}

async function handlePrevPage(): Promise<void> {
  await prevPage()
  updateUrl()
}

async function handleGoToPage(page: number): Promise<void> {
  await goToPage(page)
  updateUrl()
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
    pages.push(i)
  }
  return pages
}

/**
 * Navigate to browse missions
 */
function goToBrowseMissions(): void {
  router.push({ name: 'face-missions' })
}

/**
 * LIFECYCLE
 */
onMounted(() => {
  syncFromUrl()
})

// Watch for URL changes (browser back/forward)
watch(
  () => route.query,
  () => {
    // Cached by keep-alive: this watcher keeps firing while the page is
    // off-screen. Act only when actually on this route, so navigating to a
    // detail (which drops the query) can't corrupt state; it fires once on
    // return, refreshing the list (statuses change inter-actor).
    if (route.name !== 'face-candidatures') return
    syncFromUrl()
  },
)
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-800">
        Mes candidatures
      </h1>
      <p class="mt-1 text-slate-500">
        Suivez l'état de vos candidatures aux missions
      </p>
    </div>

    <div>
      <!-- Status Filter -->
      <div class="mb-6">
        <StatusFilter
          :model-value="statusFilter"
          @update:model-value="handleFilterChange"
        />
      </div>

      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex flex-col items-center justify-center py-24"
      >
        <Loader2 class="h-12 w-12 animate-spin text-primary" />
        <p class="mt-4 text-muted-foreground">Chargement de vos candidatures...</p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-24 text-center"
      >
        <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
          <AlertCircle class="h-10 w-10" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          {{ error }}
        </p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
          @click="fetchCandidatures(1)"
        >
          Réessayer
        </button>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="isEmpty"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
      >
        <div class="mb-4 rounded-full bg-muted p-4">
          <FileText class="h-10 w-10 text-muted-foreground" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Aucune candidature</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          <template v-if="statusFilter">
            Aucune candidature avec le statut "{{ CandidatureStatusLabel[statusFilter] }}" trouvée.
          </template>
          <template v-else>
            Vous n'avez pas encore postulé à une mission.<br />
            Découvrez les opportunités disponibles !
          </template>
        </p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
          @click="goToBrowseMissions"
        >
          Parcourir les missions
        </button>
      </div>

      <!-- Candidatures List -->
      <template v-else>
        <!-- Results count -->
        <p class="mb-4 text-sm text-muted-foreground">
          {{ total }} candidature{{ total > 1 ? 's' : '' }} trouvée{{ total > 1 ? 's' : '' }}
        </p>

        <!-- Cards Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <CandidatureCard
            v-for="candidature in candidatures"
            :key="candidature.id"
            :ref="(el) => { if (el) cardRefs[candidature.id] = el as InstanceType<typeof CandidatureCard> }"
            :candidature="candidature"
            @confirm="handleConfirm"
            @cancel="handleCancelRequest"
          />
        </div>

        <!-- Pagination -->
        <div
          v-if="lastPage > 1"
          class="mt-8 flex items-center justify-center gap-2"
        >
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!hasPrevPage"
            @click="handlePrevPage"
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
              @click="handleGoToPage(page)"
            >
              {{ page }}
            </button>
          </div>

          <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!hasNextPage"
            @click="handleNextPage"
          >
            <ChevronRight class="h-5 w-5" />
          </button>
        </div>
      </template>
    </div>

    <!-- Cancel Confirmation Modal -->
    <ConfirmModal
      :is-open="showCancelModal"
      title="Annuler cette candidature ?"
      message="Cette action est définitive. Vous ne pourrez pas postuler à nouveau pour cette mission."
      confirm-text="Oui, annuler"
      cancel-text="Non, garder"
      variant="warning"
      @confirm="handleCancelConfirm"
      @cancel="handleCancelModalClose"
    />
  </div>
</template>

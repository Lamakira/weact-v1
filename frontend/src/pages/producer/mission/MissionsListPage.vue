<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RefreshCw, Inbox, ArrowRight, Plus } from 'lucide-vue-next'
import type { SortingState } from '@tanstack/vue-table'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useMissionsList, useDeleteMission, useCloseMission, useReopenMission, useCompleteMission } from '@/features/mission/composables'
import { MissionsTable, DeleteMissionDialog, CloseMissionDialog, ReopenMissionDialog, CompleteMissionDialog, MissionStatusFilter } from '@/features/mission/components'
import { missionApi } from '@/features/mission/services/missionApi'
import { buildListQuery, parseListQuery, type ListQueryState } from '@/components/data-table/listQuery'
import { useIsDesktop } from '@/composables/useMediaQuery'
import { UgcPaymentOverlay } from '@/components/ugc'
import { MissionStatus, MISSION_SORT_KEYS, type MissionStatusType, type MissionSortKey, type MissionListParams } from '@/features/mission/types'
import type { Mission } from '@/features/mission/types'
import { useRefreshOnReturn } from '@/composables/useRefreshOnReturn'
import { usePaymentReturn } from '@/composables/usePaymentReturn'
import PaymentReturnBanner from '@/components/payment/PaymentReturnBanner.vue'

// Explicit name (devtools). Caching is driven by the route's meta.keepAlive flag.
defineOptions({ name: 'MissionsListPage' })

/**
 * LOGIC & STATE MANAGEMENT
 */
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { success, error: toastError } = useToast()
const {
  missions,
  isLoading,
  error,
  currentPage,
  lastPage,
  total,
  hasLoaded,
  fetchMissions,
  refreshMissions,
} = useMissionsList()

const isDesktop = useIsDesktop()
const view = computed<'table' | 'cards'>(() => (isDesktop.value ? 'table' : 'cards'))

/**
 * URL = single source of truth of the list view (?status=&sort=&direction=&page=&per_page=).
 * Foreign keys (?pay, ?payment_return, ?mission) are preserved by buildListQuery.
 */
const urlState = computed(() =>
  parseListQuery(route.query, { sorts: MISSION_SORT_KEYS, statuses: Object.values(MissionStatus) }),
)
const statusFilter = computed(() => urlState.value.status as MissionStatusType | '')
const sorting = computed<SortingState>(() =>
  urlState.value.sort ? [{ id: urlState.value.sort, desc: urlState.value.direction === 'desc' }] : [],
)

function currentParams(): MissionListParams {
  const state = urlState.value
  return {
    page: state.page,
    perPage: state.perPage,
    status: state.status as MissionStatusType | '',
    sort: state.sort as MissionSortKey | null,
    direction: state.direction,
  }
}

async function loadMissions(): Promise<void> {
  const params = currentParams()
  await fetchMissions(params)
  // Page past the end (stale bookmark, rows gone after an action): jump to the last
  // page instead of showing a « no data » state for a list that has data.
  if (missions.value.length === 0 && total.value > 0 && params.page > lastPage.value) {
    await navigateList({ page: lastPage.value })
  }
}

async function navigateList(patch: Partial<ListQueryState>): Promise<void> {
  await router.replace({ query: buildListQuery(route.query, { ...urlState.value, ...patch }) })
}

/** Any filter / sort / page size change goes back to page 1. */
function handleFilterChange(status: MissionStatusType | ''): void {
  void navigateList({ status, page: 1 })
}
function handleSortingChange(next: SortingState): void {
  void navigateList({ sort: next[0]?.id ?? null, direction: next[0]?.desc ? 'desc' : 'asc', page: 1 })
}
function handlePageChange(page: number): void {
  void navigateList({ page })
}
function handlePageSizeChange(perPage: number): void {
  void navigateList({ perPage, page: 1 })
}

const { deleteMission, isDeleting } = useDeleteMission()
const { closeMission, isClosing } = useCloseMission()
const { reopenMission, isReopening } = useReopenMission()
const { completeMission, isCompleting } = useCompleteMission()

// Dialog State
const isDeleteDialogOpen = ref(false)
const isCloseDialogOpen = ref(false)
const isReopenDialogOpen = ref(false)
const isCompleteDialogOpen = ref(false)
const selectedMission = ref<Mission | null>(null)

// UGC commission payment tunnel state
const payingMission = ref<Mission | null>(null)
const isUgcPayOpen = ref(false)

/**
 * ACTIONS
 */
// Return from the same-tab FedaPay checkout (?payment_return=mission_commission&mission={id}).
const paymentReturn = usePaymentReturn({
  kinds: ['mission_commission'],
  onConfirmed: async () => {
    await refreshMissions()
  },
  onRetry: (_kind, ids) => {
    if (ids.missionId) void handlePayCommission(ids.missionId)
  },
})

onMounted(async () => {
  await loadMissions()
  if (route.query.payment_return !== undefined) {
    await paymentReturn.start()
    return
  }
  await maybeOpenPayTunnel()
})

// A filter / sort / page change rewrites the URL: reload the list. Only the list
// keys matter (consuming ?pay must not refetch), and only while this (keep-alive
// cached) page is the active route.
watch(
  () => JSON.stringify(urlState.value),
  () => {
    if (route.name !== 'producer-missions') return
    void loadMissions()
  },
)

// Auto-open the commission tunnel when arriving from UGC mission creation
// (?pay={id}). Extracted so it also runs on keep-alive re-activation below.
async function maybeOpenPayTunnel(): Promise<void> {
  // A ?payment_return is being verified: never also auto-open the tunnel.
  if (route.query.payment_return !== undefined) return
  const payId = route.query.pay
  if (typeof payId === 'string' && payId) {
    const resolution = await resolvePayCommission(payId)
    // Mission unknown for now (network error): keep ?pay to retry on the next refresh.
    // Opened or definitively not payable: consume it either way.
    if (resolution === 'not-found') return

    // Consume ?pay: rewrite the current history entry without it (other keys
    // preserved), so a later Back to this URL can't replay the tunnel once the
    // commission is settled.
    const query = { ...route.query }
    delete query.pay
    void router.replace({ query })
  }
}

// Cached by keep-alive: on return, refresh the list AND re-check ?pay. The
// post-publish redirect (producer-missions?pay={id}) reactivates this cached
// instance — onMounted no longer runs — so without this the commission tunnel
// never opens and the mission stays pending_payment (revenue gap).
useRefreshOnReturn(async () => {
  await loadMissions()
  await maybeOpenPayTunnel()
})

async function retryMissions(): Promise<void> {
  await loadMissions()
  await maybeOpenPayTunnel()
}

function navigateToPublish(): void {
  router.push({ name: 'publish-mission' })
}

function handleEdit(id: string): void {
  router.push({ name: 'edit-mission', params: { id } })
}

function handleViewCandidatures(id: string): void {
  router.push({ name: 'producer-mission-candidatures', params: { id } })
}

function handleViewAttendance(id: string): void {
  router.push({ name: 'producer-mission-attendance', params: { id } })
}

type PayResolution = 'opened' | 'not-payable' | 'not-found'

async function resolvePayCommission(id: string): Promise<PayResolution> {
  // The list is paginated / filtered / sorted server-side, so the mission a ?pay
  // return must open the tunnel for may not be in the current page: fall back to
  // fetching it by id (a failed lookup keeps ?pay, retried on the next refresh).
  let mission: Mission | undefined = missions.value.find((m) => m.id === id)
  if (!mission) {
    try {
      mission = (await missionApi.getMission(id)).data
    } catch {
      return 'not-found'
    }
  }
  // Status guard: only a pending_payment mission has a commission to pay — a
  // stale ?pay (deep link, history entry) for an already-paid mission must not
  // reopen the payment tunnel.
  if (mission && mission.status === MissionStatus.PENDING_PAYMENT) {
    payingMission.value = mission
    isUgcPayOpen.value = true
    return 'opened'
  }

  return 'not-payable'
}

async function handlePayCommission(id: string): Promise<boolean> {
  return (await resolvePayCommission(id)) === 'opened'
}

function handleDeleteClick(id: string): void {
  const mission = missions.value.find((m) => m.id === id)
  if (mission) {
    selectedMission.value = mission
    isDeleteDialogOpen.value = true
  }
}

function closeDeleteDialog(): void {
  isDeleteDialogOpen.value = false
  selectedMission.value = null
}

function handleCloseClick(id: string): void {
  const mission = missions.value.find((m) => m.id === id)
  if (mission) {
    selectedMission.value = mission
    isCloseDialogOpen.value = true
  }
}

function closeCloseDialog(): void {
  isCloseDialogOpen.value = false
  selectedMission.value = null
}

function handleReopenClick(id: string): void {
  const mission = missions.value.find((m) => m.id === id)
  if (mission) {
    selectedMission.value = mission
    isReopenDialogOpen.value = true
  }
}

function closeReopenDialog(): void {
  isReopenDialogOpen.value = false
  selectedMission.value = null
}

function handleCompleteClick(id: string): void {
  const mission = missions.value.find((m) => m.id === id)
  if (mission) {
    selectedMission.value = mission
    isCompleteDialogOpen.value = true
  }
}

function closeCompleteDialog(): void {
  isCompleteDialogOpen.value = false
  selectedMission.value = null
}

async function confirmDelete(): Promise<void> {
  if (!selectedMission.value) return

  const result = await deleteMission(selectedMission.value.id)
  if (result.success) {
    success('Mission supprimée avec succès!')
    await refreshMissions()
    // Deleting the last row of a page > 1 leaves it empty: step back one page.
    if (missions.value.length === 0 && urlState.value.page > 1) {
      handlePageChange(urlState.value.page - 1)
    }
    closeDeleteDialog()
  } else {
    toastError(result.message)
    closeDeleteDialog()
  }
}

async function confirmClose(): Promise<void> {
  if (!selectedMission.value) return

  const result = await closeMission(selectedMission.value.id)
  if (result.success) {
    success('Mission clôturée avec succès!')
    await refreshMissions()
    closeCloseDialog()
  } else {
    toastError(result.message)
    closeCloseDialog()
  }
}

async function confirmReopen(): Promise<void> {
  if (!selectedMission.value) return

  const result = await reopenMission(selectedMission.value.id)
  if (result.success) {
    success('Mission réouverte avec succès!')
    await refreshMissions()
    closeReopenDialog()
  } else {
    toastError(result.message)
    closeReopenDialog()
  }
}

async function confirmComplete(): Promise<void> {
  if (!selectedMission.value) return

  const result = await completeMission(selectedMission.value.id)
  if (result.success) {
    success('Mission marquée comme terminée!')
    await refreshMissions()
    closeCompleteDialog()
  } else {
    toastError(result.message)
    closeCompleteDialog()
  }
}
</script>

<template>
  <div class="pb-10">
    <!-- Page Header Section -->
    <section class="mb-6 flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
          Mes missions
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          Gérez vos annonces et suivez les candidatures
        </p>
      </div>
      <button
        v-if="authStore.isEmailVerified"
        type="button"
        class="flex shrink-0 items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
        @click="navigateToPublish"
      >
        <Plus class="h-4 w-4" />
        <span class="hidden sm:inline">Publier une mission</span>
        <span class="sm:hidden">Publier</span>
      </button>
    </section>

    <PaymentReturnBanner
      :state="paymentReturn.state.value"
      @retry="paymentReturn.retry"
      @dismiss="paymentReturn.dismiss"
    />

    <!-- Status Filter -->
    <div class="mb-6">
      <MissionStatusFilter
        :model-value="statusFilter"
        @update:model-value="handleFilterChange"
      />
    </div>

    <div>
      <!-- Manual refresh (the result count lives in the table footer) -->
      <div v-if="hasLoaded && !error && (total > 0 || statusFilter)" class="mb-2 flex justify-end">
        <button
          type="button"
          class="group p-2 text-muted-foreground transition-colors hover:text-primary"
          title="Rafraîchir la liste"
          aria-label="Rafraîchir la liste"
          @click="retryMissions"
        >
          <RefreshCw class="h-5 w-5" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>

      <MissionsTable
        :missions="missions"
        :email-verified="authStore.isEmailVerified"
        :sorting="sorting"
        :page="currentPage"
        :page-size="urlState.perPage"
        :total="total"
        :last-page="lastPage"
        :loading="isLoading || (!hasLoaded && !error)"
        :error="error"
        :view="view"
        @update:sorting="handleSortingChange"
        @page-change="handlePageChange"
        @page-size-change="handlePageSizeChange"
        @retry="retryMissions"
        @edit="handleEdit"
        @delete="handleDeleteClick"
        @close="handleCloseClick"
        @reopen="handleReopenClick"
        @complete="handleCompleteClick"
        @view-candidatures="handleViewCandidatures"
        @view-attendance="handleViewAttendance"
        @pay-commission="handlePayCommission"
      >
        <template #empty>
          <!-- Empty State: No missions at all -->
          <div v-if="!statusFilter" class="flex flex-col items-center justify-center py-24 text-center">
            <div class="relative mb-6">
              <div class="absolute -inset-4 animate-pulse rounded-full bg-primary/5 blur-2xl" />
              <div
                class="relative flex h-24 w-24 items-center justify-center rounded-full bg-muted text-muted-foreground"
              >
                <Inbox class="h-12 w-12 opacity-50" />
              </div>
            </div>
            <h3 class="text-2xl font-bold text-foreground">Vous n'avez pas encore de missions</h3>
            <p class="mt-3 max-w-sm text-muted-foreground">
              Commencez à collaborer avec des talents en publiant votre première mission sur WEACT.
            </p>
            <button
              v-if="authStore.isEmailVerified"
              type="button"
              class="mt-8 flex items-center gap-2 rounded-full bg-primary px-8 py-3 text-base font-bold text-primary-foreground shadow-lg shadow-primary/20 transition-all hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/30 active:scale-95"
              @click="navigateToPublish"
            >
              Publier ma première mission
              <ArrowRight class="h-5 w-5" />
            </button>
            <p v-else class="mt-4 text-sm text-amber-600">
              Veuillez vérifier votre email pour publier des missions.
            </p>
          </div>

          <!-- Empty State: No missions matching filter -->
          <div v-else class="flex flex-col items-center justify-center py-24 text-center">
            <div class="relative mb-6">
              <div
                class="relative flex h-24 w-24 items-center justify-center rounded-full bg-muted text-muted-foreground"
              >
                <Inbox class="h-12 w-12 opacity-50" />
              </div>
            </div>
            <h3 class="text-xl font-bold text-foreground">Aucune mission trouvée</h3>
            <p class="mt-3 max-w-sm text-muted-foreground">
              Aucune mission ne correspond à ce filtre.
            </p>
            <button
              type="button"
              class="mt-6 flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-sm font-medium transition-colors hover:bg-muted"
              @click="handleFilterChange('')"
            >
              Voir toutes les missions
            </button>
          </div>
        </template>
      </MissionsTable>
    </div>

    <!-- Delete Confirmation Dialog -->
    <DeleteMissionDialog
      :is-open="isDeleteDialogOpen"
      :mission-title="selectedMission?.titre || ''"
      :is-loading="isDeleting"
      @cancel="closeDeleteDialog"
      @confirm="confirmDelete"
    />

    <!-- Close Confirmation Dialog -->
    <CloseMissionDialog
      :is-open="isCloseDialogOpen"
      :mission-title="selectedMission?.titre || ''"
      :is-loading="isClosing"
      @cancel="closeCloseDialog"
      @confirm="confirmClose"
    />

    <!-- Reopen Confirmation Dialog -->
    <ReopenMissionDialog
      :is-open="isReopenDialogOpen"
      :mission-title="selectedMission?.titre || ''"
      :is-loading="isReopening"
      @cancel="closeReopenDialog"
      @confirm="confirmReopen"
    />

    <!-- Complete Confirmation Dialog -->
    <CompleteMissionDialog
      :is-open="isCompleteDialogOpen"
      :mission-title="selectedMission?.titre || ''"
      :is-loading="isCompleting"
      @cancel="closeCompleteDialog"
      @confirm="confirmComplete"
    />

    <!-- UGC commission payment tunnel -->
    <UgcPaymentOverlay
      v-if="payingMission"
      v-model="isUgcPayOpen"
      kind="mission"
      :owner-id="payingMission.id"
      :amount="payingMission.commission_ugc ?? 0"
    />
  </div>
</template>

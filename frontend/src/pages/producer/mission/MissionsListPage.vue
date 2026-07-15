<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertCircle, RefreshCw, ClipboardList, Inbox, ArrowRight, Plus } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useMissionsList, useDeleteMission, useCloseMission, useReopenMission, useCompleteMission } from '@/features/mission/composables'
import { MissionCard, DeleteMissionDialog, CloseMissionDialog, ReopenMissionDialog, CompleteMissionDialog, MissionStatusFilter } from '@/features/mission/components'
import { UgcPaymentOverlay } from '@/components/ugc'
import { MissionStatus, type MissionStatusType } from '@/features/mission/types'
import type { Mission } from '@/features/mission/types'
import { useRefreshOnReturn } from '@/composables/useRefreshOnReturn'

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
  allMissions,
  isLoading,
  error,
  isEmpty,
  hasNoMissions,
  statusFilter,
  fetchMissions,
  refreshMissions,
  setStatusFilter,
} = useMissionsList()

/**
 * Handle status filter change
 */
function handleFilterChange(status: MissionStatusType | ''): void {
  setStatusFilter(status)
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
onMounted(async () => {
  await fetchMissions()
  maybeOpenPayTunnel()
})

// Auto-open the commission tunnel when arriving from UGC mission creation
// (?pay={id}). Extracted so it also runs on keep-alive re-activation below.
function maybeOpenPayTunnel(): void {
  const payId = route.query.pay
  if (typeof payId === 'string' && payId) {
    const didOpen = handlePayCommission(payId)
    if (!didOpen) return

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
  await refreshMissions()
  maybeOpenPayTunnel()
})

async function retryMissions(): Promise<void> {
  await refreshMissions()
  maybeOpenPayTunnel()
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

function handlePayCommission(id: string): boolean {
  // Search the UNFILTERED list: this cached page can keep an active status
  // filter across a keep-alive round-trip, and no filter option matches the
  // pending_payment mission a ?pay return must open the tunnel for.
  const mission = allMissions.value.find((m) => m.id === id)
  // Status guard: only a pending_payment mission has a commission to pay — a
  // stale ?pay (deep link, history entry) for an already-paid mission must not
  // reopen the payment tunnel.
  if (mission && mission.status === MissionStatus.PENDING_PAYMENT) {
    payingMission.value = mission
    isUgcPayOpen.value = true
    return true
  }

  return false
}

function handleCommissionSettled(): void {
  isUgcPayOpen.value = false
  success('Commission payée. Votre mission est publiée.')
  void refreshMissions()
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

    <!-- Status Filter -->
    <div class="mb-6">
      <MissionStatusFilter
        :model-value="statusFilter"
        @update:model-value="handleFilterChange"
      />
    </div>

    <div>
      <!-- Loading State -->
      <div v-if="isLoading && !missions.length" class="grid gap-6 md:grid-cols-2 lg:grid-cols-1">
        <div
          v-for="i in 3"
          :key="i"
          class="relative overflow-hidden rounded-xl border border-border bg-card p-6 shadow-sm"
        >
          <div class="flex items-start justify-between">
            <div class="h-6 w-3/4 animate-pulse rounded bg-muted" />
            <div class="h-8 w-8 animate-pulse rounded-full bg-muted" />
          </div>
          <div class="mt-4 space-y-3">
            <div class="h-4 w-full animate-pulse rounded bg-muted" />
            <div class="h-4 w-2/3 animate-pulse rounded bg-muted" />
          </div>
          <div class="mt-6 flex gap-2">
            <div class="h-9 w-24 animate-pulse rounded-lg bg-muted" />
            <div class="h-9 w-24 animate-pulse rounded-lg bg-muted" />
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-16 text-center"
      >
        <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
          <AlertCircle class="h-10 w-10" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          Impossible de charger vos missions pour le moment.
        </p>
        <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
        <button
          type="button"
          class="mt-6 flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-sm font-medium transition-colors hover:bg-muted"
          @click="retryMissions"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          Réessayer
        </button>
      </div>

      <!-- Empty State: No missions at all -->
      <div v-else-if="hasNoMissions" class="flex flex-col items-center justify-center py-24 text-center">
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
      <div v-else-if="isEmpty" class="flex flex-col items-center justify-center py-24 text-center">
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

      <!-- Content List -->
      <div v-else>
        <div class="mb-6 flex items-center justify-between">
          <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <ClipboardList class="h-4 w-4" />
            <span>
              {{ missions.length }} mission{{ missions.length > 1 ? 's' : '' }}
              <template v-if="statusFilter && missions.length !== allMissions.length">
                sur {{ allMissions.length }}
              </template>
            </span>
          </div>
          <button
            type="button"
            class="group p-2 text-muted-foreground transition-colors hover:text-primary"
            title="Rafraîchir la liste"
            @click="retryMissions"
          >
            <RefreshCw class="h-5 w-5" :class="{ 'animate-spin': isLoading }" />
          </button>
        </div>

        <TransitionGroup
          tag="div"
          enter-active-class="transition duration-500 ease-out"
          enter-from-class="opacity-0 translate-y-4"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-300 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
          class="flex flex-col gap-4"
        >
          <MissionCard
            v-for="mission in missions"
            :key="mission.id"
            :mission="mission"
            :email-verified="authStore.isEmailVerified"
            @edit="handleEdit"
            @delete="handleDeleteClick"
            @close="handleCloseClick"
            @reopen="handleReopenClick"
            @complete="handleCompleteClick"
            @view-candidatures="handleViewCandidatures"
            @view-attendance="handleViewAttendance"
            @pay-commission="handlePayCommission"
          />
        </TransitionGroup>
      </div>
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
      :reference="payingMission.id"
      @settled="handleCommissionSettled"
    />
  </div>
</template>

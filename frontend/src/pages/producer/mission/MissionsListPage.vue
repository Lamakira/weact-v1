<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, AlertCircle, RefreshCw, ClipboardList, Inbox, ArrowRight } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useMissionsList, useDeleteMission, useCloseMission, useReopenMission, useCompleteMission } from '@/features/mission/composables'
import { MissionCard, DeleteMissionDialog, CloseMissionDialog, ReopenMissionDialog, CompleteMissionDialog } from '@/features/mission/components'
import type { Mission } from '@/features/mission/types'

/**
 * LOGIC & STATE MANAGEMENT
 */
const router = useRouter()
const authStore = useAuthStore()
const { success, error: toastError } = useToast()
const { missions, isLoading, error, isEmpty, fetchMissions, refreshMissions } = useMissionsList()

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

/**
 * ACTIONS
 */
onMounted(() => {
  fetchMissions()
})

function navigateToPublish(): void {
  router.push({ name: 'publish-mission' })
}

function handleEdit(id: number): void {
  router.push({ name: 'edit-mission', params: { id } })
}

function handleViewCandidatures(id: number): void {
  router.push({ name: 'producer-mission-candidatures', params: { id } })
}

function handleDeleteClick(id: number): void {
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

function handleCloseClick(id: number): void {
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

function handleReopenClick(id: number): void {
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

function handleCompleteClick(id: number): void {
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
  <div class="min-h-screen bg-background pb-20">
    <!-- Header Section -->
    <header class="sticky top-0 z-20 border-b border-border bg-background/80 backdrop-blur-md">
      <div class="container mx-auto flex h-20 items-center justify-between px-4 sm:px-6">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            Mes missions
          </h1>
          <p class="hidden text-sm text-muted-foreground sm:block">
            Gérez vos annonces et suivez les candidatures
          </p>
        </div>

        <button
          v-if="authStore.isEmailVerified"
          type="button"
          class="group relative flex items-center gap-2 overflow-hidden rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:ring-2 hover:ring-primary/20 active:scale-[0.98]"
          @click="navigateToPublish"
        >
          <Plus class="h-5 w-5 transition-transform group-hover:rotate-90" />
          <span class="hidden sm:inline">Publier une mission</span>
          <span class="sm:hidden">Publier</span>
          <div
            class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/10 to-transparent transition-transform duration-500 group-hover:translate-x-full"
          />
        </button>
      </div>
    </header>

    <main class="container mx-auto mt-8 px-4 sm:px-6">
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
          @click="refreshMissions"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          Réessayer
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="isEmpty" class="flex flex-col items-center justify-center py-24 text-center">
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

      <!-- Content List -->
      <div v-else>
        <div class="mb-6 flex items-center justify-between">
          <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <ClipboardList class="h-4 w-4" />
            <span>{{ missions.length }} mission{{ missions.length > 1 ? 's' : '' }} au total</span>
          </div>
          <button
            type="button"
            class="group p-2 text-muted-foreground transition-colors hover:text-primary"
            title="Rafraîchir la liste"
            @click="refreshMissions"
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
          />
        </TransitionGroup>
      </div>
    </main>

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
  </div>
</template>

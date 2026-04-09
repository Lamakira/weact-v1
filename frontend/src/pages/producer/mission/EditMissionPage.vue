<script setup lang="ts">
/**
 * EditMissionPage
 * Page for editing an existing mission.
 * This component is rendered inside ProducerLayout via nested routing.
 */
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { MissionForm, DeleteMissionDialog } from '@/features/mission/components'
import { useMissionEdit, useDeleteMission } from '@/features/mission/composables'
import type { CreateMissionData } from '@/features/mission/types'
import { Loader2, AlertCircle, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

const route = useRoute()
const router = useRouter()
const { success, error: toastError } = useToast()
const { isLoading, isSubmitting, error, mission, loadMission, updateMission } = useMissionEdit()
const { isDeleting, deleteMission } = useDeleteMission()

const isDeleteDialogOpen = ref(false)

const missionId = computed(() => route.params.id as string)

/**
 * Parse ISO date string to YYYY-MM-DD format for date inputs
 */
function parseDate(isoDate: string): string {
  if (!isoDate) return ''
  // Handle both ISO format (2026-03-01T00:00:00Z) and plain date (2026-03-01)
  const tIndex = isoDate.indexOf('T')
  return tIndex > -1 ? isoDate.substring(0, tIndex) : isoDate
}

const initialValues = computed(() => {
  if (!mission.value) return undefined
  return {
    titre: mission.value.titre,
    description: mission.value.description,
    date_tournage: parseDate(mission.value.date_tournage),
    profil_recherche: mission.value.profil_recherche,
    budget: mission.value.budget,
    date_limite_candidature: parseDate(mission.value.date_limite_candidature),
    nombre_faces_voulu: mission.value.nombre_faces_voulu,
    type_mission: mission.value.type_mission,
    genre_voulu: mission.value.genre_voulu,
    lieu: mission.value.lieu,
    duree: mission.value.duree,
  }
})

onMounted(async () => {
  await loadMission(missionId.value)
})

async function handleSubmit(data: CreateMissionData): Promise<void> {
  const result = await updateMission(missionId.value, data)

  if (result.success && result.data) {
    success('Mission modifiée avec succès!')
    router.push({ name: 'producer-missions' })
  } else {
    toastError(result.message || 'Une erreur est survenue')
  }
}

function handleCancel(): void {
  router.push({ name: 'producer-missions' })
}

function openDeleteDialog(): void {
  isDeleteDialogOpen.value = true
}

function closeDeleteDialog(): void {
  isDeleteDialogOpen.value = false
}

async function handleDeleteConfirm(): Promise<void> {
  const result = await deleteMission(missionId.value)

  if (result.success) {
    success('Mission supprimée avec succès!')
    router.push({ name: 'producer-missions' })
  } else {
    toastError(result.message)
    closeDeleteDialog()
  }
}
</script>

<template>
  <div>
    <!-- Loading state -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
      <Loader2 class="w-8 h-8 text-primary animate-spin mb-4" />
      <p class="text-slate-500">Chargement de la mission...</p>
    </div>

    <!-- Error state -->
    <div
      v-else-if="error && !mission"
      class="flex flex-col items-center justify-center py-20"
    >
      <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
        <AlertCircle class="w-8 h-8 text-red-500" />
      </div>
      <h2 class="text-lg font-semibold text-slate-800 mb-2">Erreur de chargement</h2>
      <p class="text-slate-500 mb-4">{{ error }}</p>
      <Button variant="outline" @click="router.push({ name: 'producer-missions' })">
        Retour aux missions
      </Button>
    </div>

    <!-- Form with delete action -->
    <template v-else-if="mission && initialValues">
      <!-- Delete action bar -->
      <div class="flex justify-end mb-6">
        <Button
          variant="outline"
          class="text-destructive border-destructive hover:bg-destructive/10"
          @click="openDeleteDialog"
        >
          <Trash2 class="w-4 h-4 mr-2" />
          Supprimer la mission
        </Button>
      </div>

      <MissionForm
        mode="edit"
        :initial-values="initialValues"
        :is-submitting="isSubmitting"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </template>

    <!-- Delete Confirmation Dialog -->
    <DeleteMissionDialog
      :is-open="isDeleteDialogOpen"
      :mission-title="mission?.titre ?? ''"
      :is-loading="isDeleting"
      @cancel="closeDeleteDialog"
      @confirm="handleDeleteConfirm"
    />
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
</style>

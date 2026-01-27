<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Loader2, AlertCircle } from 'lucide-vue-next'
import { ProducerCandidaturesSection } from '@/features/candidature/components'
import { missionApi } from '@/features/mission/services/missionApi'
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

/**
 * Computed: Mission ID from route params
 */
const missionId = computed(() => Number(route.params.id))

/**
 * Fetch mission details for the header
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
 * Lifecycle
 */
onMounted(() => {
  fetchMission()
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
          <h1 class="text-2xl font-bold text-foreground sm:text-3xl">
            {{ mission.titre }}
          </h1>
          <p class="mt-1 text-muted-foreground">
            Candidatures reçues pour cette mission
          </p>
        </template>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto mt-6 px-4 sm:px-6">
      <!-- Show section only when we have the mission ID -->
      <ProducerCandidaturesSection
        v-if="!isLoading && !error && mission"
        :mission-id="missionId"
      />
    </main>
  </div>
</template>

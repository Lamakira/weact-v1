<script setup lang="ts">
/**
 * ProducerDashboardPage
 * Dashboard content for Producer users - KPIs, quick access cards.
 * This component is rendered inside ProducerLayout via nested routing.
 */
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { RefreshCw, Star, Check, Clock, AlertCircle } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useProducerDashboardStats } from '@/features/dashboard/composables/useProducerDashboardStats'
import {
  PRODUCER_KPI_CONFIGS,
  PRODUCER_CANDIDATURES_KPI,
  PRODUCER_COLLABORATORS_KPI,
  PRODUCER_ACCEPTANCE_RATE_KPI,
  PRODUCER_RESPONSE_TIME_KPI,
} from '@/features/dashboard/types'
import { Skeleton } from '@/components/ui/skeleton'
import KpiCard from '@/features/dashboard/components/KpiCard.vue'

const router = useRouter()
const authStore = useAuthStore()
const { stats, isLoading: statsLoading, error: statsError, fetchStats, retry } = useProducerDashboardStats()

onMounted(() => {
  fetchStats()
})

// Computed values for rating display (FR58)
const formattedRating = computed(() => {
  if (stats.value?.average_rating === null || stats.value?.average_rating === undefined) {
    return '--'
  }
  return stats.value.average_rating.toFixed(1)
})

const ratingSubtitle = computed(() => {
  const count = stats.value?.ratings_count ?? 0
  if (count === 0) {
    return 'Aucun avis'
  }
  return `${count} avis`
})

// Computed values for advanced stats (FR59)
const formattedAcceptanceRate = computed(() => {
  const rate = stats.value?.acceptance_rate
  if (rate === null || rate === undefined) {
    return '--'
  }
  return `${rate.toFixed(1)}%`
})

const formattedResponseTime = computed(() => {
  const hours = stats.value?.average_response_time_hours
  if (hours === null || hours === undefined) {
    return 'N/A'
  }
  return `${hours.toFixed(1)}h`
})

const responseTimeSubtitle = computed(() => {
  const hours = stats.value?.average_response_time_hours
  if (hours === null || hours === undefined) {
    return 'Aucune décision'
  }
  return 'Délai moyen'
})

// Navigation functions for quick access cards
function goToProfile(): void {
  router.push({ name: 'producer-profile' })
}

function goToPublicProfile(): void {
  if (authStore.user?.userable?.id) {
    router.push({ name: 'public-producer-profile', params: { id: authStore.user.userable.id } })
  }
}

function goToMissions(): void {
  router.push({ name: 'producer-missions' })
}

function goToPublishMission(): void {
  router.push({ name: 'publish-mission' })
}

function goToMessages(): void {
  router.push({ name: 'producer-messages' })
}
</script>

<template>
  <div>
    <!-- KPI Section: Mes missions -->
    <section class="mb-10" aria-labelledby="kpi-section-title" data-testid="kpi-section">
      <h2 id="kpi-section-title" class="text-lg font-semibold text-slate-800 mb-4">
        Mes missions
      </h2>

      <!-- Error state -->
      <div
        v-if="statsError"
        class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-center justify-between"
        data-testid="stats-error"
        role="alert"
      >
        <div class="flex items-center gap-3">
          <AlertCircle class="w-5 h-5 text-red-500" aria-hidden="true" />
          <span class="text-sm text-red-700">{{ statsError }}</span>
        </div>
        <button
          @click="retry"
          :disabled="statsLoading"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors disabled:opacity-50"
          data-testid="retry-button"
        >
          <RefreshCw :class="{ 'animate-spin': statsLoading }" :size="16" />
          Réessayer
        </button>
      </div>

      <!-- KPI cards grid -->
      <div
        v-else
        class="grid grid-cols-1 min-[426px]:grid-cols-2 lg:grid-cols-4 gap-6"
        data-testid="kpi-cards-grid"
      >
        <KpiCard
          v-for="kpi in PRODUCER_KPI_CONFIGS"
          :key="kpi.key"
          :title="kpi.title"
          :value="stats?.[kpi.key] ?? 0"
          :icon="kpi.icon"
          :color="kpi.color"
          :is-loading="statsLoading"
          :data-testid="'kpi-card-' + kpi.key"
        />
      </div>
    </section>

    <!-- Candidatures & Réputation KPI Section (FR56 + FR57 + FR58) -->
    <section
      v-if="!statsError"
      class="mb-10"
      aria-labelledby="candidatures-section-title"
      data-testid="candidatures-kpi-section"
    >
      <h2 id="candidatures-section-title" class="text-lg font-semibold text-slate-800 mb-4">
        Candidatures & Réputation
      </h2>

      <div
        class="grid grid-cols-1 min-[426px]:grid-cols-2 lg:grid-cols-4 gap-6"
        data-testid="candidatures-kpi-grid"
      >
        <KpiCard
          :title="PRODUCER_CANDIDATURES_KPI.title"
          :value="stats?.total_candidatures ?? 0"
          :icon="PRODUCER_CANDIDATURES_KPI.icon"
          :color="PRODUCER_CANDIDATURES_KPI.color"
          :is-loading="statsLoading"
          data-testid="kpi-card-total_candidatures"
        />
        <KpiCard
          :title="PRODUCER_COLLABORATORS_KPI.title"
          :value="stats?.unique_collaborators ?? 0"
          :icon="PRODUCER_COLLABORATORS_KPI.icon"
          :color="PRODUCER_COLLABORATORS_KPI.color"
          :is-loading="statsLoading"
          data-testid="kpi-card-unique_collaborators"
        />

        <!-- Rating card with custom display for decimal values (FR58) -->
        <div
          v-if="statsLoading"
          class="bg-white rounded-2xl p-4 shadow-sm"
          data-testid="kpi-card-rating-skeleton"
        >
          <div class="flex items-center gap-3">
            <Skeleton class="h-11 w-11 rounded-xl" />
            <div class="space-y-2">
              <Skeleton class="h-6 w-14" />
              <Skeleton class="h-4 w-20" />
            </div>
          </div>
        </div>

        <div
          v-else
          class="bg-white rounded-2xl p-4 shadow-sm transition-all duration-200 hover:shadow-md"
          :aria-label="`Ma note: ${formattedRating}`"
          data-testid="kpi-card-rating"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex h-11 w-11 items-center justify-center rounded-xl shrink-0 bg-amber-50 text-amber-500"
            >
              <Star :size="20" stroke-width="2" />
            </div>

            <div class="flex flex-col min-w-0">
              <span
                class="text-xl font-bold text-gray-900 leading-none"
                data-testid="kpi-card-rating-value"
              >
                {{ formattedRating }}
              </span>
              <span
                class="mt-1 text-sm text-gray-500 truncate"
                data-testid="kpi-card-rating-title"
              >
                Ma note
              </span>
              <span
                class="text-xs text-gray-400 truncate"
                data-testid="kpi-card-rating-subtitle"
              >
                {{ ratingSubtitle }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Statistiques avancées Section (FR59) -->
    <section
      v-if="!statsError"
      class="mb-10"
      aria-labelledby="advanced-stats-section-title"
      data-testid="advanced-stats-section"
    >
      <h2 id="advanced-stats-section-title" class="text-lg font-semibold text-slate-800 mb-4">
        Statistiques avancées
      </h2>

      <div
        class="grid grid-cols-1 min-[426px]:grid-cols-2 lg:grid-cols-4 gap-6"
        data-testid="advanced-stats-grid"
      >
        <!-- Acceptance rate card with custom display for percentage -->
        <div
          v-if="statsLoading"
          class="bg-white rounded-2xl p-4 shadow-sm"
          data-testid="kpi-card-acceptance-rate-skeleton"
        >
          <div class="flex items-center gap-3">
            <Skeleton class="h-11 w-11 rounded-xl" />
            <div class="space-y-2">
              <Skeleton class="h-6 w-14" />
              <Skeleton class="h-4 w-20" />
            </div>
          </div>
        </div>

        <div
          v-else
          class="bg-white rounded-2xl p-4 shadow-sm transition-all duration-200 hover:shadow-md"
          :aria-label="`${PRODUCER_ACCEPTANCE_RATE_KPI.title}: ${formattedAcceptanceRate}`"
          data-testid="kpi-card-acceptance_rate"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex h-11 w-11 items-center justify-center rounded-xl shrink-0 bg-green-50 text-green-500"
            >
              <Check :size="20" stroke-width="2" />
            </div>

            <div class="flex flex-col min-w-0">
              <span
                class="text-xl font-bold text-gray-900 leading-none"
                data-testid="kpi-card-acceptance_rate-value"
              >
                {{ formattedAcceptanceRate }}
              </span>
              <span
                class="mt-1 text-sm text-gray-500 truncate"
                data-testid="kpi-card-acceptance_rate-title"
              >
                {{ PRODUCER_ACCEPTANCE_RATE_KPI.title }}
              </span>
            </div>
          </div>
        </div>

        <!-- Average response time card with custom display for hours -->
        <div
          v-if="statsLoading"
          class="bg-white rounded-2xl p-4 shadow-sm"
          data-testid="kpi-card-response-time-skeleton"
        >
          <div class="flex items-center gap-3">
            <Skeleton class="h-11 w-11 rounded-xl" />
            <div class="space-y-2">
              <Skeleton class="h-6 w-14" />
              <Skeleton class="h-4 w-20" />
            </div>
          </div>
        </div>

        <div
          v-else
          class="bg-white rounded-2xl p-4 shadow-sm transition-all duration-200 hover:shadow-md"
          :aria-label="`${PRODUCER_RESPONSE_TIME_KPI.title}: ${formattedResponseTime}`"
          data-testid="kpi-card-average_response_time_hours"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex h-11 w-11 items-center justify-center rounded-xl shrink-0 bg-blue-50 text-blue-500"
            >
              <Clock :size="20" stroke-width="2" />
            </div>

            <div class="flex flex-col min-w-0">
              <span
                class="text-xl font-bold text-gray-900 leading-none"
                data-testid="kpi-card-average_response_time_hours-value"
              >
                {{ formattedResponseTime }}
              </span>
              <span
                class="mt-1 text-sm text-gray-500 truncate"
                data-testid="kpi-card-average_response_time_hours-title"
              >
                {{ PRODUCER_RESPONSE_TIME_KPI.title }}
              </span>
              <span
                class="text-xs text-gray-400 truncate"
                data-testid="kpi-card-average_response_time_hours-subtitle"
              >
                {{ responseTimeSubtitle }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Quick Access Cards Section -->
    <section aria-labelledby="quick-access-section-title">
      <h2 id="quick-access-section-title" class="text-lg font-semibold text-slate-800 mb-4">
        Accès rapides
      </h2>
      <div
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
        data-testid="quick-access-cards-grid"
      >
        <!-- Profile Card -->
        <button
          @click="goToProfile"
          class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 group text-left"
          aria-label="Accéder à mon profil"
          data-testid="profile-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-teal-50 rounded-xl flex items-center justify-center group-hover:bg-teal-100 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-teal-600"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-medium text-slate-800">Mon profil</h3>
              <p class="text-sm text-slate-500">Gérer ma photo et mes informations</p>
            </div>
          </div>
        </button>

        <!-- Public Profile Card -->
        <button
          v-if="authStore.user?.userable?.id"
          @click="goToPublicProfile"
          class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 group text-left"
          aria-label="Voir mon profil public"
          data-testid="public-profile-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-teal-50 rounded-xl flex items-center justify-center group-hover:bg-teal-100 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-teal-600"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.573-3.007-9.963-7.178z"
                />
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-medium text-slate-800">Voir mon profil public</h3>
              <p class="text-sm text-slate-500">Comment les Faces voient mon profil</p>
            </div>
          </div>
        </button>

        <!-- Missions List Card -->
        <button
          @click="goToMissions"
          class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 group text-left"
          aria-label="Accéder à mes missions"
          data-testid="missions-list-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center group-hover:bg-primary/20 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-medium text-slate-800">Mes missions</h3>
              <p class="text-sm text-slate-500">Gérer mes castings en cours</p>
            </div>
          </div>
        </button>

        <!-- Publish Mission Card -->
        <button
          @click="goToPublishMission"
          class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 group text-left"
          aria-label="Publier une nouvelle mission"
          data-testid="publish-mission-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center group-hover:bg-primary/20 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 4.5v15m7.5-7.5h-15"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-medium text-slate-800">Publier une mission</h3>
              <p class="text-sm text-slate-500">Créer un nouveau casting</p>
            </div>
          </div>
        </button>

        <!-- Messages Card -->
        <button
          @click="goToMessages"
          class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 group text-left"
          aria-label="Accéder à mes messages"
          data-testid="messages-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-blue-500"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-medium text-slate-800">Messages</h3>
              <p class="text-sm text-slate-500">Discussions avec les Faces</p>
            </div>
          </div>
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}
.bg-primary\/10 {
  background-color: rgb(25 132 150 / 0.1);
}
.bg-primary\/20 {
  background-color: rgb(25 132 150 / 0.2);
}
</style>

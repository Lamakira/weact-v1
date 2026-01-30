<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { useProfileCompletion } from '@/features/face/composables/useProfileCompletion'
import ProfileCompletionCard from '@/features/face/components/ProfileCompletionCard.vue'
import { useDashboardStats, KpiCard, FACE_KPI_CONFIGS } from '@/features/dashboard'

const router = useRouter()
const authStore = useAuthStore()
const { logout, isLoading } = useAuth()

// Profile completion composable
const {
  isLoading: isCompletionLoading,
  percentage: completionPercentage,
  missingItems: completionMissingItems,
  fetchCompletion,
} = useProfileCompletion()

// Dashboard stats composable
const {
  stats,
  isLoading: isStatsLoading,
  error: statsError,
  fetchStats,
  retry: retryStats,
} = useDashboardStats()

// Fetch data on mount
onMounted(async () => {
  await Promise.all([fetchCompletion(), fetchStats()])
})

async function handleLogout(): Promise<void> {
  await logout()
}

function goToProfile(): void {
  router.push({ name: 'face-profile' })
}

function goToMissions(): void {
  router.push({ name: 'face-missions' })
}

function goToMessages(): void {
  router.push({ name: 'face-messages' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header with logout -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
            <span class="text-white font-bold">W</span>
          </div>
          <h1 class="text-xl font-semibold text-gray-900">Dashboard Face</h1>
        </div>

        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">
            {{ authStore.user?.email }}
          </span>
          <button
            @click="goToProfile"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors"
            data-testid="profile-button"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
              />
            </svg>
            Mon profil
          </button>
          <button
            @click="handleLogout"
            :disabled="isLoading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            data-testid="logout-button"
          >
            <svg
              v-if="!isLoading"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"
              />
            </svg>
            <svg
              v-else
              class="w-5 h-5 animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isLoading ? 'Déconnexion...' : 'Déconnexion' }}
          </button>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- KPI Cards Section -->
      <section class="mb-8" aria-labelledby="kpi-section-title">
        <h2 id="kpi-section-title" class="text-lg font-semibold text-gray-900 mb-4">
          Mes candidatures
        </h2>

        <!-- Error State -->
        <div
          v-if="statsError"
          class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center justify-between"
          data-testid="stats-error"
        >
          <div class="flex items-center gap-3">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-5 h-5 text-red-500"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
              />
            </svg>
            <span class="text-sm text-red-700">{{ statsError }}</span>
          </div>
          <button
            @click="retryStats"
            class="text-sm font-medium text-red-600 hover:text-red-800 underline"
            data-testid="retry-stats-button"
          >
            Réessayer
          </button>
        </div>

        <!-- KPI Cards Grid -->
        <div
          v-else
          class="grid grid-cols-2 lg:grid-cols-4 gap-4"
          data-testid="kpi-cards-grid"
        >
          <KpiCard
            v-for="kpi in FACE_KPI_CONFIGS"
            :key="kpi.key"
            :title="kpi.title"
            :value="stats?.[kpi.key] ?? 0"
            :icon="kpi.icon"
            :color="kpi.color"
            :is-loading="isStatsLoading"
            :data-testid="'kpi-card-' + kpi.key"
          />
        </div>
      </section>

      <!-- Dashboard cards grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Profile Completion Card -->
        <ProfileCompletionCard
          :percentage="completionPercentage"
          :missing-count="completionMissingItems.length"
          :is-loading="isCompletionLoading"
          data-testid="profile-completion-card"
        />

        <!-- Browse Missions Card -->
        <div
          class="bg-white rounded-lg shadow p-6 cursor-pointer hover:shadow-md transition-shadow border-l-4 border-primary"
          @click="goToMissions"
          data-testid="browse-missions-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
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
                  d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900">Voir les missions</h3>
              <p class="text-sm text-gray-500">Découvrez les opportunités disponibles</p>
            </div>
          </div>
        </div>

        <!-- Messages Card -->
        <div
          class="bg-white rounded-lg shadow p-6 cursor-pointer hover:shadow-md transition-shadow border-l-4 border-blue-500"
          @click="goToMessages"
          data-testid="messages-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
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
              <h3 class="text-lg font-semibold text-gray-900">Messages</h3>
              <p class="text-sm text-gray-500">Discussions avec les producteurs</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Welcome message -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Bienvenue sur votre Dashboard Face</h2>
        <p class="text-gray-600">
          Suivez vos candidatures et découvrez de nouvelles opportunités.
          Plus de fonctionnalités seront ajoutées prochainement.
        </p>
      </div>
    </main>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.bg-primary\/10 {
  background-color: rgba(25, 132, 150, 0.1);
}
.text-primary {
  color: #198496;
}
.border-primary {
  border-color: #198496;
}
.focus\:ring-primary:focus {
  --tw-ring-color: #198496;
}
</style>


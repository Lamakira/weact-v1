<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { useProfileCompletion } from '@/features/face/composables/useProfileCompletion'
import ProfileCompletionCard from '@/features/face/components/ProfileCompletionCard.vue'
import { User, LogOut, Loader2, AlertCircle } from 'lucide-vue-next'
import {
  useDashboardStats,
  useDashboardCharts,
  useMissionsCount,
  KpiCard,
  WalletCard,
  ActivityChart,
  MissionsQuickAccessCard,
  MessagesCard,
  FACE_KPI_CONFIGS,
} from '@/features/dashboard'

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

// Dashboard charts composable
const {
  candidaturesByMonth,
  missionsCompletedByMonth,
  isLoading: isChartsLoading,
  error: chartsError,
  fetchChartStats,
  retry: retryCharts,
} = useDashboardCharts()

// Missions count composable (for quick access card)
const {
  count: missionsCount,
  isLoading: isMissionsCountLoading,
  fetchMissionsCount,
} = useMissionsCount()

// Fetch data on mount
onMounted(async () => {
  await Promise.all([fetchCompletion(), fetchStats(), fetchChartStats(), fetchMissionsCount()])
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
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
          <!-- Logo and Title -->
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-primary rounded-full flex items-center justify-center"
              aria-hidden="true"
            >
              <span class="text-white font-bold text-lg">W</span>
            </div>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard Face</h1>
          </div>

          <!-- User Actions -->
          <div class="flex items-center gap-3 sm:gap-4 flex-wrap">
            <span
              class="text-sm text-gray-600 hidden sm:inline"
              data-testid="user-email"
            >
              {{ authStore.user?.email }}
            </span>
            <button
              @click="goToProfile"
              class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors"
              data-testid="profile-button"
              aria-label="Accéder à mon profil"
            >
              <User class="w-5 h-5" aria-hidden="true" />
              <span class="hidden sm:inline">Mon profil</span>
            </button>
            <button
              @click="handleLogout"
              :disabled="isLoading"
              class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              data-testid="logout-button"
              aria-label="Se déconnecter"
            >
              <LogOut v-if="!isLoading" class="w-5 h-5" aria-hidden="true" />
              <Loader2 v-else class="w-5 h-5 animate-spin" aria-hidden="true" />
              <span class="hidden sm:inline">{{ isLoading ? 'Déconnexion...' : 'Déconnexion' }}</span>
            </button>
          </div>
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
            <AlertCircle class="w-5 h-5 text-red-500" aria-hidden="true" />
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
          class="grid grid-cols-2 lg:grid-cols-4 gap-6"
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

      <!-- Quick Access Cards Section -->
      <section class="mb-8" aria-labelledby="quick-access-section-title">
        <h2 id="quick-access-section-title" class="text-lg font-semibold text-gray-900 mb-4">
          Accès rapides
        </h2>
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"
          data-testid="quick-access-cards-grid"
        >
          <!-- Wallet Card (Inactive MVP placeholder) -->
          <WalletCard />

          <!-- Profile Completion Card -->
          <ProfileCompletionCard
            :percentage="completionPercentage"
            :missing-count="completionMissingItems.length"
            :is-loading="isCompletionLoading"
            data-testid="profile-completion-card"
            @click="goToProfile"
          />

          <!-- Missions Quick Access Card -->
          <MissionsQuickAccessCard
            :count="missionsCount"
            :is-loading="isMissionsCountLoading"
            data-testid="browse-missions-card"
            @click="goToMissions"
          />

          <!-- Messages Card -->
          <MessagesCard
            data-testid="messages-card"
            @click="goToMessages"
          />
        </div>
      </section>

      <!-- Charts Section -->
      <section>
        <ActivityChart
          :candidatures-by-month="candidaturesByMonth"
          :missions-completed-by-month="missionsCompletedByMonth"
          :is-loading="isChartsLoading"
          :error="chartsError"
          @retry="retryCharts"
        />
      </section>
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


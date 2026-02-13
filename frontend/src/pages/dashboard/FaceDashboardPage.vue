<script setup lang="ts">
/**
 * FaceDashboardPage
 * Dashboard content for Face users - KPIs, quick access cards, and charts.
 * This component is rendered inside FaceLayout via nested routing.
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProfileCompletion } from '@/features/face/composables/useProfileCompletion'
import ProfileCompletionCard from '@/features/face/components/ProfileCompletionCard.vue'
import { AlertCircle } from 'lucide-vue-next'
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
  <div>
    <!-- KPI Cards Section -->
    <section class="mb-10" aria-labelledby="kpi-section-title">
      <h2 id="kpi-section-title" class="text-lg font-semibold text-slate-800 mb-4">
        Mes candidatures
      </h2>

      <!-- Error State -->
      <div
        v-if="statsError"
        class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-center justify-between"
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
        class="grid grid-cols-1 min-[376px]:grid-cols-2 lg:grid-cols-4 gap-6"
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
    <section class="mb-10" aria-labelledby="quick-access-section-title">
      <h2 id="quick-access-section-title" class="text-lg font-semibold text-slate-800 mb-4">
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
  </div>
</template>

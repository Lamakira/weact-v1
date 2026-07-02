<script setup lang="ts">
/**
 * FaceDashboardPage
 * Dashboard home for Face users — profile card + KPIs + quick access.
 * Two-column layout: profile photo (left) and stats/actions (right).
 */
import { computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useProfileCompletion } from '@/features/face/composables/useProfileCompletion'
import { useProfilePhoto } from '@/features/face/composables/useProfilePhoto'
import { useCategoryNiche } from '@/features/face/composables/useCategoryNiche'
import { useBioLocation } from '@/features/face/composables/useBioLocation'
import {
  AlertCircle,
  Pencil,
  Briefcase,
  MessageCircle,
  Clock,
  CheckCircle2,
  PlayCircle,
  CheckSquare,
  Wallet,
} from 'lucide-vue-next'
import {
  useDashboardStats,
  useDashboardCharts,
  useMissionsCount,
  useBookingStats,
  useDashboardBookingCharts,
  ActivityChart,
  BookingActivityChart,
} from '@/features/dashboard'
import { useWallet } from '@/features/wallet'
import { Skeleton } from '@/components/ui/skeleton'
import CurrentPlanCard from '@/features/face/components/CurrentPlanCard.vue'

const router = useRouter()
const {
  profile,
  isLoading: isProfileBaseLoading,
  fetchProfile,
} = useProfilePhoto()
const {
  categoryNicheInfo: categoryNiche,
  isLoading: isCategoryNicheLoading,
  fetchCategoryNiche,
} = useCategoryNiche()
const {
  bioLocationInfo: bioLocation,
  isLoading: isBioLocationLoading,
  fetchBioLocation,
} = useBioLocation()

// Profile completion composable
const {
  isLoading: isCompletionLoading,
  percentage: completionPercentage,
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

// Missions count composable
const {
  fetchMissionsCount,
} = useMissionsCount()

// Wallet balance composable
const { balance: walletBalance, fetchWallet } = useWallet()

// Booking stats composable
const {
  bookingStats,
  isLoading: isBookingStatsLoading,
  fetchBookingStats,
} = useBookingStats()

// Booking chart composable
const {
  bookingsByMonth,
  bookingsCompletedByMonth,
  isLoading: isBookingChartsLoading,
  error: bookingChartsError,
  fetchBookingChartStats,
  retry: retryBookingCharts,
} = useDashboardBookingCharts()

const isProfileLoading = computed(() => {
  return (
    (isProfileBaseLoading.value && profile.value === null) ||
    (isCategoryNicheLoading.value && categoryNiche.value === null) ||
    (isBioLocationLoading.value && bioLocation.value === null)
  )
})

// Computed values
const fullName = computed(() => {
  if (profile.value) return `${profile.value.prenom} ${profile.value.nom}`
  return ''
})

const initials = computed(() => {
  if (profile.value) {
    return `${profile.value.prenom.charAt(0)}${profile.value.nom.charAt(0)}`.toUpperCase()
  }
  return 'U'
})

const categoryDisplay = computed(() => {
  const parts: string[] = []
  if (categoryNiche.value?.categories?.length) {
    parts.push(...categoryNiche.value.categories.map((c) => c.label))
  }
  if (categoryNiche.value?.niches?.length) {
    parts.push(...categoryNiche.value.niches.map((n) => n.label))
  }
  return parts.join(' · ')
})

// SVG Circle math for profile completion
const radius = 36
const circumference = 2 * Math.PI * radius
const completionOffset = computed(() => circumference - (completionPercentage.value / 100) * circumference)

// Fetch all data on mount
onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    fetchCategoryNiche(),
    fetchBioLocation(),
    fetchCompletion(),
    fetchStats(),
    fetchChartStats(),
    fetchMissionsCount(),
    fetchWallet(),
    fetchBookingStats(),
    fetchBookingChartStats(),
  ])
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
  <div data-testid="face-dashboard-page">
    <div class="grid grid-cols-1 lg:grid-cols-10 gap-6 items-start">

      <!-- LEFT COLUMN: Profile Photo + Wallet (sticky) -->
      <div class="lg:col-span-3 lg:sticky lg:top-0 lg:self-start flex flex-col gap-4">
        <div
          class="relative h-96 rounded-2xl overflow-hidden shadow-sm group bg-white border border-gray-200 transition-all duration-200 hover:shadow-md"
          data-testid="profile-photo-card"
        >
          <!-- Loading skeleton -->
          <template v-if="isProfileLoading">
            <Skeleton class="absolute inset-0 w-full h-full rounded-2xl" />
          </template>

          <template v-else>
            <!-- Profile Photo / Fallback -->
            <div class="absolute inset-0 w-full h-full">
              <img
                v-if="profile?.profile_photo_url"
                :src="profile.profile_photo_url"
                :alt="fullName"
                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
              />
              <div
                v-else
                class="w-full h-full bg-gradient-to-br from-[#198496] to-[#146c7a] flex items-center justify-center"
              >
                <span class="text-white text-7xl font-bold tracking-tighter opacity-40">{{ initials }}</span>
              </div>
            </div>

            <!-- Bottom Overlay -->
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/50 to-transparent pt-20 flex flex-col justify-end p-5 sm:p-6 text-white">
              <h1 class="text-2xl sm:text-3xl font-bold mb-0.5" data-testid="profile-name">
                {{ fullName }}
              </h1>
              <p
                v-if="categoryDisplay"
                class="text-white/70 text-sm sm:text-base mb-3"
                data-testid="profile-category"
              >
                {{ categoryDisplay }}
              </p>

              <div class="flex items-center justify-between pt-3 border-t border-white/20">
                <div
                  v-if="bioLocation?.ville"
                  class="bg-white/20 backdrop-blur-md px-4 py-1.5 rounded-full text-sm font-medium flex items-center gap-2"
                  data-testid="profile-city"
                >
                  <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                  {{ bioLocation.ville }}
                </div>
                <div v-else></div>

                <button
                  @click="goToProfile"
                  class="flex items-center gap-2 bg-white text-gray-900 px-4 py-2 rounded-md font-medium text-sm hover:bg-gray-100 transition-all duration-200 active:scale-95"
                  data-testid="profile-edit-button"
                >
                  <Pencil :size="16" />
                  Modifier
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- Wallet Balance Card (always visible, anchored below photo) -->
        <RouterLink
          :to="{ name: 'face-wallet' }"
          class="group bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 transition-all duration-200 hover:border-[#198496] hover:shadow-md"
          data-testid="wallet-card"
        >
          <div class="w-11 h-11 rounded-xl bg-[#198496]/10 flex items-center justify-center text-[#198496] flex-shrink-0 group-hover:bg-[#198496]/20 transition-colors">
            <Wallet :size="20" />
          </div>
          <div class="min-w-0">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mon portefeuille</p>
            <p class="text-xl font-bold text-gray-900 mt-0.5">
              {{ new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(walletBalance) }}
            </p>
          </div>
          <span class="ml-auto text-xs font-medium text-[#198496] group-hover:underline flex-shrink-0">Gérer →</span>
        </RouterLink>

        <!-- Current Plan Card (FP-3.2) — tier + status, anchored below the wallet -->
        <CurrentPlanCard />
      </div>

      <!-- RIGHT COLUMN: Stats + Quick Access (scrolls with page) -->
      <div class="lg:col-span-7 flex flex-col gap-6">

        <!-- Stats Panel -->
        <div
          class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 shadow-sm flex flex-col"
          data-testid="stats-panel"
        >
          <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-6 sm:mb-8">Mes candidatures</h2>

          <!-- Error State -->
          <div
            v-if="statsError"
            class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between mb-6"
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

          <!-- Stats Grid -->
          <div
            v-else
            class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-6 mb-8 sm:mb-10"
            data-testid="kpi-cards-grid"
          >
            <!-- Pending -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="kpi-card-pending">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 flex-shrink-0">
                <template v-if="isStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <Clock v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">En attente</p>
                <template v-if="isStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ stats?.pending ?? 0 }}</p>
              </div>
            </div>

            <!-- Accepted -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="kpi-card-accepted">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-green-50 flex items-center justify-center text-green-600 flex-shrink-0">
                <template v-if="isStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <CheckCircle2 v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">Acceptées</p>
                <template v-if="isStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ stats?.accepted ?? 0 }}</p>
              </div>
            </div>

            <!-- In Progress -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="kpi-card-in_progress">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 flex-shrink-0">
                <template v-if="isStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <PlayCircle v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">En cours</p>
                <template v-if="isStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ stats?.in_progress ?? 0 }}</p>
              </div>
            </div>

            <!-- Completed -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="kpi-card-completed">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-[#198496]/10 flex items-center justify-center text-[#198496] flex-shrink-0">
                <template v-if="isStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <CheckSquare v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">Terminées</p>
                <template v-if="isStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ stats?.completed ?? 0 }}</p>
              </div>
            </div>
          </div>

          <!-- Profile Completion Section -->
          <div class="mt-auto pt-6 sm:pt-8 border-t border-gray-100 flex items-center gap-6 sm:gap-8">
            <div class="relative w-20 h-20 sm:w-24 sm:h-24 flex items-center justify-center flex-shrink-0">
              <template v-if="isCompletionLoading">
                <Skeleton class="w-20 h-20 sm:w-24 sm:h-24 rounded-full" />
              </template>
              <template v-else>
                <svg class="transform -rotate-90 w-20 h-20 sm:w-24 sm:h-24">
                  <circle
                    cx="50%"
                    cy="50%"
                    :r="radius"
                    stroke="currentColor"
                    stroke-width="6"
                    fill="transparent"
                    class="text-gray-100"
                  />
                  <circle
                    cx="50%"
                    cy="50%"
                    :r="radius"
                    stroke="currentColor"
                    stroke-width="6"
                    fill="transparent"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="completionOffset"
                    class="text-[#198496]"
                    stroke-linecap="round"
                  />
                </svg>
                <span
                  class="absolute text-lg sm:text-xl font-bold text-gray-900"
                  data-testid="profile-completion-percentage"
                >
                  {{ completionPercentage }}%
                </span>
              </template>
            </div>
            <div class="min-w-0">
              <p class="text-gray-500 font-medium mb-1 text-sm sm:text-base">Profil complété à</p>
              <p class="text-gray-900 font-bold text-base sm:text-lg">Optimisez votre visibilité</p>
              <p class="text-xs sm:text-sm text-gray-400">Plus votre profil est complet, plus vous attirez de producteurs.</p>
            </div>
          </div>
        </div>

        <!-- Booking Stats Panel -->
        <div
          class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 shadow-sm"
          data-testid="booking-stats-panel"
        >
          <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-6">Mes bookings</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-6">
            <!-- En attente -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="booking-kpi-pending">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 flex-shrink-0">
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <Clock v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">En attente</p>
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ bookingStats?.pending ?? 0 }}</p>
              </div>
            </div>

            <!-- Acceptés -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="booking-kpi-accepted">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-green-50 flex items-center justify-center text-green-600 flex-shrink-0">
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <CheckCircle2 v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">Acceptés</p>
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ bookingStats?.accepted ?? 0 }}</p>
              </div>
            </div>

            <!-- En cours -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="booking-kpi-in-progress">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 flex-shrink-0">
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <PlayCircle v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">En cours</p>
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ bookingStats?.in_progress ?? 0 }}</p>
              </div>
            </div>

            <!-- Terminés -->
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50" data-testid="booking-kpi-completed">
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-[#198496]/10 flex items-center justify-center text-[#198496] flex-shrink-0">
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <CheckSquare v-else :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">Terminés</p>
                <template v-if="isBookingStatsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ bookingStats?.completed ?? 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Access Cards — compact row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-testid="quick-access-cards-grid">
          <!-- Missions -->
          <button
            @click="goToMissions"
            class="group bg-white px-4 py-3 rounded-xl border border-gray-200 shadow-sm transition-all duration-200 hover:border-[#198496] hover:shadow-md flex items-center gap-3"
            data-testid="browse-missions-card"
          >
            <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-[#198496]/10 group-hover:text-[#198496] transition-colors flex-shrink-0">
              <Briefcase :size="16" />
            </div>
            <p class="font-semibold text-gray-900 text-xs sm:text-sm truncate">Missions</p>
            <span class="ml-auto text-[10px] sm:text-xs font-medium text-[#198496] group-hover:underline flex-shrink-0">Voir tout</span>
          </button>

          <!-- Messages -->
          <button
            @click="goToMessages"
            class="group bg-white px-4 py-3 rounded-xl border border-gray-200 shadow-sm transition-all duration-200 hover:border-[#198496] hover:shadow-md flex items-center gap-3"
            data-testid="messages-card"
          >
            <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-[#198496]/10 group-hover:text-[#198496] transition-colors flex-shrink-0">
              <MessageCircle :size="16" />
            </div>
            <p class="font-semibold text-gray-900 text-xs sm:text-sm truncate">Messages</p>
          </button>
        </div>

      </div>
    </div>

    <!-- Candidatures Charts -->
    <section class="mt-8">
      <ActivityChart
        :candidatures-by-month="candidaturesByMonth"
        :missions-completed-by-month="missionsCompletedByMonth"
        :is-loading="isChartsLoading"
        :error="chartsError"
        @retry="retryCharts"
      />
    </section>

    <!-- Booking Charts -->
    <section class="mt-8">
      <BookingActivityChart
        :bookings-by-month="bookingsByMonth"
        :bookings-completed-by-month="bookingsCompletedByMonth"
        :is-loading="isBookingChartsLoading"
        :error="bookingChartsError"
        @retry="retryBookingCharts"
      />
    </section>
  </div>
</template>

<style scoped>
/* Smooth circular progress animation */
svg circle {
  transition: stroke-dashoffset 1.5s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>

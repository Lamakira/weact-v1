<script setup lang="ts">
/**
 * ProducerDashboardPage
 * Dashboard home for Producer users — profile card + KPIs + quick access.
 * Two-column layout: profile photo (left) and stats/actions (right).
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  RefreshCw,
  Star,
  Check,
  Clock,
  AlertCircle,
  Pencil,
  Briefcase,
  MessageCircle,
  PlusCircle,
  Eye,
  Users,
  UserCheck,
  CheckCircle2,
  PlayCircle,
  CheckSquare,
  XCircle,
} from 'lucide-vue-next'
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
import { producerApi } from '@/features/producer/services/producerApi'
import type { Producer } from '@/features/producer/types'

const router = useRouter()
const authStore = useAuthStore()
const { stats, isLoading: statsLoading, error: statsError, fetchStats, retry } = useProducerDashboardStats()

// Profile data
const profile = ref<Producer | null>(null)
const isProfileLoading = ref(true)

onMounted(async () => {
  const profilePromise = producerApi.getProfile()
    .then((res) => { profile.value = res.data })
    .catch(() => { /* Silently fail */ })
    .finally(() => { isProfileLoading.value = false })

  await Promise.all([profilePromise, fetchStats()])
})

// Computed values
const displayName = computed(() => profile.value?.display_name ?? '')

// Profile photo: prefer profile_photo_url, fall back to agency_logo_url for agency-type producers
const profilePhotoUrl = computed(() => {
  if (!profile.value) return null
  return profile.value.profile_photo_url ?? profile.value.agency_logo_url ?? null
})

const initials = computed(() => {
  const name = displayName.value
  if (!name) return 'P'
  const parts = name.split(' ')
  return parts.length >= 2
    ? `${parts[0]!.charAt(0)}${parts[1]!.charAt(0)}`.toUpperCase()
    : name.charAt(0).toUpperCase()
})

const profileSubtitle = computed(() => {
  if (profile.value?.type === 'agency' && profile.value?.agency_name) {
    return profile.value.agency_name
  }
  return profile.value?.type === 'agency' ? 'Agence' : 'Producteur indépendant'
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
  if (count === 0) return 'Aucun avis'
  return `${count} avis`
})

// Computed values for advanced stats (FR59)
const formattedAcceptanceRate = computed(() => {
  const rate = stats.value?.acceptance_rate
  if (rate === null || rate === undefined) return '--'
  return `${rate.toFixed(1)}%`
})

const formattedResponseTime = computed(() => {
  const hours = stats.value?.average_response_time_hours
  if (hours === null || hours === undefined) return 'N/A'
  return `${hours.toFixed(1)}h`
})

const responseTimeSubtitle = computed(() => {
  const hours = stats.value?.average_response_time_hours
  if (hours === null || hours === undefined) return 'Aucune décision'
  return 'Délai moyen'
})

// KPI icon mapping
const kpiIcons: Record<string, typeof Clock> = {
  published: CheckCircle2,
  in_progress: PlayCircle,
  closed: XCircle,
  completed: CheckSquare,
}

const kpiColors: Record<string, string> = {
  published: 'bg-[#198496]/10 text-[#198496]',
  in_progress: 'bg-blue-50 text-blue-600',
  closed: 'bg-amber-50 text-amber-600',
  completed: 'bg-green-50 text-green-600',
}

// Navigation
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
  <div data-testid="producer-dashboard-page">
    <div class="grid grid-cols-1 lg:grid-cols-10 gap-6 items-stretch">

      <!-- LEFT COLUMN: Profile Photo Card -->
      <div class="lg:col-span-4 h-full">
        <div
          class="relative h-full min-h-[420px] lg:min-h-full rounded-2xl overflow-hidden shadow-sm group bg-white border border-gray-200 transition-all duration-200 hover:shadow-md"
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
                v-if="profilePhotoUrl"
                :src="profilePhotoUrl"
                :alt="displayName"
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
                {{ displayName }}
              </h1>
              <p
                v-if="profileSubtitle"
                class="text-white/70 text-sm sm:text-base mb-3"
                data-testid="profile-subtitle"
              >
                {{ profileSubtitle }}
              </p>

              <div class="flex items-center justify-between pt-3 border-t border-white/20">
                <button
                  v-if="authStore.user?.userable?.id"
                  @click="goToPublicProfile"
                  class="flex items-center gap-2 bg-white/20 backdrop-blur-md text-white px-4 py-1.5 rounded-full text-sm font-medium hover:bg-white/30 transition-all duration-200"
                  data-testid="public-profile-button"
                >
                  <Eye :size="14" />
                  Voir profil public
                </button>
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
      </div>

      <!-- RIGHT COLUMN: Stats + Quick Access -->
      <div class="lg:col-span-6 flex flex-col gap-6">

        <!-- Stats Panel: Mes missions -->
        <div
          class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 shadow-sm flex flex-col"
          data-testid="stats-panel"
        >
          <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-6 sm:mb-8">Mes missions</h2>

          <!-- Error State -->
          <div
            v-if="statsError"
            class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between mb-6"
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
              class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors disabled:opacity-50"
              data-testid="retry-button"
            >
              <RefreshCw :class="{ 'animate-spin': statsLoading }" :size="16" />
              Réessayer
            </button>
          </div>

          <!-- Mission KPIs Grid -->
          <div
            v-else
            class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-6 mb-6"
            data-testid="kpi-cards-grid"
          >
            <div
              v-for="kpi in PRODUCER_KPI_CONFIGS"
              :key="kpi.key"
              class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-xl bg-gray-50/50"
              :data-testid="'kpi-card-' + kpi.key"
            >
              <div
                class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center flex-shrink-0"
                :class="kpiColors[kpi.key] || 'bg-gray-50 text-gray-500'"
              >
                <template v-if="statsLoading">
                  <Skeleton class="w-5 h-5 sm:w-6 sm:h-6 rounded" />
                </template>
                <component v-else :is="kpiIcons[kpi.key] || CheckSquare" :size="22" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-xs sm:text-sm font-medium uppercase tracking-wider truncate">{{ kpi.title }}</p>
                <template v-if="statsLoading">
                  <Skeleton class="h-7 w-10 mt-1" />
                </template>
                <p v-else class="text-xl sm:text-2xl font-bold text-gray-900">{{ stats?.[kpi.key] ?? 0 }}</p>
              </div>
            </div>
          </div>

          <!-- Candidatures & Reputation row -->
          <div v-if="!statsError" class="pt-6 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Candidatures & Réputation</h3>
          </div>
          <div
            v-if="!statsError"
            class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4"
            data-testid="candidatures-kpi-grid"
          >
            <!-- Candidatures reçues -->
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50/50" data-testid="kpi-card-total_candidatures">
              <div class="w-9 h-9 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 flex-shrink-0">
                <template v-if="statsLoading"><Skeleton class="w-4 h-4 rounded" /></template>
                <Users v-else :size="18" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-[10px] sm:text-xs font-medium uppercase tracking-wider truncate">Candidatures</p>
                <template v-if="statsLoading"><Skeleton class="h-5 w-8 mt-0.5" /></template>
                <p v-else class="text-lg font-bold text-gray-900">{{ stats?.total_candidatures ?? 0 }}</p>
              </div>
            </div>

            <!-- Collaborateurs -->
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50/50" data-testid="kpi-card-unique_collaborators">
              <div class="w-9 h-9 rounded-full bg-green-50 flex items-center justify-center text-green-600 flex-shrink-0">
                <template v-if="statsLoading"><Skeleton class="w-4 h-4 rounded" /></template>
                <UserCheck v-else :size="18" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-[10px] sm:text-xs font-medium uppercase tracking-wider truncate">Collaborateurs</p>
                <template v-if="statsLoading"><Skeleton class="h-5 w-8 mt-0.5" /></template>
                <p v-else class="text-lg font-bold text-gray-900">{{ stats?.unique_collaborators ?? 0 }}</p>
              </div>
            </div>

            <!-- Ma note -->
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50/50" data-testid="kpi-card-rating">
              <div class="w-9 h-9 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 flex-shrink-0">
                <template v-if="statsLoading"><Skeleton class="w-4 h-4 rounded" /></template>
                <Star v-else :size="18" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-[10px] sm:text-xs font-medium uppercase tracking-wider truncate">Ma note</p>
                <template v-if="statsLoading"><Skeleton class="h-5 w-8 mt-0.5" /></template>
                <template v-else>
                  <p class="text-lg font-bold text-gray-900" data-testid="kpi-card-rating-value">{{ formattedRating }}</p>
                  <p class="text-[10px] text-gray-400" data-testid="kpi-card-rating-subtitle">{{ ratingSubtitle }}</p>
                </template>
              </div>
            </div>

            <!-- Taux d'acceptation -->
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50/50" data-testid="kpi-card-acceptance_rate">
              <div class="w-9 h-9 rounded-full bg-green-50 flex items-center justify-center text-green-500 flex-shrink-0">
                <template v-if="statsLoading"><Skeleton class="w-4 h-4 rounded" /></template>
                <Check v-else :size="18" />
              </div>
              <div class="min-w-0">
                <p class="text-gray-500 text-[10px] sm:text-xs font-medium uppercase tracking-wider truncate">Acceptation</p>
                <template v-if="statsLoading"><Skeleton class="h-5 w-8 mt-0.5" /></template>
                <p v-else class="text-lg font-bold text-gray-900" data-testid="kpi-card-acceptance_rate-value">{{ formattedAcceptanceRate }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Access Cards — compact row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" data-testid="quick-access-cards-grid">
          <!-- Missions -->
          <button
            @click="goToMissions"
            class="group bg-white px-4 py-3 rounded-xl border border-gray-200 shadow-sm transition-all duration-200 hover:border-[#198496] hover:shadow-md flex items-center gap-3"
            data-testid="missions-list-card"
          >
            <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-[#198496]/10 group-hover:text-[#198496] transition-colors flex-shrink-0">
              <Briefcase :size="16" />
            </div>
            <p class="font-semibold text-gray-900 text-xs sm:text-sm truncate">Mes missions</p>
            <span class="ml-auto text-[10px] sm:text-xs font-medium text-[#198496] group-hover:underline flex-shrink-0">Voir tout</span>
          </button>

          <!-- Publish Mission -->
          <button
            @click="goToPublishMission"
            class="group bg-white px-4 py-3 rounded-xl border border-gray-200 shadow-sm transition-all duration-200 hover:border-[#198496] hover:shadow-md flex items-center gap-3"
            data-testid="publish-mission-card"
          >
            <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-[#198496]/10 group-hover:text-[#198496] transition-colors flex-shrink-0">
              <PlusCircle :size="16" />
            </div>
            <p class="font-semibold text-gray-900 text-xs sm:text-sm truncate">Publier</p>
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
  </div>
</template>

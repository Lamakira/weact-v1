<script setup lang="ts">
/**
 * FaceDashboardPage
 * Dashboard home for Face users — direction « Régie » :
 * en-tête profil + « À faire » + matrice d'activité + portefeuille / profil / abonnement.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { Camera, Eye } from 'lucide-vue-next'
import { useProfileCompletion } from '@/features/face/composables/useProfileCompletion'
import { useProfilePhoto } from '@/features/face/composables/useProfilePhoto'
import { useCategoryNiche } from '@/features/face/composables/useCategoryNiche'
import { useBioLocation } from '@/features/face/composables/useBioLocation'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import { TIER_PRESENTATION, displayTierOf } from '@/features/face/tierPresentation'
import {
  useDashboardStats,
  useDashboardCharts,
  useBookingStats,
  useDashboardBookingCharts,
  useFaceDashboardTodo,
} from '@/features/dashboard'
import FaceTodoPanel from '@/features/dashboard/components/FaceTodoPanel.vue'
import FaceActivityMatrix from '@/features/dashboard/components/FaceActivityMatrix.vue'
import FaceWalletPanel from '@/features/dashboard/components/FaceWalletPanel.vue'
import FaceProfilePanel from '@/features/dashboard/components/FaceProfilePanel.vue'
import { useWallet } from '@/features/wallet'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import CurrentPlanCard from '@/features/face/components/CurrentPlanCard.vue'

const router = useRouter()
const { profile, isLoading: isProfileBaseLoading, fetchProfile } = useProfilePhoto()
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

const {
  isLoading: isCompletionLoading,
  percentage: completionPercentage,
  missingItems,
  fetchCompletion,
} = useProfileCompletion()

const {
  stats,
  isLoading: isStatsLoading,
  error: statsError,
  fetchStats,
  retry: retryStats,
} = useDashboardStats()

const { candidaturesByMonth, fetchChartStats } = useDashboardCharts()

const {
  balance: walletBalance,
  pendingEscrow,
  withdrawalRequests,
  isLoading: isWalletLoading,
  fetchWallet,
} = useWallet()

const { bookingStats, isLoading: isBookingStatsLoading, fetchBookingStats } = useBookingStats()

const { bookingsByMonth, fetchBookingChartStats } = useDashboardBookingCharts()

const {
  items: todoItems,
  isLoading: isTodoLoading,
  error: todoError,
  fetchTodo,
  retry: retryTodo,
} = useFaceDashboardTodo()

const { current: subscription, fetchStatus: fetchSubscriptionStatus } = useSubscriptionStatus()

const isProfileLoading = computed(() => {
  return (
    (isProfileBaseLoading.value && profile.value === null) ||
    (isCategoryNicheLoading.value && categoryNiche.value === null) ||
    (isBioLocationLoading.value && bioLocation.value === null)
  )
})

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

// Photo illisible (URL cassée) : repli sur les initiales ; réarmé si l'URL change
const photoFailed = ref(false)
watch(
  () => profile.value?.profile_photo_url,
  () => {
    photoFailed.value = false
  },
)

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

// Badge de plan : même règle que le panneau Abonnement (plan déchu si annulé/expiré)
const planName = computed(() =>
  subscription.value ? TIER_PRESENTATION[displayTierOf(subscription.value)].name : null,
)

// Dernier virement = dernier retrait approuvé (date de traitement)
const lastWithdrawalAt = computed<string | null>(() => {
  const processed = withdrawalRequests.value
    .filter((r) => r.status === 'approved' && r.processed_at)
    .map((r) => r.processed_at as string)
    .sort()
  return processed.length ? (processed[processed.length - 1] ?? null) : null
})

onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    fetchCategoryNiche(),
    fetchBioLocation(),
    fetchCompletion(),
    fetchStats(),
    fetchChartStats(),
    fetchWallet(),
    fetchBookingStats(),
    fetchBookingChartStats(),
    fetchTodo(),
    fetchSubscriptionStatus(),
  ])
})

// La photo se change depuis la fiche (même flux que l'ancien bouton « Modifier »)
function goToProfile(): void {
  router.push({ name: 'face-profile' })
}
</script>

<template>
  <div class="space-y-4" data-testid="face-dashboard-page">
    <h1 class="sr-only">Tableau de bord</h1>

    <!-- En-tête profil -->
    <header class="flex items-center gap-3" data-testid="face-dashboard-header">
      <template v-if="isProfileLoading">
        <Skeleton class="size-10 rounded-full" />
        <div class="space-y-2">
          <Skeleton class="h-4 w-40" />
          <Skeleton class="h-3 w-56" />
        </div>
      </template>

      <template v-else>
        <div class="relative shrink-0" data-testid="profile-photo-card">
          <div class="size-10 overflow-hidden rounded-full bg-sidebar ring-1 ring-line">
            <img
              v-if="profile?.profile_photo_url && !photoFailed"
              :src="profile.thumbnail_url || profile.profile_photo_url"
              :alt="fullName"
              class="size-full object-cover"
              @error="photoFailed = true"
            />
            <span
              v-else
              class="grid size-full place-items-center bg-weact-50 text-[13px] font-semibold text-weact-700"
              data-testid="profile-avatar-fallback"
            >
              {{ initials }}
            </span>
          </div>
          <button
            type="button"
            class="absolute -bottom-1 -right-1 grid size-5 place-items-center rounded-full bg-white text-ink-2 ring-1 ring-line after:absolute after:-inset-2 after:content-[''] hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
            aria-label="Changer la photo de profil"
            data-testid="profile-photo-edit"
            @click="goToProfile"
          >
            <Camera class="size-3" aria-hidden="true" />
          </button>
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-[16px] font-semibold tracking-[-0.01em] text-ink" data-testid="profile-name">
            {{ fullName }}
          </p>
          <p class="flex min-w-0 items-center gap-2 text-[12.5px] text-ink-3">
            <span v-if="categoryDisplay" class="truncate" data-testid="profile-category">{{ categoryDisplay }}</span>
            <span v-if="bioLocation?.ville" class="shrink-0" data-testid="profile-city">{{ bioLocation.ville }}</span>
            <span
              v-if="planName"
              class="shrink-0 rounded-full px-2 py-0.5 text-[11.5px] font-medium text-ink-2 ring-1 ring-line"
              data-testid="profile-plan-badge"
            >
              {{ planName }}
            </span>
          </p>
        </div>
      </template>

      <!-- Actions d'en-tête : une seule visible sur mobile -->
      <div class="ml-auto flex shrink-0 items-center gap-2">
        <Button
          v-if="profile?.username"
          as-child
          variant="regie-secondary"
          size="regie"
          class="hidden md:inline-flex"
        >
          <RouterLink
            :to="{ name: 'public-face-profile', params: { username: profile.username } }"
            data-testid="public-profile-link"
          >
            <Eye aria-hidden="true" />
            Fiche publique
          </RouterLink>
        </Button>
        <Button type="button" variant="regie" size="regie" data-testid="profile-edit-button" @click="goToProfile">
          Modifier le profil
        </Button>
      </div>
    </header>

    <!-- À faire -->
    <FaceTodoPanel :items="todoItems" :is-loading="isTodoLoading" :error="todoError" @retry="retryTodo" />

    <!-- Activité -->
    <div data-testid="stats-panel">
      <div
        v-if="statsError"
        class="flex items-center justify-between gap-3 rounded-panel bg-white p-4 ring-1 ring-line"
        data-testid="stats-error"
      >
        <span class="text-dash text-ink-2">{{ statsError }}</span>
        <Button type="button" variant="regie-secondary" size="regie" data-testid="retry-stats-button" @click="retryStats">
          Réessayer
        </Button>
      </div>
      <FaceActivityMatrix
        v-else
        :stats="stats"
        :booking-stats="bookingStats"
        :candidatures-by-month="candidaturesByMonth"
        :bookings-by-month="bookingsByMonth"
        :is-loading="isStatsLoading || isBookingStatsLoading"
      />
    </div>

    <!-- Portefeuille / Profil / Abonnement -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3" data-testid="face-dashboard-bottom-row">
      <FaceWalletPanel
        :balance="walletBalance"
        :pending-escrow="pendingEscrow"
        :last-withdrawal-at="lastWithdrawalAt"
        :is-loading="isWalletLoading"
      />
      <FaceProfilePanel
        :percentage="completionPercentage"
        :missing-items="missingItems"
        :is-loading="isCompletionLoading"
      />
      <CurrentPlanCard />
    </div>
  </div>
</template>

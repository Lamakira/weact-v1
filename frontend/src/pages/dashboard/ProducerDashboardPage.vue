<script setup lang="ts">
/**
 * ProducerDashboardPage
 * Dashboard home for Producer users (direction « Régie ») : en-tête (logo, nom,
 * Fiche publique, Publier une mission), rangée de 4 indicateurs missions, puis 4
 * modules de travail (à valider, messages non lus, missions actives, réputation).
 */
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Eye, Plus } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useProducerDashboardStats } from '@/features/dashboard/composables/useProducerDashboardStats'
import { useProducerDashboardModules } from '@/features/dashboard/composables/useProducerDashboardModules'
import { useProducerProfilePhoto } from '@/features/producer/composables/useProducerProfilePhoto'
import ProducerKpiRow from '@/features/dashboard/components/producer/ProducerKpiRow.vue'
import ToValidatePanel from '@/features/dashboard/components/producer/ToValidatePanel.vue'
import UnreadMessagesPanel from '@/features/dashboard/components/producer/UnreadMessagesPanel.vue'
import ActiveMissionsPanel from '@/features/dashboard/components/producer/ActiveMissionsPanel.vue'
import ReputationPanel from '@/features/dashboard/components/producer/ReputationPanel.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { buttonVariants } from '@/components/ui/button'

const authStore = useAuthStore()
const { stats, isLoading: statsLoading, error: statsError, fetchStats, retry } = useProducerDashboardStats()
const { profile, isLoading: isProfileLoading, fetchProfile } = useProducerProfilePhoto()
const modules = useProducerDashboardModules()

onMounted(async () => {
  await Promise.all([fetchProfile(), fetchStats(), modules.fetchAll()])
})

const displayName = computed(() => profile.value?.display_name ?? '')
const isAgency = computed(() => profile.value?.type === 'agency')

// Square logo for an agency, profile photo for an individual (the other as fallback)
const logoUrl = computed(() => {
  if (!profile.value) return null
  return isAgency.value
    ? (profile.value.agency_logo_url ?? profile.value.profile_photo_url ?? null)
    : (profile.value.profile_photo_url ?? profile.value.agency_logo_url ?? null)
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
  if (!profile.value) return ''
  const kind = isAgency.value ? 'Agence' : 'Producteur indépendant'
  const rating = stats.value?.average_rating
  const count = stats.value?.ratings_count ?? 0
  if (rating === null || rating === undefined || count === 0) return kind
  return `${kind} · ★ ${rating.toFixed(1).replace('.', ',')} (${count} avis)`
})

// The public route is `/producers/:slug`: an id param never matched it.
const publicProfileSlug = computed(() => {
  const userable = authStore.user?.userable
  return userable && 'slug' in userable ? userable.slug : null
})
</script>

<template>
  <div class="space-y-5" data-testid="producer-dashboard-page">
    <!-- En-tête : logo + nom, actions (une seule visible sur mobile) -->
    <header class="flex items-center gap-3" data-testid="producer-dashboard-header">
      <div
        class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-[10px] bg-weact-700 text-[13px] font-semibold text-white"
        data-testid="profile-photo-card"
      >
        <Skeleton v-if="isProfileLoading" class="size-full rounded-none" />
        <img v-else-if="logoUrl" :src="logoUrl" :alt="displayName" class="size-full object-cover" />
        <span v-else aria-hidden="true">{{ initials }}</span>
      </div>

      <div class="min-w-0 flex-1">
        <h1 class="truncate text-[17px] font-semibold leading-tight tracking-[-0.02em] text-ink" data-testid="profile-name">
          {{ displayName }}
        </h1>
        <p v-if="profileSubtitle" class="truncate text-[12.5px] text-ink-3" data-testid="profile-subtitle">
          {{ profileSubtitle }}
        </p>
      </div>

      <RouterLink
        v-if="publicProfileSlug"
        :to="{ name: 'public-producer-profile', params: { slug: publicProfileSlug } }"
        :class="[buttonVariants({ variant: 'regie-secondary', size: 'regie' }), 'hidden md:inline-flex']"
        data-testid="public-profile-button"
      >
        <Eye aria-hidden="true" />
        Fiche publique
      </RouterLink>

      <RouterLink
        :to="{ name: 'publish-mission' }"
        :class="buttonVariants({ variant: 'regie', size: 'regie' })"
        aria-label="Publier une mission"
        data-testid="publish-mission-button"
      >
        <Plus aria-hidden="true" />
        <span class="sm:hidden">Publier</span>
        <span class="hidden sm:inline">Publier une mission</span>
      </RouterLink>
    </header>

    <ProducerKpiRow :stats="stats" :is-loading="statsLoading" :error="statsError" @retry="retry" />

    <!-- 4 modules : empilés sur mobile, grille 2 × 2 dès lg -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2" data-testid="producer-dashboard-modules">
      <ToValidatePanel
        :items="modules.toValidate.value"
        :total="modules.toValidateTotal.value"
        :is-loading="modules.toValidateLoading.value"
        :error="modules.toValidateError.value"
        @retry="modules.fetchToValidate"
      />
      <UnreadMessagesPanel
        :items="modules.unreadConversations.value"
        :total="modules.unreadTotal.value"
        :is-loading="modules.unreadLoading.value"
        :error="modules.unreadError.value"
        @retry="modules.fetchUnreadMessages"
      />
      <ActiveMissionsPanel
        :items="modules.activeMissions.value"
        :total="modules.activeMissionsTotal.value"
        :is-loading="modules.activeMissionsLoading.value"
        :error="modules.activeMissionsError.value"
        @retry="modules.fetchActiveMissions"
      />
      <ReputationPanel :stats="stats" :is-loading="statsLoading" :error="statsError" @retry="retry" />
    </div>
  </div>
</template>

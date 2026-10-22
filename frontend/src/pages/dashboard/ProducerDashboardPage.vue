<script setup lang="ts">
/**
 * ProducerDashboardPage
 * Dashboard home for Producer users (direction « Régie »), en deux colonnes dès lg :
 * - colonne principale : en-tête personnel (« Bonjour {prénom} » + phrase qui résume ce
 *   qui attend + Publier une mission), file « À faire » pleine largeur, 4 indicateurs
 *   missions, puis les modules « Livrables à valider » et « Réputation » ;
 * - colonne de droite : « Missions actives » (barre Faces confirmées / voulues) et
 *   « Messages non lus » (fils de mission et de booking).
 * Sur téléphone : une seule colonne, la to-do d'abord.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Eye, Plus } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useProducerDashboardStats } from '@/features/dashboard/composables/useProducerDashboardStats'
import { useProducerDashboardModules } from '@/features/dashboard/composables/useProducerDashboardModules'
import { useProducerProfilePhoto } from '@/features/producer/composables/useProducerProfilePhoto'
import { useProducerDashboardTodo } from '@/features/dashboard/composables/useProducerDashboardTodo'
import ProducerTodoPanel from '@/features/dashboard/components/producer/ProducerTodoPanel.vue'
import ProducerKpiRow from '@/features/dashboard/components/producer/ProducerKpiRow.vue'
import ToValidatePanel from '@/features/dashboard/components/producer/ToValidatePanel.vue'
import UnreadMessagesPanel from '@/features/dashboard/components/producer/UnreadMessagesPanel.vue'
import ActiveMissionsPanel from '@/features/dashboard/components/producer/ActiveMissionsPanel.vue'
import ReputationPanel from '@/features/dashboard/components/producer/ReputationPanel.vue'
import PushSoftPrompt from '@/features/notification/components/PushSoftPrompt.vue'
import { buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'

const authStore = useAuthStore()
const { stats, isLoading: statsLoading, error: statsError, fetchStats, retry } = useProducerDashboardStats()
const { profile, fetchProfile } = useProducerProfilePhoto()
const modules = useProducerDashboardModules()
const {
  items: todoItems,
  isLoading: isTodoLoading,
  error: todoError,
  fetchTodo,
  retry: retryTodo,
} = useProducerDashboardTodo()

// Phrase de synthèse masquée tant que la file n'a pas été lue une première fois :
// jamais de « Rien à traiter » avant d'avoir regardé.
const todoSettled = ref(false)

onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    fetchStats(),
    modules.fetchAll(),
    fetchTodo().finally(() => { todoSettled.value = true }),
  ])
})

const displayName = computed(() => profile.value?.display_name ?? '')

// Prénom du Producteur ; une agence n'en a pas : on la salue par son nom d'affichage.
const greetingName = computed(() => profile.value?.first_name?.trim() || displayName.value)

const todoSummary = computed(() => {
  if (!todoSettled.value || todoError.value) return ''
  const n = todoItems.value.length
  if (n === 0) return 'Rien à traiter pour le moment.'
  if (n === 1) return '1 action vous attend.'
  return `${n} actions vous attendent, la plus urgente en premier.`
})

// The public route is `/producers/:slug`: an id param never matched it.
const publicProfileSlug = computed(() => {
  const userable = authStore.user?.userable
  return userable && 'slug' in userable ? userable.slug : null
})
</script>

<template>
  <div class="space-y-5" data-testid="producer-dashboard-page">
    <!-- En-tête personnel : salutation, phrase de synthèse, actions (une seule visible sur mobile) -->
    <header class="flex flex-wrap items-end justify-between gap-3" data-testid="producer-dashboard-header">
      <div class="min-w-0 flex-1">
        <h1
          class="truncate text-[22px] font-semibold leading-tight tracking-[-0.02em] text-ink max-md:text-[19px]"
          data-testid="profile-name"
        >
          {{ greetingName ? `Bonjour ${greetingName}` : 'Bonjour' }}
        </h1>
        <p v-if="todoSummary" class="mt-0.5 text-dash text-ink-3" data-testid="todo-summary">
          {{ todoSummary }}
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <RouterLink
          v-if="publicProfileSlug"
          :to="{ name: 'public-producer-profile', params: { slug: publicProfileSlug } }"
          :class="cn(buttonVariants({ variant: 'regie-secondary', size: 'regie' }), 'hidden md:inline-flex')"
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
      </div>
    </header>

    <!-- Invite web push (une fois, fermable) -->
    <PushSoftPrompt audience="producer" />

    <div
      class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start"
      data-testid="producer-dashboard-columns"
    >
      <!-- Colonne principale : la to-do d'abord -->
      <div class="min-w-0 space-y-5" data-testid="producer-dashboard-main">
        <ProducerTodoPanel :items="todoItems" :is-loading="isTodoLoading" :error="todoError" @retry="retryTodo" />

        <ProducerKpiRow :stats="stats" :is-loading="statsLoading" :error="statsError" @retry="retry" />

        <!-- Modules conservés : livrables à valider (travail à traiter) et réputation -->
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2" data-testid="producer-dashboard-modules">
          <ToValidatePanel
            :items="modules.toValidate.value"
            :total="modules.toValidateTotal.value"
            :is-loading="modules.toValidateLoading.value"
            :error="modules.toValidateError.value"
            @retry="modules.fetchToValidate"
          />
          <ReputationPanel :stats="stats" :is-loading="statsLoading" :error="statsError" @retry="retry" />
        </div>
      </div>

      <!-- Colonne de droite : missions actives et messages non lus -->
      <aside class="min-w-0 space-y-5" data-testid="producer-dashboard-aside" aria-label="Missions et messages">
        <ActiveMissionsPanel
          :items="modules.activeMissions.value"
          :total="modules.activeMissionsTotal.value"
          :is-loading="modules.activeMissionsLoading.value"
          :error="modules.activeMissionsError.value"
          @retry="modules.fetchActiveMissions"
        />
        <UnreadMessagesPanel
          :items="modules.unreadConversations.value"
          :total="modules.unreadTotal.value"
          :is-loading="modules.unreadLoading.value"
          :error="modules.unreadError.value"
          @retry="modules.fetchUnreadMessages"
        />
      </aside>
    </div>
  </div>
</template>

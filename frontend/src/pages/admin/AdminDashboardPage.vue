<script setup lang="ts">
/**
 * AdminDashboardPage
 * Admin dashboard with global platform KPIs.
 * (FR68) — Admin Dashboard KPIs
 */
import { onMounted } from 'vue'
import {
  RefreshCw,
  AlertCircle,
  FilePlus,
  Briefcase,
  UserCheck,
  Building,
  TrendingUp,
  Users,
  Target,
  Activity,
  Clock,
  FileText,
  UserPlus,
} from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { useAdminDashboardStats } from '@/features/admin/composables/useAdminDashboardStats'
import { useAdminDashboardTrends } from '@/features/admin/composables/useAdminDashboardTrends'
import KpiCard from '@/features/dashboard/components/KpiCard.vue'
import type { KpiColor, KpiIcon } from '@/features/dashboard/types'
import type { RouteLocationRaw } from 'vue-router'

const adminAuthStore = useAdminAuthStore()
const { stats, isLoading, error, fetchStats, retry } = useAdminDashboardStats()
const {
  trends,
  recentActivity,
  isTrendsLoading,
  isActivityLoading,
  trendsError,
  activityError,
  fetchTrends,
  fetchRecentActivity,
  retryTrends,
  retryActivity,
} = useAdminDashboardTrends()

onMounted(() => {
  fetchStats()
  fetchTrends()
  fetchRecentActivity()
})

function formatRelativeTime(timestamp: string): string {
  const diff = Date.now() - new Date(timestamp).getTime()
  if (diff < 0) return "à l'instant"
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return "à l'instant"
  if (minutes < 60) return `il y a ${minutes}min`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `il y a ${hours}h`
  const days = Math.floor(hours / 24)
  return `il y a ${days}j`
}

const activityIcon: Record<string, typeof Briefcase> = {
  mission: Briefcase,
  article: FileText,
  user: UserPlus,
}

interface AdminKpiConfig {
  key: string
  title: string
  color: KpiColor
  icon: KpiIcon
  to?: RouteLocationRaw
}

const userKpis: AdminKpiConfig[] = [
  { key: 'faces', title: 'Faces', color: 'primary', icon: 'users', to: { name: 'admin-faces-list' } },
  { key: 'producers', title: 'Producteurs', color: 'purple-500', icon: 'userCheck', to: { name: 'admin-producers-list' } },
  { key: 'admins', title: 'Admins', color: 'blue-500', icon: 'shield', to: { name: 'admin-admins-list' } },
]

const missionKpis: AdminKpiConfig[] = [
  { key: 'published', title: 'Publiées', color: 'green-500', icon: 'check', to: { name: 'admin-missions-list' } },
  { key: 'closed', title: 'Fermées', color: 'amber-500', icon: 'clock', to: { name: 'admin-missions-list' } },
  { key: 'completed', title: 'Terminées', color: 'primary', icon: 'checkCircle', to: { name: 'admin-missions-list' } },
  { key: 'draft', title: 'Brouillons', color: 'gray-500', icon: 'edit', to: { name: 'admin-missions-list' } },
]

const articleKpis: AdminKpiConfig[] = [
  { key: 'published_articles', title: 'Articles publiés', color: 'green-500', icon: 'file', to: { name: 'admin-articles-list' } },
  { key: 'draft_articles', title: 'Articles brouillons', color: 'amber-500', icon: 'edit', to: { name: 'admin-articles-list' } },
]

const candidatureKpis: AdminKpiConfig[] = [
  { key: 'total_candidatures', title: 'Candidatures totales', color: 'blue-500', icon: 'users' },
  { key: 'completed_candidatures', title: 'Complétées', color: 'primary', icon: 'checkCircle' },
]

const kpiValueGetters: Record<string, () => number> = {
  // Users
  faces: () => stats.value?.users.faces ?? 0,
  producers: () => stats.value?.users.producers ?? 0,
  admins: () => stats.value?.users.admins ?? 0,
  // Missions
  published: () => stats.value?.missions.published ?? 0,
  closed: () => stats.value?.missions.closed ?? 0,
  completed: () => stats.value?.missions.completed ?? 0,
  draft: () => stats.value?.missions.draft ?? 0,
  // Articles
  published_articles: () => stats.value?.articles.published ?? 0,
  draft_articles: () => stats.value?.articles.draft ?? 0,
  // Candidatures
  total_candidatures: () => stats.value?.candidatures.total ?? 0,
  completed_candidatures: () => stats.value?.candidatures.completed ?? 0,
}

function getKpiValue(key: string): number {
  return kpiValueGetters[key]?.() ?? 0
}

const quickActions = [
  { label: 'Créer un article', icon: FilePlus, to: { name: 'admin-articles-create' }, description: 'Rédiger un nouvel article' },
  { label: 'Voir les missions', icon: Briefcase, to: { name: 'admin-missions-list' }, description: 'Consulter toutes les missions' },
  { label: 'Gérer les Faces', icon: UserCheck, to: { name: 'admin-faces-list' }, description: 'Gérer les comptes Faces' },
  { label: 'Gérer les Producteurs', icon: Building, to: { name: 'admin-producers-list' }, description: 'Gérer les comptes Producteurs' },
]
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="mt-1 text-sm text-gray-500">
        Bienvenue, {{ adminAuthStore.adminName }}
      </p>
    </div>

    <!-- Error state -->
    <div
      v-if="error"
      class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-center justify-between"
      data-testid="stats-error"
      role="alert"
    >
      <div class="flex items-center gap-3">
        <AlertCircle class="w-5 h-5 text-red-500" aria-hidden="true" />
        <span class="text-sm text-red-700">{{ error }}</span>
      </div>
      <button
        @click="retry"
        :disabled="isLoading"
        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors disabled:opacity-50"
        data-testid="retry-button"
      >
        <RefreshCw :class="{ 'animate-spin': isLoading }" :size="16" />
        Réessayer
      </button>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        <!-- Panel 1: Utilisateurs -->
        <section aria-labelledby="users-section-title" data-testid="users-kpi-section" class="p-6 bg-white rounded-[24px] shadow-sm border border-teal-50">
          <h2 id="users-section-title" class="text-lg font-semibold text-slate-800 mb-6">
            Utilisateurs
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" data-testid="users-kpi-grid">
            <KpiCard
              v-for="kpi in userKpis"
              :key="kpi.key"
              :title="kpi.title"
              :value="getKpiValue(kpi.key)"
              :icon="kpi.icon"
              :color="kpi.color"
              :to="kpi.to"
              :is-loading="isLoading"
              :data-testid="'kpi-card-' + kpi.key"
            />
          </div>
        </section>

        <!-- Panel 2: Missions -->
        <section aria-labelledby="missions-section-title" data-testid="missions-kpi-section" class="p-6 bg-white rounded-[24px] shadow-sm border border-teal-50">
          <h2 id="missions-section-title" class="text-lg font-semibold text-slate-800 mb-6">
            Missions
          </h2>
          <div class="grid grid-cols-2 gap-4" data-testid="missions-kpi-grid">
            <KpiCard
              v-for="kpi in missionKpis"
              :key="kpi.key"
              :title="kpi.title"
              :value="getKpiValue(kpi.key)"
              :icon="kpi.icon"
              :color="kpi.color"
              :to="kpi.to"
              :is-loading="isLoading"
              :data-testid="'kpi-card-' + kpi.key"
            />
          </div>
        </section>

        <!-- Panel 3: Articles -->
        <section aria-labelledby="articles-section-title" data-testid="articles-kpi-section" class="p-6 bg-white rounded-[24px] shadow-sm border border-teal-50">
          <h2 id="articles-section-title" class="text-lg font-semibold text-slate-800 mb-6">
            Articles
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" data-testid="articles-kpi-grid">
            <KpiCard
              v-for="kpi in articleKpis"
              :key="kpi.key"
              :title="kpi.title"
              :value="getKpiValue(kpi.key)"
              :icon="kpi.icon"
              :color="kpi.color"
              :to="kpi.to"
              :is-loading="isLoading"
              :data-testid="'kpi-card-' + kpi.key"
            />
          </div>
        </section>

        <!-- Panel 4: Candidatures -->
        <section aria-labelledby="candidatures-section-title" data-testid="candidatures-kpi-section" class="p-6 bg-white rounded-[24px] shadow-sm border border-teal-50">
          <h2 id="candidatures-section-title" class="text-lg font-semibold text-slate-800 mb-6">
            Candidatures
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" data-testid="candidatures-kpi-grid">
            <KpiCard
              v-for="kpi in candidatureKpis"
              :key="kpi.key"
              :title="kpi.title"
              :value="getKpiValue(kpi.key)"
              :icon="kpi.icon"
              :color="kpi.color"
              :to="kpi.to"
              :is-loading="isLoading"
              :data-testid="'kpi-card-' + kpi.key"
            />
          </div>
        </section>
      </div>

      <!-- Trends Section -->
      <section aria-labelledby="trends-section-title" data-testid="trends-section" class="mt-10">
        <h2 id="trends-section-title" class="text-lg font-semibold text-slate-800 mb-6">
          <TrendingUp class="inline w-5 h-5 mr-2 text-primary-500" aria-hidden="true" />
          Tendances
        </h2>

        <!-- Trends error -->
        <div
          v-if="trendsError"
          class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-center justify-between mb-4"
          data-testid="trends-error"
          role="alert"
        >
          <div class="flex items-center gap-3">
            <AlertCircle class="w-5 h-5 text-red-500" aria-hidden="true" />
            <span class="text-sm text-red-700">{{ trendsError }}</span>
          </div>
          <button
            @click="retryTrends"
            :disabled="isTrendsLoading"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors disabled:opacity-50"
            data-testid="trends-retry-button"
          >
            <RefreshCw :class="{ 'animate-spin': isTrendsLoading }" :size="16" />
            Réessayer
          </button>
        </div>

        <!-- Trends loading skeleton -->
        <div v-else-if="isTrendsLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6" data-testid="trends-loading">
          <div v-for="i in 4" :key="i" class="p-5 bg-white rounded-[24px] shadow-sm border border-teal-50 animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-1/3 mb-4"></div>
            <div class="space-y-3">
              <div class="flex justify-between">
                <div class="h-3 bg-gray-200 rounded w-1/4"></div>
                <div class="h-3 bg-gray-200 rounded w-1/6"></div>
              </div>
              <div class="flex justify-between">
                <div class="h-3 bg-gray-200 rounded w-1/4"></div>
                <div class="h-3 bg-gray-200 rounded w-1/6"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Trends data -->
        <div v-else-if="trends" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Inscriptions -->
          <div class="p-5 bg-white rounded-[24px] shadow-sm border border-teal-50" data-testid="trends-registrations">
            <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
              <Users class="w-4 h-4 text-primary-500" aria-hidden="true" />
              Inscriptions
            </h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Faces</span>
                <div class="flex gap-3">
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">7j: {{ trends.registrations.faces_7d }}</span>
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">30j: {{ trends.registrations.faces_30d }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Producteurs</span>
                <div class="flex gap-3">
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">7j: {{ trends.registrations.producers_7d }}</span>
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">30j: {{ trends.registrations.producers_30d }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Missions -->
          <div class="p-5 bg-white rounded-[24px] shadow-sm border border-teal-50" data-testid="trends-missions">
            <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
              <Target class="w-4 h-4 text-green-500" aria-hidden="true" />
              Missions
            </h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Publiées</span>
                <div class="flex gap-3">
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">7j: {{ trends.missions.published_7d }}</span>
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">30j: {{ trends.missions.published_30d }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Terminées</span>
                <div class="flex gap-3">
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">7j: {{ trends.missions.completed_7d }}</span>
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">30j: {{ trends.missions.completed_30d }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Candidatures -->
          <div class="p-5 bg-white rounded-[24px] shadow-sm border border-teal-50" data-testid="trends-candidatures">
            <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
              <Activity class="w-4 h-4 text-blue-500" aria-hidden="true" />
              Candidatures
            </h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Soumises</span>
                <div class="flex gap-3">
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">7j: {{ trends.candidatures.submitted_7d }}</span>
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">30j: {{ trends.candidatures.submitted_30d }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Taux d'acceptation</span>
                <span class="text-xs font-medium text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full">30j: {{ trends.candidatures.acceptance_rate_30d }}%</span>
              </div>
            </div>
          </div>

          <!-- Santé plateforme -->
          <div class="p-5 bg-white rounded-[24px] shadow-sm border border-teal-50" data-testid="trends-health">
            <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
              <Activity class="w-4 h-4 text-amber-500" aria-hidden="true" />
              Santé plateforme
            </h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Missions sans candidature</span>
                <span
                  class="text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="trends.health.missions_without_candidatures_30d > 0 ? 'text-red-600 bg-red-50' : 'text-green-600 bg-green-50'"
                >
                  30j: {{ trends.health.missions_without_candidatures_30d }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Utilisateurs actifs</span>
                <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">7j: {{ trends.health.active_users_7d }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Quick Actions -->
      <section aria-labelledby="quick-actions-title" data-testid="quick-actions-section" class="mt-10">
        <h2 id="quick-actions-title" class="text-lg font-semibold text-slate-800 mb-6">
          Actions rapides
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <RouterLink
            v-for="action in quickActions"
            :key="action.label"
            :to="action.to"
            class="flex items-center gap-4 p-4 bg-white rounded-2xl shadow-sm border border-teal-50 hover:border-primary-200 hover:shadow-md transition-all group"
            :data-testid="'quick-action-' + action.label.toLowerCase().replace(/\s+/g, '-')"
          >
            <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition-colors">
              <component :is="action.icon" class="w-5 h-5 text-primary-600" />
            </div>
            <div>
              <p class="text-sm font-medium text-slate-800">{{ action.label }}</p>
              <p class="text-xs text-slate-500">{{ action.description }}</p>
            </div>
          </RouterLink>
        </div>
      </section>

      <!-- Recent Activity -->
      <section aria-labelledby="recent-activity-title" data-testid="recent-activity-section" class="mt-10">
        <h2 id="recent-activity-title" class="text-lg font-semibold text-slate-800 mb-6">
          <Clock class="inline w-5 h-5 mr-2 text-slate-400" aria-hidden="true" />
          Activité récente
        </h2>

        <!-- Activity error -->
        <div
          v-if="activityError"
          class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-center justify-between"
          data-testid="activity-error"
          role="alert"
        >
          <div class="flex items-center gap-3">
            <AlertCircle class="w-5 h-5 text-red-500" aria-hidden="true" />
            <span class="text-sm text-red-700">{{ activityError }}</span>
          </div>
          <button
            @click="retryActivity"
            :disabled="isActivityLoading"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors disabled:opacity-50"
            data-testid="activity-retry-button"
          >
            <RefreshCw :class="{ 'animate-spin': isActivityLoading }" :size="16" />
            Réessayer
          </button>
        </div>

        <!-- Activity loading skeleton -->
        <div v-else-if="isActivityLoading" class="space-y-3" data-testid="activity-loading">
          <div v-for="i in 3" :key="i" class="flex items-center gap-4 p-4 bg-white rounded-2xl shadow-sm border border-teal-50 animate-pulse">
            <div class="w-10 h-10 bg-gray-200 rounded-xl"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3 bg-gray-200 rounded w-1/3"></div>
              <div class="h-3 bg-gray-200 rounded w-1/2"></div>
            </div>
            <div class="h-3 bg-gray-200 rounded w-16"></div>
          </div>
        </div>

        <!-- Activity data -->
        <div v-else-if="recentActivity.length > 0" class="space-y-3">
          <RouterLink
            v-for="(event, index) in recentActivity"
            :key="index"
            :to="event.link || '#'"
            class="flex items-center gap-4 p-4 bg-white rounded-2xl shadow-sm border border-teal-50 hover:border-primary-200 hover:shadow-md transition-all group"
            :data-testid="'activity-item-' + index"
          >
            <div
              class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center"
              :class="{
                'bg-blue-50': event.type === 'mission',
                'bg-green-50': event.type === 'article',
                'bg-purple-50': event.type === 'user',
              }"
            >
              <component
                :is="activityIcon[event.type as keyof typeof activityIcon] || Activity"
                class="w-5 h-5"
                :class="{
                  'text-blue-500': event.type === 'mission',
                  'text-green-500': event.type === 'article',
                  'text-purple-500': event.type === 'user',
                }"
              />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">{{ event.title }}</p>
              <p class="text-xs text-slate-500">{{ event.description }}</p>
            </div>
            <span class="text-xs text-slate-400 whitespace-nowrap">{{ formatRelativeTime(event.timestamp) }}</span>
          </RouterLink>
        </div>

        <!-- Empty state -->
        <div v-else class="p-6 bg-white rounded-[24px] shadow-sm border border-teal-50 text-center" data-testid="activity-empty">
          <p class="text-sm text-slate-500">Aucune activité récente</p>
        </div>
      </section>
    </template>
  </div>
</template>

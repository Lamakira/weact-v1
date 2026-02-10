<script setup lang="ts">
/**
 * AdminDashboardPage
 * Admin dashboard with global platform KPIs.
 * (FR68) — Admin Dashboard KPIs
 */
import { onMounted } from 'vue'
import { RefreshCw, AlertCircle } from 'lucide-vue-next'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { useAdminDashboardStats } from '@/features/admin/composables/useAdminDashboardStats'
import KpiCard from '@/features/dashboard/components/KpiCard.vue'
import type { KpiColor, KpiIcon } from '@/features/dashboard/types'
import type { RouteLocationRaw } from 'vue-router'

const adminAuthStore = useAdminAuthStore()
const { stats, isLoading, error, fetchStats, retry } = useAdminDashboardStats()

onMounted(() => {
  fetchStats()
})

interface AdminKpiConfig {
  key: string
  title: string
  color: KpiColor
  icon: KpiIcon
  to?: RouteLocationRaw
}

// KPI card route mapping — add :to when admin CRUD pages are created
// Story 13-4: admin-faces-list, Story 13-5: admin-producers-list
// Story 13-8: admin-missions-list, Articles/Candidatures: TBD
const userKpis: AdminKpiConfig[] = [
  { key: 'faces', title: 'Faces', color: 'primary', icon: 'users' },
  { key: 'producers', title: 'Producteurs', color: 'purple-500', icon: 'userCheck' },
  { key: 'admins', title: 'Admins', color: 'blue-500', icon: 'shield', to: { name: 'admin-admins-list' } },
]

const missionKpis: AdminKpiConfig[] = [
  { key: 'published', title: 'Publiées', color: 'green-500', icon: 'check' },
  { key: 'closed', title: 'Fermées', color: 'amber-500', icon: 'clock' },
  { key: 'completed', title: 'Terminées', color: 'primary', icon: 'checkCircle' },
  { key: 'draft', title: 'Brouillons', color: 'gray-500', icon: 'edit' },
]

const articleKpis: AdminKpiConfig[] = [
  { key: 'published_articles', title: 'Articles publiés', color: 'green-500', icon: 'file' },
  { key: 'draft_articles', title: 'Articles brouillons', color: 'amber-500', icon: 'edit' },
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
    </template>
  </div>
</template>

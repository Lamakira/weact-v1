<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import {
  AlertCircle,
  CreditCard,
  Loader2,
  Search,
  X,
} from 'lucide-vue-next'
import {
  useAdminFaceSubscriptions,
  useAdminSubscriptionsList,
} from '@/features/admin/composables/useAdminFaceSubscriptions'
import type { AdminSubscriptionListItem } from '@/features/admin/services/adminFaceSubscriptionsApi'
import AdminSubscriptionCancelModal from '@/features/admin/components/AdminSubscriptionCancelModal.vue'
import AdminSubscriptionExtendModal from '@/features/admin/components/AdminSubscriptionExtendModal.vue'
import {
  formatAmount,
  formatSubscriptionDate,
  isCancellable,
  isExtendable,
  statusBadgeClass,
} from '@/features/admin/utils/subscriptionDisplay'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const {
  subscriptions,
  pagination,
  stats,
  isLoading,
  error,
  statsError,
  fetchSubscriptions,
  fetchStats,
} = useAdminSubscriptionsList()

// Mutations inline : mêmes endpoints/validations que la fiche Face (FP-1.4)
const { extend, cancel } = useAdminFaceSubscriptions()

// ---------- Filtres / pagination ----------

const searchQuery = ref('')
const planFilter = ref('')
const statusFilter = ref('')
const sortDirection = ref<'asc' | 'desc'>('asc')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const hasSubscriptions = computed(() => subscriptions.value.length > 0)
const totalPages = computed(() => pagination.value?.last_page ?? 1)
const currentPage = computed(() => pagination.value?.current_page ?? 1)

function buildParams(page: number = 1) {
  const params: Record<string, string | number> = { page }
  const search = searchQuery.value.trim()
  if (search) params.search = search
  if (planFilter.value) params.plan = planFilter.value
  if (statusFilter.value) params.status = statusFilter.value
  if (sortDirection.value === 'desc') params.sort = 'expires_at_desc'
  return params
}

function loadSubscriptions(page: number = 1) {
  fetchSubscriptions(buildParams(page))
}

// Toute action qui déclenche un fetch immédiat doit désamorcer le timer de
// recherche encore armé, sinon il refire loadSubscriptions(1) 300 ms plus
// tard (retour fantôme en page 1 + requête dupliquée).
function clearSearchDebounce(): void {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
    searchTimeout = null
  }
}

onMounted(() => {
  loadSubscriptions()
  fetchStats()
})

onUnmounted(() => {
  clearSearchDebounce()
})

// Levé par clearFilters : la remise à zéro des 3 refs déclencherait sinon
// jusqu'à 3 requêtes (watch search débouncé + watch filtres + appel direct)
// sur des endpoints throttlés à 30/min.
let suppressFilterWatchers = false

watch(searchQuery, () => {
  if (suppressFilterWatchers) return
  clearSearchDebounce()
  searchTimeout = setTimeout(() => {
    loadSubscriptions(1)
  }, 300)
})

watch([planFilter, statusFilter], () => {
  if (suppressFilterWatchers) return
  clearSearchDebounce()
  loadSubscriptions(1)
})

function toggleSort(): void {
  clearSearchDebounce()
  sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  loadSubscriptions(1)
}

function goToPage(page: number): void {
  clearSearchDebounce()
  loadSubscriptions(page)
}

function clearFilters(): void {
  clearSearchDebounce()
  suppressFilterWatchers = true
  searchQuery.value = ''
  planFilter.value = ''
  statusFilter.value = ''
  void nextTick(() => {
    suppressFilterWatchers = false
  })
  loadSubscriptions(1)
}

const hasActiveFilters = computed(
  () => searchQuery.value || planFilter.value || statusFilter.value,
)

// ---------- Helpers ----------

const formatDate = formatSubscriptionDate

function faceDisplayName(sub: AdminSubscriptionListItem): string {
  const parts = [sub.face?.prenom, sub.face?.nom].filter(Boolean)
  return parts.length > 0 ? parts.join(' ') : (sub.face?.username ?? '—')
}

async function refreshAfterMutation(): Promise<void> {
  const pageBefore = currentPage.value
  await fetchSubscriptions(buildParams(pageBefore))
  // Clamp : la mutation peut avoir vidé la page courante d'une liste filtrée
  // (ex. annulation de la dernière « active » de la page) — retomber sur la
  // dernière page réelle plutôt que d'afficher un faux état vide sans
  // contrôles de pagination.
  const meta = pagination.value
  if (meta && meta.total > 0 && subscriptions.value.length === 0 && pageBefore > meta.last_page) {
    await fetchSubscriptions(buildParams(meta.last_page))
  }
  await fetchStats()
}

// ---------- Modales Prolonger / Annuler (composants partagés avec la fiche Face) ----------

const extendTarget = ref<AdminSubscriptionListItem | null>(null)
const cancelTarget = ref<AdminSubscriptionListItem | null>(null)

function openExtend(sub: AdminSubscriptionListItem): void {
  extendTarget.value = sub
}

function openCancel(sub: AdminSubscriptionListItem): void {
  cancelTarget.value = sub
}

async function onExtendSuccess(message: string | null): Promise<void> {
  extendTarget.value = null
  toast.success(message ?? 'Abonnement étendu')
  await refreshAfterMutation()
}

async function onCancelSuccess(message: string | null): Promise<void> {
  cancelTarget.value = null
  toast.success(message ?? 'Abonnement annulé')
  await refreshAfterMutation()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Abonnements</h1>
      <p class="mt-1 text-sm text-gray-500">
        Vue transversale des abonnements Face de la plateforme
      </p>
    </div>

    <!-- KPI Cards -->
    <div
      v-if="statsError"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="stats-error"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ statsError }}</p>
    </div>
    <div
      v-else-if="stats"
      class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
      data-testid="kpi-cards"
    >
      <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="kpi-active">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Actives</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.active_by_plan.total }}</p>
        <p class="mt-1 text-xs text-gray-500">
          Starter {{ stats.active_by_plan.starter }} · Pro {{ stats.active_by_plan.pro }} · Élite
          {{ stats.active_by_plan.elite }}
        </p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="kpi-revenue-month">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Revenus du mois</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">
          {{ formatAmount(stats.revenue.current_month, stats.revenue.currency) }}
        </p>
        <p class="mt-1 text-xs text-gray-500">Mois calendaire en cours</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="kpi-revenue-total">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Revenus cumulés</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">
          {{ formatAmount(stats.revenue.total, stats.revenue.currency) }}
        </p>
        <p class="mt-1 text-xs text-gray-500">Encaissements totaux</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="kpi-expiring">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Expirent sous 30 j</p>
        <p class="mt-1 text-2xl font-bold text-amber-600">{{ stats.expiring_within_30_days }}</p>
        <p class="mt-1 text-xs text-gray-500">Abonnements actifs</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="kpi-pending">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">En attente</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.pending_payment_count }}</p>
        <p class="mt-1 text-xs text-gray-500">Paiements en attente</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="kpi-failed">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Échoués</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{{ stats.failed_count }}</p>
        <p class="mt-1 text-xs text-gray-500">Paiements échoués</p>
      </div>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row gap-3" data-testid="filters-bar">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher par nom, prénom ou username de la Face..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          data-testid="search-input"
        />
      </div>

      <select
        v-model="planFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="plan-filter"
      >
        <option value="">Tous paliers</option>
        <option value="starter">Starter</option>
        <option value="pro">Pro</option>
        <option value="elite">Élite</option>
      </select>

      <select
        v-model="statusFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="status-filter"
      >
        <option value="">Tous statuts</option>
        <option value="active">Active</option>
        <option value="pending_payment">En attente de paiement</option>
        <option value="expired">Expirée</option>
        <option value="cancelled">Annulée</option>
        <option value="failed">Échouée</option>
      </select>

      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="inline-flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
        data-testid="clear-filters"
      >
        <X class="h-4 w-4" />
        Effacer
      </button>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="error-message"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex items-center justify-center py-12"
      data-testid="loading-state"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!hasSubscriptions && !error"
      class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
      data-testid="empty-state"
    >
      <CreditCard class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucun abonnement trouvé</h3>
      <p class="mt-2 text-sm text-gray-500">
        {{ hasActiveFilters ? 'Aucun résultat pour ces critères de recherche.' : 'Aucun abonnement sur la plateforme.' }}
      </p>
    </div>

    <!-- Subscriptions Table -->
    <div
      v-else-if="hasSubscriptions"
      class="overflow-x-auto rounded-xl border border-gray-200 bg-white"
      data-testid="subscriptions-table"
    >
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Face
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Palier
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Statut
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Montant
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              <button
                type="button"
                class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700"
                data-testid="sort-expires"
                @click="toggleSort"
              >
                Expire le
                <span aria-hidden="true">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
              </button>
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="sub in subscriptions"
            :key="sub.id"
            class="hover:bg-gray-50 transition-colors"
            data-testid="subscription-row"
          >
            <td class="whitespace-nowrap px-6 py-4">
              <router-link
                :to="{ name: 'admin-face-detail', params: { id: sub.face.id } }"
                class="group"
                data-testid="face-link"
              >
                <p class="text-sm font-medium text-gray-900 group-hover:text-primary-600">
                  {{ faceDisplayName(sub) }}
                </p>
                <p class="text-xs text-gray-500">@{{ sub.face.username }}</p>
              </router-link>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
              {{ sub.plan_label ?? '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="statusBadgeClass(sub.status)"
                data-testid="status-badge"
              >
                {{ sub.status_label ?? '—' }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
              {{ formatAmount(sub.paid_amount, sub.currency) }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatDate(sub.expires_at) }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right">
              <div class="inline-flex items-center gap-2">
                <button
                  v-if="isExtendable(sub)"
                  type="button"
                  class="rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 transition hover:bg-green-100"
                  data-testid="extend-button"
                  @click="openExtend(sub)"
                >
                  Prolonger
                </button>
                <button
                  v-if="isCancellable(sub)"
                  type="button"
                  class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100"
                  data-testid="cancel-button"
                  @click="openCancel(sub)"
                >
                  Annuler
                </button>
                <span v-if="!isExtendable(sub) && !isCancellable(sub)" class="text-xs text-gray-400">
                  —
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3"
        data-testid="pagination"
      >
        <p class="text-sm text-gray-700">
          Page {{ currentPage }} sur {{ totalPages }}
          <span class="text-gray-500">({{ pagination?.total }} résultats)</span>
        </p>
        <div class="flex gap-2">
          <button
            :disabled="currentPage <= 1"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="pagination-prev"
            @click="goToPage(currentPage - 1)"
          >
            Précédent
          </button>
          <button
            :disabled="currentPage >= totalPages"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="pagination-next"
            @click="goToPage(currentPage + 1)"
          >
            Suivant
          </button>
        </div>
      </div>
    </div>

    <!-- Modales Prolonger / Annuler (partagées avec AdminFaceSubscriptionSection) -->
    <AdminSubscriptionExtendModal
      :target="extendTarget"
      :submit-action="extend"
      @close="extendTarget = null"
      @success="onExtendSuccess"
      @conflict="refreshAfterMutation"
    />
    <AdminSubscriptionCancelModal
      :target="cancelTarget"
      :submit-action="cancel"
      @close="cancelTarget = null"
      @success="onCancelSuccess"
      @conflict="refreshAfterMutation"
    />
  </div>
</template>

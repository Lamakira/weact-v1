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
  type AdminSubscriptionActionResult,
} from '@/features/admin/composables/useAdminFaceSubscriptions'
import type {
  AdminSubscriptionListItem,
  AdminSubscriptionStatus,
} from '@/features/admin/services/adminFaceSubscriptionsApi'
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
  if (searchQuery.value) params.search = searchQuery.value
  if (planFilter.value) params.plan = planFilter.value
  if (statusFilter.value) params.status = statusFilter.value
  if (sortDirection.value === 'desc') params.sort = 'expires_at_desc'
  return params
}

function loadSubscriptions(page: number = 1) {
  fetchSubscriptions(buildParams(page))
}

onMounted(() => {
  loadSubscriptions()
  fetchStats()
})

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

// Levé par clearFilters : la remise à zéro des 3 refs déclencherait sinon
// jusqu'à 3 requêtes (watch search débouncé + watch filtres + appel direct)
// sur des endpoints throttlés à 30/min.
let suppressFilterWatchers = false

watch(searchQuery, () => {
  if (suppressFilterWatchers) return
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadSubscriptions(1)
  }, 300)
})

watch([planFilter, statusFilter], () => {
  if (suppressFilterWatchers) return
  loadSubscriptions(1)
})

function toggleSort(): void {
  sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  loadSubscriptions(1)
}

function goToPage(page: number): void {
  loadSubscriptions(page)
}

function clearFilters(): void {
  if (searchTimeout) clearTimeout(searchTimeout)
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

const STATUS_BADGE_CLASS: Record<AdminSubscriptionStatus, string> = {
  active: 'bg-green-100 text-green-700',
  pending_payment: 'bg-amber-100 text-amber-700',
  expired: 'bg-red-100 text-red-700',
  cancelled: 'bg-gray-100 text-gray-600',
  failed: 'bg-red-100 text-red-700',
}

function statusBadgeClass(status: AdminSubscriptionStatus | null): string {
  if (!status) return 'bg-gray-100 text-gray-600'
  return STATUS_BADGE_CLASS[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function formatAmount(amount: number | null | undefined, currency: string = 'XOF'): string {
  if (amount === null || amount === undefined || !Number.isFinite(amount)) return '—'
  try {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount)
  } catch {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} ${currency || 'XOF'}`
  }
}

function faceDisplayName(sub: AdminSubscriptionListItem): string {
  const parts = [sub.face?.prenom, sub.face?.nom].filter(Boolean)
  return parts.length > 0 ? parts.join(' ') : (sub.face?.username ?? '—')
}

function isExtendable(sub: AdminSubscriptionListItem): boolean {
  return (
    sub.status === 'active' &&
    sub.expires_at !== null &&
    new Date(sub.expires_at).getTime() > Date.now()
  )
}

function isCancellable(sub: AdminSubscriptionListItem): boolean {
  return sub.status === 'active' || sub.status === 'pending_payment'
}

// ---------- Focus management (pattern AdminFaceSubscriptionSection) ----------

const lastFocusedElement = ref<HTMLElement | null>(null)
const extendModalRef = ref<HTMLDivElement | null>(null)
const cancelModalRef = ref<HTMLDivElement | null>(null)

async function prepareModalFocus(modalRef: typeof extendModalRef): Promise<void> {
  lastFocusedElement.value =
    document.activeElement instanceof HTMLElement ? document.activeElement : null
  await nextTick()
  modalRef.value?.focus()
}

function restoreFocus(): void {
  if (lastFocusedElement.value && document.contains(lastFocusedElement.value)) {
    lastFocusedElement.value.focus({ preventScroll: true })
  }
  lastFocusedElement.value = null
}

function trapModalFocus(event: KeyboardEvent, root: HTMLDivElement | null): void {
  if (!root) return

  const focusable = Array.from(
    root.querySelectorAll<HTMLElement>(
      'button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => element.offsetParent !== null)

  if (focusable.length === 0) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (!first || !last) return

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
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

// ---------- Modale Prolonger ----------

const extendTarget = ref<AdminSubscriptionListItem | null>(null)
const extendNotes = ref('')
const extendAdditionalDays = ref<string>('')
const extendSubmitting = ref(false)
const extendConflictMessage = ref<string | null>(null)
const extendErrors = ref<Record<string, string[]>>({})

async function openExtend(sub: AdminSubscriptionListItem): Promise<void> {
  extendTarget.value = sub
  extendNotes.value = ''
  extendAdditionalDays.value = ''
  extendConflictMessage.value = null
  extendErrors.value = {}
  await prepareModalFocus(extendModalRef)
}

function closeExtend(): void {
  if (extendSubmitting.value) return
  extendTarget.value = null
  restoreFocus()
}

async function submitExtend(): Promise<void> {
  if (extendSubmitting.value) return
  if (!extendTarget.value) return
  extendSubmitting.value = true
  extendConflictMessage.value = null
  extendErrors.value = {}

  const parsedAdditionalDays = Number(extendAdditionalDays.value)
  if (
    !Number.isInteger(parsedAdditionalDays) ||
    parsedAdditionalDays < 1 ||
    parsedAdditionalDays > 3650
  ) {
    extendErrors.value = {
      additional_days: ['Saisissez un nombre entier entre 1 et 3650.'],
    }
    extendSubmitting.value = false
    return
  }

  const payload = {
    notes: extendNotes.value.trim(),
    additional_days: parsedAdditionalDays,
  }

  let result: AdminSubscriptionActionResult
  try {
    result = await extend(extendTarget.value.id, payload)
  } catch {
    extendSubmitting.value = false
    return
  }

  if (result.success) {
    extendSubmitting.value = false
    // closeExtend (et pas target=null direct) : restaure le focus sur le
    // bouton d'origine — parité avec la fermeture manuelle.
    closeExtend()
    toast.success(result.message ?? 'Abonnement étendu')
    await refreshAfterMutation()
    return
  }

  extendSubmitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    extendErrors.value = result.errors ?? {}
    extendConflictMessage.value = result.message ?? null
  } else {
    extendErrors.value = {}
    extendConflictMessage.value = result.message ?? 'Une erreur est survenue'
    // Liste ET stats : un 409 signifie que l'état a bougé ailleurs — les
    // cartes KPI doivent suivre, pas seulement le tableau.
    await refreshAfterMutation()
  }
}

// ---------- Modale Annuler ----------

const cancelTarget = ref<AdminSubscriptionListItem | null>(null)
const cancelNotes = ref('')
const cancelSubmitting = ref(false)
const cancelConflictMessage = ref<string | null>(null)
const cancelErrors = ref<Record<string, string[]>>({})

async function openCancel(sub: AdminSubscriptionListItem): Promise<void> {
  cancelTarget.value = sub
  cancelNotes.value = ''
  cancelConflictMessage.value = null
  cancelErrors.value = {}
  await prepareModalFocus(cancelModalRef)
}

function closeCancel(): void {
  if (cancelSubmitting.value) return
  cancelTarget.value = null
  restoreFocus()
}

async function submitCancel(): Promise<void> {
  if (cancelSubmitting.value) return
  if (!cancelTarget.value) return
  cancelSubmitting.value = true
  cancelConflictMessage.value = null
  cancelErrors.value = {}

  const payload = { notes: cancelNotes.value.trim() }
  let result: AdminSubscriptionActionResult
  try {
    result = await cancel(cancelTarget.value.id, payload)
  } catch {
    cancelSubmitting.value = false
    return
  }

  if (result.success) {
    cancelSubmitting.value = false
    closeCancel()
    toast.success(result.message ?? 'Abonnement annulé')
    await refreshAfterMutation()
    return
  }

  cancelSubmitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    cancelErrors.value = result.errors ?? {}
    cancelConflictMessage.value = result.message ?? null
  } else {
    cancelErrors.value = {}
    cancelConflictMessage.value = result.message ?? 'Une erreur est survenue'
    await refreshAfterMutation()
  }
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

    <!-- ============ Modale Prolonger ============ -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="extendTarget"
          ref="extendModalRef"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          data-testid="subscriptions-extend-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="subscriptions-extend-title"
          tabindex="-1"
          @click.self="closeExtend"
          @keydown.esc="closeExtend"
          @keydown.tab="trapModalFocus($event, extendModalRef)"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
              <div>
                <h3 id="subscriptions-extend-title" class="text-lg font-semibold text-gray-900">
                  Prolonger l'abonnement
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Ajoutera la durée à l'expiration actuelle ({{ formatDate(extendTarget.expires_at) }}).
                </p>
              </div>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600"
                aria-label="Fermer"
                @click="closeExtend"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div
                v-if="extendConflictMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                data-testid="subscriptions-extend-error"
              >
                {{ extendConflictMessage }}
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="subscriptions-extend-notes">
                  Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="subscriptions-extend-notes"
                  v-model="extendNotes"
                  rows="3"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Raison de la prolongation (5 à 1000 caractères)..."
                  data-testid="extend-notes"
                />
                <p
                  v-if="extendErrors.notes?.length"
                  class="text-xs text-red-600"
                  data-testid="subscriptions-extend-field-error-notes"
                >
                  {{ extendErrors.notes[0] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="subscriptions-extend-additional-days">
                  Jours supplémentaires <span class="text-red-500">*</span>
                </label>
                <input
                  id="subscriptions-extend-additional-days"
                  v-model="extendAdditionalDays"
                  type="number"
                  min="1"
                  max="3650"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-testid="extend-additional-days"
                />
                <p class="text-xs text-gray-500">
                  Nombre de jours à ajouter à la date d'expiration actuelle.
                </p>
                <p
                  v-if="extendErrors.additional_days?.length"
                  class="text-xs text-red-600"
                  data-testid="subscriptions-extend-field-error-additional_days"
                >
                  {{ extendErrors.additional_days[0] }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeExtend"
              >
                Annuler
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700 disabled:opacity-50"
                :disabled="extendSubmitting"
                data-testid="extend-submit"
                @click="submitExtend"
              >
                <Loader2 v-if="extendSubmitting" class="h-4 w-4 animate-spin" />
                Prolonger
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============ Modale Annuler ============ -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="cancelTarget"
          ref="cancelModalRef"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          data-testid="subscriptions-cancel-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="subscriptions-cancel-title"
          tabindex="-1"
          @click.self="closeCancel"
          @keydown.esc="closeCancel"
          @keydown.tab="trapModalFocus($event, cancelModalRef)"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
              <div>
                <h3 id="subscriptions-cancel-title" class="text-lg font-semibold text-gray-900">
                  Annuler l'abonnement
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Cette action est immédiate. L'utilisateur ne pourra plus accéder aux fonctionnalités
                  Premium, mais ses photos 3-4 et sa vidéo de casting ne seront PAS supprimées (elles
                  deviendront simplement masquées publiquement).
                </p>
              </div>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600"
                aria-label="Fermer"
                @click="closeCancel"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div
                v-if="cancelConflictMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                data-testid="subscriptions-cancel-error"
              >
                {{ cancelConflictMessage }}
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="subscriptions-cancel-notes">
                  Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="subscriptions-cancel-notes"
                  v-model="cancelNotes"
                  rows="3"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Raison de l'annulation (5 à 1000 caractères)..."
                  data-testid="cancel-notes"
                />
                <p
                  v-if="cancelErrors.notes?.length"
                  class="text-xs text-red-600"
                  data-testid="subscriptions-cancel-field-error-notes"
                >
                  {{ cancelErrors.notes[0] }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeCancel"
              >
                Retour
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
                :disabled="cancelSubmitting"
                data-testid="cancel-submit"
                @click="submitCancel"
              >
                <Loader2 v-if="cancelSubmitting" class="h-4 w-4 animate-spin" />
                Confirmer l'annulation
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

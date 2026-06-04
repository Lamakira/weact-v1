<script setup lang="ts">
/**
 * AdminFaceContactsPage — "Faces à contacter"
 * Unified, read-only view of the Faces actively engaged on a booking or a
 * mission, with their (admin-only) WhatsApp number, so the team can reach out
 * manually (wa.me) and push in-flight deals through.
 */
import { onMounted, onUnmounted, computed, ref, watch } from 'vue'
import { Search, MessageCircle, AlertCircle, Loader2, X, PhoneOff } from 'lucide-vue-next'
import { useAdminEngagements } from '@/features/admin/composables/useAdminEngagements'
import type {
  AdminEngagementData,
  AdminEngagementListParams,
} from '@/features/admin/services/adminEngagementsApi'

const { engagements, pagination, isLoading, error, fetchEngagements } = useAdminEngagements()

const searchQuery = ref('')
const statusFilter = ref('')
const typeFilter = ref<'' | 'booking' | 'mission'>('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const STATUS_OPTIONS = [
  { value: 'pending', label: 'En attente' },
  { value: 'accepted', label: 'Acceptée' },
  { value: 'paid', label: 'Payée' },
  { value: 'in_progress', label: 'En cours' },
  { value: 'confirmed', label: 'Confirmée (mission)' },
  { value: 'confirmed_by_face', label: 'Confirmée par la Face' },
  { value: 'confirmed_by_producer', label: 'Confirmée par le Producteur' },
]

const hasEngagements = computed(() => engagements.value.length > 0)
const totalPages = computed(() => pagination.value?.last_page ?? 1)
const currentPage = computed(() => pagination.value?.current_page ?? 1)
const hasActiveFilters = computed(
  () => Boolean(searchQuery.value) || Boolean(statusFilter.value) || Boolean(typeFilter.value),
)

function buildParams(page = 1): AdminEngagementListParams {
  const params: AdminEngagementListParams = { page }
  if (searchQuery.value) params.search = searchQuery.value
  if (statusFilter.value) params.status = statusFilter.value
  if (typeFilter.value) params.type = typeFilter.value
  return params
}

function loadEngagements(page = 1): void {
  fetchEngagements(buildParams(page))
}

onMounted(() => {
  loadEngagements()
})

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadEngagements(1)
  }, 300)
})

watch([statusFilter, typeFilter], () => {
  loadEngagements(1)
})

function goToPage(page: number): void {
  loadEngagements(page)
}

function clearFilters(): void {
  searchQuery.value = ''
  statusFilter.value = ''
  typeFilter.value = ''
  loadEngagements(1)
}

function getStatusClass(status: string): string {
  switch (status) {
    case 'pending':
      return 'bg-gray-100 text-gray-800'
    case 'accepted':
      return 'bg-amber-100 text-amber-800'
    case 'paid':
      return 'bg-green-100 text-green-800'
    case 'in_progress':
      return 'bg-blue-100 text-blue-800'
    case 'confirmed':
    case 'confirmed_by_face':
    case 'confirmed_by_producer':
      return 'bg-indigo-100 text-indigo-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

function typeLabel(type: AdminEngagementData['type']): string {
  return type === 'mission' ? 'Mission' : 'Booking'
}

function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatCurrency(amount: number | null): string {
  if (amount == null) return '—'
  return (
    new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    })
      .format(amount)
      .replace('XOF', '')
      .trim() + ' XOF'
  )
}

function formatSince(dateString: string | null): string {
  if (!dateString) return '—'
  const diffMs = Date.now() - new Date(dateString).getTime()
  if (Number.isNaN(diffMs) || diffMs < 0) return '—'
  const days = Math.floor(diffMs / 86_400_000)
  if (days >= 1) return `il y a ${days} j`
  const hours = Math.floor(diffMs / 3_600_000)
  if (hours >= 1) return `il y a ${hours} h`
  const mins = Math.max(1, Math.floor(diffMs / 60_000))
  return `il y a ${mins} min`
}

function buildMessage(row: AdminEngagementData): string {
  const displayName = row.face.display_name.trim()
  const prenom = displayName && displayName !== '—' ? displayName.split(/\s+/)[0] : ''
  const greeting = prenom ? `Bonjour ${prenom},` : 'Bonjour,'
  const objet = row.type === 'mission' ? 'mission' : 'booking'
  return `${greeting} l'équipe WEACT vous contacte au sujet de votre ${objet} sur la plateforme.`
}

function whatsappDigits(row: AdminEngagementData): string {
  return (row.face.whatsapp_number ?? '').replace(/\D/g, '')
}

function hasDialableWhatsapp(row: AdminEngagementData): boolean {
  return row.face.has_whatsapp && whatsappDigits(row).length > 0
}

function waLink(row: AdminEngagementData): string {
  const digits = whatsappDigits(row)
  return `https://wa.me/${digits}?text=${encodeURIComponent(buildMessage(row))}`
}
</script>

<template>
  <div class="space-y-6" data-testid="engagements-page">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Faces à contacter</h1>
      <p class="mt-1 text-sm text-gray-500">
        Suivez les Faces engagées sur une mission ou un booking actif et contactez-les sur WhatsApp.
      </p>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row gap-3">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher par Face, numéro ou producteur..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          data-testid="engagement-search"
        />
      </div>

      <!-- Type filter -->
      <select
        v-model="typeFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="engagement-type-filter"
      >
        <option value="">Tous les types</option>
        <option value="mission">Mission</option>
        <option value="booking">Booking</option>
      </select>

      <!-- Status filter -->
      <select
        v-model="statusFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="engagement-status-filter"
      >
        <option value="">Tous les statuts</option>
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>

      <!-- Clear filters -->
      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="inline-flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
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
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!hasEngagements && !error"
      class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
    >
      <MessageCircle class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucune Face à contacter</h3>
      <p class="mt-2 text-sm text-gray-500">
        {{
          hasActiveFilters
            ? 'Aucun résultat pour ces critères.'
            : 'Aucun engagement actif pour le moment.'
        }}
      </p>
    </div>

    <!-- Engagements Table -->
    <div
      v-else-if="hasEngagements"
      class="overflow-x-auto rounded-xl border border-gray-200 bg-white"
    >
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Face</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">WhatsApp</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Objet</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Producteur</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Montant Face</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">État</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Depuis</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="row in engagements"
            :key="row.id"
            class="hover:bg-gray-50 transition-colors"
            data-testid="engagement-row"
            :data-type="row.type"
          >
            <!-- Face -->
            <td class="whitespace-nowrap px-6 py-4">
              <RouterLink
                v-if="row.face.id"
                :to="{ name: 'admin-face-detail', params: { id: row.face.id } }"
                class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline"
              >
                {{ row.face.display_name }}
              </RouterLink>
              <span v-else class="text-sm font-medium text-gray-900">{{ row.face.display_name }}</span>
            </td>

            <!-- WhatsApp -->
            <td class="whitespace-nowrap px-6 py-4">
              <a
                v-if="hasDialableWhatsapp(row)"
                :href="waLink(row)"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 rounded-lg bg-green-50 px-2.5 py-1 text-sm font-medium text-green-700 hover:bg-green-100 transition-colors"
                data-testid="whatsapp-link"
              >
                <MessageCircle class="h-4 w-4" />
                {{ row.face.whatsapp_number }}
              </a>
              <span
                v-else
                class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500"
                data-testid="whatsapp-missing"
              >
                <PhoneOff class="h-3.5 w-3.5" />
                Numéro manquant
              </span>
            </td>

            <!-- Type -->
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="row.type === 'mission' ? 'bg-purple-100 text-purple-800' : 'bg-teal-100 text-teal-800'"
              >
                {{ typeLabel(row.type) }}
              </span>
            </td>

            <!-- Objet -->
            <td class="px-6 py-4">
              <RouterLink
                v-if="row.type === 'mission' && row.objet.detail_id"
                :to="{ name: 'admin-mission-detail', params: { id: row.objet.detail_id } }"
                class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline max-w-[200px] truncate block"
              >
                {{ row.objet.label ?? '—' }}
              </RouterLink>
              <p v-else class="text-sm font-medium text-gray-900 max-w-[200px] truncate">
                {{ row.objet.label ?? '—' }}
              </p>
              <p class="text-xs text-gray-500">{{ formatDate(row.objet.date) }}</p>
            </td>

            <!-- Producteur -->
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ row.producer.display_name }}
            </td>

            <!-- Montant -->
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
              {{ formatCurrency(row.montant_face_recoit) }}
            </td>

            <!-- État -->
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getStatusClass(row.status)"
              >
                {{ row.status_label }}
              </span>
            </td>

            <!-- Depuis -->
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatSince(row.engaged_since) }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3"
      >
        <p class="text-sm text-gray-700">
          Page {{ currentPage }} sur {{ totalPages }}
          <span class="text-gray-500">({{ pagination?.total }} résultats)</span>
        </p>
        <div class="flex gap-2">
          <button
            :disabled="currentPage <= 1"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            @click="goToPage(currentPage - 1)"
          >
            Précédent
          </button>
          <button
            :disabled="currentPage >= totalPages"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            @click="goToPage(currentPage + 1)"
          >
            Suivant
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

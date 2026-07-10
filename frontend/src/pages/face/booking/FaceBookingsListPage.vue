<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Loader2,
  AlertCircle,
  CalendarCheck,
  ChevronLeft,
  ChevronRight,
} from 'lucide-vue-next'
import { useBookingsList } from '@/features/booking/composables'
import { BookingCard, BookingStatusFilter } from '@/features/booking/components'
import type { BookingFilterStatus } from '@/features/booking/types'
import { BookingFilterLabel } from '@/features/booking/types'

// Explicit name (devtools). Caching is driven by the route's meta.keepAlive flag.
defineOptions({ name: 'FaceBookingsListPage' })

const route = useRoute()
const router = useRouter()

const {
  bookings,
  isLoading,
  error,
  currentPage,
  lastPage,
  total,
  hasNextPage,
  hasPrevPage,
  isEmpty,
  statusFilter,
  fetchBookings,
  nextPage,
  prevPage,
  goToPage,
  setStatusFilter,
} = useBookingsList()

// Guard to prevent double-fetch when we programmatically update the URL
let skipNextWatch = false

/**
 * Sync filter with URL query params
 */
function syncFromUrl(): void {
  const urlStatus = route.query.status as BookingFilterStatus | undefined
  const urlPage = parseInt(route.query.page as string, 10) || 1

  if (urlStatus !== undefined && urlStatus !== statusFilter.value) {
    statusFilter.value = urlStatus
  }

  fetchBookings(urlPage)
}

/**
 * Update URL when filter or page changes (skip watcher to avoid double-fetch)
 */
function updateUrl(): void {
  const query: Record<string, string> = {}
  if (statusFilter.value) {
    query.status = statusFilter.value
  }
  if (currentPage.value > 1) {
    query.page = String(currentPage.value)
  }
  // Replacing with an identical query is a "duplicated navigation": vue-router
  // aborts it and the query watcher never ticks, so an armed skipNextWatch
  // would survive in this keep-alive-cached instance and swallow the next
  // return's refresh. Only arm the flag when a navigation will actually happen.
  const current = route.query
  const unchanged =
    Object.keys(current).length === Object.keys(query).length &&
    Object.entries(query).every(([key, value]) => current[key] === value)
  if (unchanged) return
  skipNextWatch = true
  router.replace({ query })
}

async function handleFilterChange(status: BookingFilterStatus): Promise<void> {
  await setStatusFilter(status)
  updateUrl()
}

async function handleNextPage(): Promise<void> {
  await nextPage()
  updateUrl()
}

async function handlePrevPage(): Promise<void> {
  await prevPage()
  updateUrl()
}

async function handleGoToPage(page: number): Promise<void> {
  await goToPage(page)
  updateUrl()
}

function getPageNumbers(): number[] {
  const pages: number[] = []
  const maxVisiblePages = 5
  let startPage = Math.max(1, currentPage.value - Math.floor(maxVisiblePages / 2))
  const endPage = Math.min(lastPage.value, startPage + maxVisiblePages - 1)

  if (endPage - startPage + 1 < maxVisiblePages) {
    startPage = Math.max(1, endPage - maxVisiblePages + 1)
  }

  for (let i = startPage; i <= endPage; i++) {
    pages.push(i)
  }
  return pages
}

function goToProfile(): void {
  router.push({ name: 'face-profile' })
}

onMounted(() => {
  syncFromUrl()
})

watch(
  () => route.query,
  () => {
    // Cached by keep-alive: this watcher keeps firing while the page is
    // off-screen. Act only when actually on this route, so navigating to a
    // booking detail (which drops the query) can't corrupt state; it fires once
    // on return, refreshing the list (booking statuses change).
    if (route.name !== 'face-bookings') return
    if (skipNextWatch) {
      skipNextWatch = false
      return
    }
    syncFromUrl()
  },
)
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-800">
        Mes bookings
      </h1>
      <p class="mt-1 text-slate-500">
        Suivez vos demandes de booking et leur évolution
      </p>
    </div>

    <div>
      <!-- Status Filter -->
      <div class="mb-6">
        <BookingStatusFilter
          :model-value="statusFilter"
          @update:model-value="handleFilterChange"
        />
      </div>

      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex flex-col items-center justify-center py-24"
      >
        <Loader2 class="h-12 w-12 animate-spin text-primary" />
        <p class="mt-4 text-muted-foreground">Chargement de vos bookings...</p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-24 text-center"
      >
        <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
          <AlertCircle class="h-10 w-10" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          {{ error }}
        </p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
          @click="fetchBookings(1)"
        >
          Réessayer
        </button>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="isEmpty"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
      >
        <div class="mb-4 rounded-full bg-muted p-4">
          <CalendarCheck class="h-10 w-10 text-muted-foreground" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Pas encore de booking</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          <template v-if="statusFilter">
            Aucun booking avec le statut "{{ BookingFilterLabel[statusFilter] }}" trouvé.
          </template>
          <template v-else>
            Vous n'avez pas encore reçu de demande de booking.<br />
            Complétez votre profil pour attirer les producteurs !
          </template>
        </p>
        <button
          v-if="!statusFilter"
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
          @click="goToProfile"
        >
          Compléter mon profil
        </button>
      </div>

      <!-- Bookings List -->
      <template v-else>
        <!-- Results count -->
        <p class="mb-4 text-sm text-muted-foreground">
          {{ total }} booking{{ total > 1 ? 's' : '' }} trouvé{{ total > 1 ? 's' : '' }}
        </p>

        <!-- Cards Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <BookingCard
            v-for="booking in bookings"
            :key="booking.id"
            :booking="booking"
          />
        </div>

        <!-- Pagination -->
        <div
          v-if="lastPage > 1"
          class="mt-8 flex items-center justify-center gap-2"
        >
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!hasPrevPage"
            @click="handlePrevPage"
          >
            <ChevronLeft class="h-5 w-5" />
          </button>

          <div class="flex items-center gap-1">
            <button
              v-for="page in getPageNumbers()"
              :key="page"
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium transition-colors"
              :class="[
                page === currentPage
                  ? 'bg-primary text-white'
                  : 'border border-border bg-card hover:bg-muted',
              ]"
              @click="handleGoToPage(page)"
            >
              {{ page }}
            </button>
          </div>

          <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!hasNextPage"
            @click="handleNextPage"
          >
            <ChevronRight class="h-5 w-5" />
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

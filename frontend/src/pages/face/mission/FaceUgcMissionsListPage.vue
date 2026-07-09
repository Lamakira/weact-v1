<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Inbox, AlertCircle, RefreshCw, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { useUgcMissionDiscovery } from '@/features/mission/composables'
import { UgcMissionCard, UgcPaywallBanner } from '@/features/mission/components'

// Named so <keep-alive :include> in FaceLayout can cache this listing.
defineOptions({ name: 'FaceUgcMissionsListPage' })

/**
 * Découverte des missions UGC (écran 6A). Gating d'affichage piloté
 * exclusivement par meta.can_access_ugc de la réponse (D-2.2.b).
 */
const router = useRouter()

const {
  items,
  canAccessUgc,
  paywall,
  isLoading,
  error,
  isEmpty,
  currentPage,
  lastPage,
  totalCount,
  hasNextPage,
  hasPrevPage,
  fetchMissions,
  nextPage,
  prevPage,
  refreshMissions,
} = useUgcMissionDiscovery()

onMounted(() => {
  fetchMissions(1)
})

function handleCardClick(id: string): void {
  if (!canAccessUgc.value) {
    router.push(paywall.value?.pricing_url ?? '/pricing')
    return
  }
  router.push({ name: 'face-mission-detail', params: { id } })
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-xl min-[376px]:text-2xl font-bold text-slate-800">Missions UGC</h1>
      <p class="mt-1 text-xs min-[376px]:text-sm text-slate-500">
        Recevez des produits et créez du contenu rémunéré
      </p>
    </div>

    <!-- Paywall Banner (Face gratuite) — stable pendant les refetchs, masqué sur l'état erreur -->
    <UgcPaywallBanner
      v-if="!error && !canAccessUgc && paywall"
      :message="paywall.message"
      :pricing-url="paywall.pricing_url"
    />

    <!-- Loading State -->
    <div
      v-if="isLoading && !items.length"
      class="grid gap-4 min-[376px]:gap-6 sm:grid-cols-2 xl:grid-cols-3"
    >
      <div
        v-for="i in 6"
        :key="i"
        class="relative overflow-hidden rounded-lg border border-border bg-card p-5"
        data-testid="ugc-skeleton-card"
      >
        <div class="mb-4 flex flex-col gap-2">
          <div class="h-5 w-24 animate-pulse rounded-full bg-muted" />
          <div class="h-6 w-3/4 animate-pulse rounded bg-muted" />
        </div>
        <div class="mb-6 space-y-2">
          <div class="h-4 w-full animate-pulse rounded bg-muted" />
          <div class="h-4 w-2/3 animate-pulse rounded bg-muted" />
        </div>
        <div class="mb-6 grid grid-cols-2 gap-3">
          <div class="h-4 w-full animate-pulse rounded bg-muted" />
          <div class="h-4 w-full animate-pulse rounded bg-muted" />
        </div>
        <div class="h-px w-full bg-border" />
        <div class="mt-4 flex items-center gap-3">
          <div class="h-8 w-8 animate-pulse rounded-full bg-muted" />
          <div class="flex-1 space-y-1">
            <div class="h-3 w-16 animate-pulse rounded bg-muted" />
            <div class="h-4 w-24 animate-pulse rounded bg-muted" />
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-16 text-center"
    >
      <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
        <AlertCircle class="h-10 w-10" />
      </div>
      <h3 class="text-lg min-[376px]:text-xl font-bold text-foreground">
        Oups ! Une erreur est survenue
      </h3>
      <p class="mt-2 max-w-xs text-xs min-[376px]:text-sm text-muted-foreground">
        Impossible de charger les missions UGC pour le moment.
      </p>
      <p class="mt-2 text-xs min-[376px]:text-sm text-destructive">{{ error }}</p>
      <button
        type="button"
        class="mt-6 flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted"
        @click="refreshMissions"
      >
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
        Réessayer
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="isEmpty" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="relative mb-6">
        <div class="absolute -inset-4 animate-pulse rounded-full bg-primary/5 blur-2xl" />
        <div
          class="relative flex h-24 w-24 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
          <Inbox class="h-12 w-12 opacity-50" />
        </div>
      </div>
      <h3 class="text-xl min-[376px]:text-2xl font-bold text-foreground">
        Aucune mission UGC disponible
      </h3>
      <p class="mt-3 max-w-sm text-xs min-[376px]:text-sm text-muted-foreground">
        Revenez plus tard pour découvrir de nouvelles opportunités UGC.
      </p>
    </div>

    <!-- Content Grid -->
    <div v-else>
      <div class="mb-6 flex items-center justify-between">
        <div class="text-xs min-[376px]:text-sm font-medium text-muted-foreground">
          {{ totalCount }} mission{{ totalCount > 1 ? 's' : '' }} UGC
        </div>
        <button
          type="button"
          class="group p-2 text-muted-foreground transition-colors hover:text-primary"
          title="Rafraîchir la liste"
          @click="refreshMissions"
        >
          <RefreshCw class="h-5 w-5" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>

      <div class="grid gap-4 min-[376px]:gap-6 sm:grid-cols-2 xl:grid-cols-3">
        <UgcMissionCard
          v-for="item in items"
          :key="item.id"
          :item="item"
          :locked="!canAccessUgc"
          @click="handleCardClick"
        />
      </div>

      <!-- Pagination -->
      <div
        v-if="lastPage > 1"
        class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:justify-between"
      >
        <p class="text-xs min-[376px]:text-sm text-muted-foreground">
          Page {{ currentPage }} sur {{ lastPage }}
        </p>

        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="!hasPrevPage || isLoading"
            class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 min-[376px]:px-4 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            @click="prevPage"
          >
            <ChevronLeft class="h-4 w-4" />
            Précédent
          </button>
          <button
            type="button"
            :disabled="!hasNextPage || isLoading"
            class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 min-[376px]:px-4 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            @click="nextPage"
          >
            Suivant
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.text-primary {
  color: #198496;
}
</style>

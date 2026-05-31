<script setup lang="ts">
/**
 * CurrentPlanCard
 * Dashboard Face — carte "Plan actuel" affichée sous le portefeuille (FP-3.2).
 * Lit le palier + statut via le composable partagé `useSubscriptionStatus`
 * (cache 60s — aucun appel réseau dédié si le statut est déjà chargé) et expose
 * un CTA vers la facturation + un lien vers la comparaison des plans.
 *
 * Cohérence avec l'onglet Facturation (FaceBillingPage.vue) :
 *  - noms de palier issus du module partagé `TIER_PRESENTATION` ;
 *  - un abonnement annulé / expiré affiche le plan déchu (pas "Découverte"),
 *    comme `FaceBillingPage.displayTier`.
 */
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { CreditCard } from 'lucide-vue-next'
import { Skeleton } from '@/components/ui/skeleton'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import { TIER_PRESENTATION } from '@/features/face/tierPresentation'
import type { FaceSubscriptionTier } from '@/features/face/types'

const { current, isLoading, error, fetchStatus, refreshStatus } = useSubscriptionStatus()

onMounted(() => {
  // Cache-respecting : `fetchStatus` renvoie le cache s'il est frais (TTL 60s).
  void fetchStatus()
})

// Mirror FaceBillingPage.displayTier — cancelled/expired surface the lapsed plan.
const displayTier = computed<FaceSubscriptionTier>(() => {
  const c = current.value
  if (!c) return 'free'
  if ((c.status === 'cancelled' || c.status === 'expired') && c.plan) return c.plan
  return c.tier
})
const planName = computed<string>(() => TIER_PRESENTATION[displayTier.value].name)
const isFreeTier = computed<boolean>(() => displayTier.value === 'free')

type PlanTone = 'active' | 'cancelled' | 'expired' | 'pending' | 'free'
const planStatus = computed<{ tone: PlanTone; label: string }>(() => {
  switch (current.value?.status) {
    case 'active':
      return { tone: 'active', label: 'Actif' }
    case 'cancelled':
      return { tone: 'cancelled', label: 'Annulé' }
    case 'expired':
      return { tone: 'expired', label: 'Expiré' }
    case 'pending_payment':
      return { tone: 'pending', label: 'En attente de paiement' }
    default:
      // 'free' | 'failed' | undefined → offre gratuite
      return { tone: 'free', label: 'Offre gratuite' }
  }
})

const STATUS_PILL_CLASS: Record<PlanTone, string> = {
  active: 'bg-emerald-50 text-emerald-700',
  cancelled: 'bg-red-50 text-red-700',
  expired: 'bg-gray-100 text-gray-500',
  pending: 'bg-blue-50 text-blue-700',
  free: 'bg-gray-100 text-gray-500',
}
</script>

<template>
  <div
    class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5"
    data-testid="current-plan-card"
  >
    <!-- Loading — only while no cached status is available yet -->
    <template v-if="isLoading && !current">
      <Skeleton class="h-24 w-full rounded-xl" data-testid="plan-skeleton" />
    </template>

    <!-- Error — cold-cache fetch failed: never claim "Découverte" for a paying Face -->
    <template v-else-if="error && !current">
      <div data-testid="plan-error">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Plan actuel</p>
        <p class="text-sm text-gray-500 mt-2">Statut indisponible pour le moment.</p>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-4">
          <button
            type="button"
            class="text-sm font-semibold text-[#198496] border border-[#198496] hover:bg-[#198496]/5 px-4 py-2 rounded-md transition-colors"
            data-testid="plan-retry"
            @click="refreshStatus()"
          >
            Réessayer
          </button>
          <RouterLink
            :to="{ name: 'face-billing' }"
            class="text-sm font-medium text-[#198496] hover:underline"
            data-testid="plan-billing-cta"
          >
            Gérer mon plan
          </RouterLink>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="flex items-center gap-3">
        <div
          class="w-11 h-11 rounded-xl bg-[#198496]/10 flex items-center justify-center text-[#198496] flex-shrink-0"
        >
          <CreditCard :size="20" />
        </div>
        <div class="min-w-0">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Plan actuel</p>
          <p class="text-lg font-bold text-gray-900 mt-0.5 truncate" data-testid="plan-tier-name">{{ planName }}</p>
        </div>
        <span
          class="ml-auto text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0 whitespace-nowrap"
          :class="STATUS_PILL_CLASS[planStatus.tone]"
          data-testid="plan-status"
        >{{ planStatus.label }}</span>
      </div>

      <p v-if="isFreeTier" class="text-xs text-gray-500 mt-3" data-testid="plan-upsell-copy">
        Découvrez les plans payants pour plus de visibilité et une commission réduite.
      </p>

      <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-4">
        <RouterLink
          :to="{ name: 'face-billing' }"
          class="text-sm font-semibold text-white bg-[#198496] hover:bg-[#146c7a] px-4 py-2 rounded-md transition-colors"
          data-testid="plan-billing-cta"
        >
          Gérer mon plan
        </RouterLink>
        <RouterLink
          :to="{ name: 'pricing' }"
          class="text-sm font-medium text-[#198496] hover:underline"
          data-testid="plan-compare-link"
        >
          Comparer les plans
        </RouterLink>
      </div>
    </template>
  </div>
</template>

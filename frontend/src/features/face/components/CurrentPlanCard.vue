<script setup lang="ts">
/**
 * CurrentPlanCard
 * Dashboard Face — panneau « Abonnement » (direction Régie, FP-3.2).
 * Lit le palier + statut via le composable partagé `useSubscriptionStatus`
 * (cache 60s — aucun appel réseau dédié si le statut est déjà chargé) et expose
 * un CTA vers la facturation + un lien vers la comparaison des plans.
 *
 * Cohérence avec l'onglet Facturation (FaceBillingPage.vue) :
 *  - noms de palier issus du module partagé `TIER_PRESENTATION` ;
 *  - un abonnement annulé / expiré affiche le plan déchu (pas "Découverte"),
 *    comme `FaceBillingPage.displayTier` (partagé via `displayTierOf`).
 */
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import RPanel from '@/components/regie/RPanel.vue'
import RStatusDot from '@/components/regie/RStatusDot.vue'
import type { StatusTone } from '@/components/regie/statusTone'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import { TIER_PRESENTATION, displayTierOf } from '@/features/face/tierPresentation'
import type { FaceSubscriptionTier } from '@/features/face/types'

const { current, isLoading, error, fetchStatus, refreshStatus } = useSubscriptionStatus()

onMounted(() => {
  // Cache-respecting : `fetchStatus` renvoie le cache s'il est frais (TTL 60s).
  void fetchStatus()
})

const displayTier = computed<FaceSubscriptionTier>(() => displayTierOf(current.value))
const planName = computed<string>(() => TIER_PRESENTATION[displayTier.value].name)
const isFreeTier = computed<boolean>(() => displayTier.value === 'free')

const planStatus = computed<{ tone: StatusTone; label: string }>(() => {
  switch (current.value?.status) {
    case 'active':
      return { tone: 'success', label: 'Actif' }
    case 'cancelled':
      return { tone: 'danger', label: 'Annulé' }
    case 'expired':
      return { tone: 'neutral', label: 'Expiré' }
    case 'pending_payment':
      return { tone: 'pending', label: 'En attente de paiement' }
    default:
      // 'free' | 'failed' | undefined → offre gratuite
      return { tone: 'neutral', label: 'Offre gratuite' }
  }
})

// Taux de commission du palier courant (capabilities.commission_rate est une fraction).
const commissionLabel = computed<string | null>(() => {
  const rate = current.value?.capabilities?.commission_rate
  if (rate === undefined || rate === null) return null
  return `Commission ${Math.round(rate * 100)} %`
})

// Échéance : abonnements payés en une fois (pas de prélèvement automatique) → « expire le ».
const expiryLabel = computed<string | null>(() => {
  const c = current.value
  if (!c || !c.expires_at || (c.status !== 'active' && c.status !== 'cancelled')) return null
  const date = new Date(c.expires_at)
  if (Number.isNaN(date.getTime())) return null
  const formatted = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long' }).format(date)
  return c.status === 'cancelled' ? `actif jusqu'au ${formatted}` : `expire le ${formatted}`
})
</script>

<template>
  <RPanel title="Abonnement" data-testid="current-plan-card">
    <!-- Loading — only while no cached status is available yet -->
    <div v-if="isLoading && !current" class="p-4 sm:p-5">
      <Skeleton class="h-20 w-full rounded-xl" data-testid="plan-skeleton" />
    </div>

    <!-- Error — cold-cache fetch failed: never claim "Découverte" for a paying Face -->
    <div v-else-if="error && !current" class="p-4 sm:p-5" data-testid="plan-error">
      <p class="text-dash text-ink-3">Statut indisponible pour le moment.</p>
      <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
        <Button
          type="button"
          variant="regie-secondary"
          size="regie"
          data-testid="plan-retry"
          @click="refreshStatus()"
        >
          Réessayer
        </Button>
        <RouterLink
          :to="{ name: 'face-billing' }"
          class="text-[13px] font-semibold text-weact-700 hover:underline"
          data-testid="plan-billing-cta"
        >
          Gérer mon plan
        </RouterLink>
      </div>
    </div>

    <div v-else class="p-4 sm:p-5">
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <p class="text-[20px] font-semibold tracking-[-0.02em] text-ink" data-testid="plan-tier-name">{{ planName }}</p>
        <RStatusDot
          :tone="planStatus.tone"
          :label="planStatus.label"
          class="text-[12.5px] text-ink-2"
          data-testid="plan-status"
        />
      </div>

      <p class="mt-1 text-[12.5px] text-ink-3" data-testid="plan-details">
        <span v-if="commissionLabel">{{ commissionLabel }}</span>
        <span v-if="commissionLabel && expiryLabel"> · </span>
        <span v-if="expiryLabel">{{ expiryLabel }}</span>
      </p>

      <p v-if="isFreeTier" class="mt-3 text-[12.5px] text-ink-3" data-testid="plan-upsell-copy">
        Découvrez les plans payants pour plus de visibilité et une commission réduite.
      </p>

      <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
        <Button as-child variant="regie" size="regie">
          <RouterLink :to="{ name: 'face-billing' }" data-testid="plan-billing-cta">Gérer mon plan</RouterLink>
        </Button>
        <RouterLink
          :to="{ name: 'pricing' }"
          class="text-[13px] font-semibold text-weact-700 hover:underline"
          data-testid="plan-compare-link"
        >
          Comparer les plans
        </RouterLink>
      </div>
    </div>
  </RPanel>
</template>

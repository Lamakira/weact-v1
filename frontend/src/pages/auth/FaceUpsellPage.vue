<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Crown } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue'
import { TIER_PRESENTATION } from '@/features/face/tierPresentation'
import type { FaceSubscriptionPlan } from '@/features/face/types'

// The three purchasable tiers, ascending. Names/taglines/badge come from the
// shared TIER_PRESENTATION map (no duplicated tier-name table). Prices + full
// comparison + payment live on /pricing, which each CTA deep-links into via ?plan=.
const PAID_TIERS: FaceSubscriptionPlan[] = ['starter', 'pro', 'elite']

// CTA labels mirror PricingView's wording for consistency.
const CTA_LABEL: Record<FaceSubscriptionPlan, string> = {
  starter: 'Choisir Starter',
  pro: 'Choisir Pro',
  elite: 'Passer Élite',
}

const router = useRouter()

// Honesty for the unverified case (FP-3.5 review D1-A): a just-registered Face is
// always unverified, and PricingView swallows the ?plan= deep-link when the email
// isn't verified — so the « Choisir … » CTAs would silently no-op. When unverified
// we surface the reusable EmailVerificationBanner (with resend) and disable the
// tier CTAs instead of linking to a dead-end. `isEmailVerified` is already in the
// store right after registration (no fetch needed).
const authStore = useAuthStore()
const isEmailVerified = computed(() => authStore.isEmailVerified)

// Tier-aware guard (FP-3.5 review D2): /bienvenue only guards requiresAuth + role,
// not subscription tier. An already-subscribed Face reaching it directly would see
// misleading « Découverte » upsell copy. Fetch the status once and bounce a paying
// Face to the dashboard. The redirect targets face-dashboard (AWAY from /bienvenue)
// → no loop. For the common just-registered free Face, tier is 'free' → page stays.
const { tier: currentTier, fetchStatus } = useSubscriptionStatus()

onMounted(async () => {
  await fetchStatus()
  if (currentTier.value !== 'free') {
    router.replace({ name: 'face-dashboard' })
  }
})
</script>

<template>
  <div
    class="min-h-screen bg-gray-50 flex flex-col items-center px-6 py-12"
    data-testid="face-upsell-page"
  >
    <div class="max-w-3xl w-full text-center">
      <h1 class="text-3xl font-bold text-gray-900 mb-2">Bienvenue sur WEACT 🎉</h1>
      <p class="text-gray-600 mb-8">
        Votre profil Découverte est prêt. Passez à un palier supérieur pour débloquer plus de
        portfolio, de visibilité et de missions UGC rémunérées — ou commencez gratuitement.
      </p>

      <!-- Email not verified: show the reusable banner (with resend) and disable
           the tier CTAs so the user is never sent to a dead-end /pricing deep-link
           that can't open the payment modal yet (review D1-A). -->
      <div v-if="!isEmailVerified" class="mb-8 text-left" data-testid="upsell-verify-email">
        <EmailVerificationBanner />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8" data-testid="upsell-tiers">
        <div
          v-for="tier in PAID_TIERS"
          :key="tier"
          :data-testid="`upsell-tier-${tier}`"
          class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex flex-col text-left"
        >
          <div class="flex items-center gap-2 mb-1">
            <h2 class="text-lg font-bold text-gray-900">{{ TIER_PRESENTATION[tier].name }}</h2>
            <Crown v-if="tier === 'elite'" class="h-4 w-4 text-[#198496]" />
            <span
              v-else-if="TIER_PRESENTATION[tier].badge"
              class="text-[9px] font-bold tracking-[0.12em] uppercase text-white bg-[#198496] px-1.5 py-0.5 rounded"
            >{{ TIER_PRESENTATION[tier].badge }}</span>
          </div>
          <p class="text-sm text-gray-500 mb-4 flex-grow">{{ TIER_PRESENTATION[tier].tagline }}</p>
          <RouterLink
            v-if="isEmailVerified"
            :to="{ name: 'pricing', query: { plan: tier } }"
            :data-testid="`upsell-cta-${tier}`"
            class="w-full text-center text-sm font-semibold py-2.5 px-4 rounded-md bg-[#198496] text-white hover:bg-[#146c7a] transition-colors"
          >{{ CTA_LABEL[tier] }}</RouterLink>
          <span
            v-else
            :data-testid="`upsell-cta-${tier}`"
            aria-disabled="true"
            class="w-full text-center text-sm font-semibold py-2.5 px-4 rounded-md bg-gray-200 text-gray-400 cursor-not-allowed"
          >Vérifiez votre email d'abord</span>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
        <RouterLink
          :to="{ name: 'pricing' }"
          data-testid="upsell-compare-link"
          class="text-sm font-medium text-[#198496] hover:underline"
        >Voir la comparaison complète des plans</RouterLink>
        <RouterLink
          :to="{ name: 'face-dashboard' }"
          data-testid="upsell-continue-free"
          class="text-sm font-semibold text-gray-600 hover:text-gray-900"
        >Continuer en Découverte</RouterLink>
      </div>
    </div>
  </div>
</template>

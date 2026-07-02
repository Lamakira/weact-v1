<script setup lang="ts">
import { Lock } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'

withDefaults(
  defineProps<{
    message: string
    pricingUrl?: string
  }>(),
  { pricingUrl: '/pricing' },
)
</script>

<template>
  <div
    class="mb-6 flex items-center gap-3 rounded-lg border border-primary/25 bg-primary/5 p-3"
    data-testid="ugc-paywall-banner"
  >
    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary">
      <Lock class="h-4 w-4 text-white" />
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold text-foreground">Accès UGC réservé aux abonnés</p>
      <p class="mt-0.5 text-xs text-muted-foreground">{{ message }}</p>
    </div>
    <RouterLink
      :to="pricingUrl"
      class="shrink-0 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-primary/90"
      data-testid="paywall-cta"
    >
      Voir les abonnements
    </RouterLink>
  </div>
</template>

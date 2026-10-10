<script setup lang="ts">
withDefaults(
  defineProps<{
    message?: string
    pricingUrl?: string
  }>(),
  {
    message:
      'Postuler aux missions est réservé aux Faces abonnées. Les Producteurs peuvent toujours vous réserver directement.',
    pricingUrl: '/pricing',
  },
)
</script>

<template>
  <div
    class="mb-6 flex flex-col gap-3 rounded-[10px] bg-white p-3 ring-1 ring-[#E5E5E5] sm:flex-row sm:items-center"
    data-testid="apply-subscription-banner"
  >
    <p class="min-w-0 flex-1 text-sm text-[#525252]">{{ message }}</p>
    <RouterLink
      :to="pricingUrl"
      class="shrink-0 rounded-[10px] bg-[#167686] px-3 py-1.5 text-center text-xs font-medium text-white transition-colors hover:bg-[#126676]"
      data-testid="apply-subscription-banner-cta"
    >
      Voir les abonnements
    </RouterLink>
  </div>
</template>

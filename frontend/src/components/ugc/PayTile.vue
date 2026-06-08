<script setup lang="ts">
/**
 * PayTile (UX-DR8) — informational payment-method tile.
 *
 * Presentational only: the real provider is chosen on FedaPay's hosted checkout
 * page (the `checkout_url` is provider-agnostic), so this tile has no selection
 * state — it just communicates an accepted method (MTN / Moov / Carte).
 */
type Provider = 'mtn' | 'moov' | 'fedapay'

defineProps<{ provider: Provider }>()

const CONFIG: Record<Provider, { name: string; sub: string; initials: string; bg: string; fg: string }> = {
  mtn: { name: 'MTN MoMo', sub: 'Mobile Money', initials: 'MTN', bg: '#FFCC00', fg: '#0F1419' },
  moov: { name: 'Moov Money', sub: 'Mobile Money', initials: 'M', bg: '#0066B3', fg: '#ffffff' },
  fedapay: { name: 'Carte bancaire', sub: 'Visa / Mastercard via FedaPay', initials: 'FP', bg: '#0F1419', fg: '#ffffff' },
}
</script>

<template>
  <div
    class="flex items-center gap-3 rounded-md border border-gray-200 bg-white p-3"
    data-testid="pay-tile"
  >
    <div
      class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md text-xs font-bold"
      :style="{ background: CONFIG[provider].bg, color: CONFIG[provider].fg }"
    >
      {{ CONFIG[provider].initials }}
    </div>
    <div class="min-w-0">
      <div class="text-sm font-semibold text-gray-900">{{ CONFIG[provider].name }}</div>
      <div class="truncate text-[11px] text-gray-500">{{ CONFIG[provider].sub }}</div>
    </div>
  </div>
</template>

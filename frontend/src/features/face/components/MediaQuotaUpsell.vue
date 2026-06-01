<script setup lang="ts">
import { computed } from 'vue'
import type { FaceSubscriptionTier, SubscriptionOffer, TierCapabilities } from '../types'
import { resolveQuotaUpsellTarget, MEDIA_NOUN, type MediaQuotaKey } from '../mediaQuotaUpsell'

interface Props {
  mediaKey: MediaQuotaKey
  description: string
  capabilities: TierCapabilities
  currentTier: FaceSubscriptionTier
  offers: SubscriptionOffer[]
}
const props = defineProps<Props>()

const currentQuota = computed(() => props.capabilities[props.mediaKey])

// Build the two rendered sentences in <script> (not the template) so TS narrowing
// of the null upsell target is reliable — vue-tsc does not always narrow `v-if`
// across to an interpolation, which would flag `upsellTarget.tierName` as possibly
// null. The template then just interpolates plain strings.
const quotaLine = computed<string | null>(() =>
  currentQuota.value >= 1
    ? `Ajoutez jusqu'à ${currentQuota.value} ${MEDIA_NOUN[props.mediaKey](currentQuota.value)}.`
    : null,
)
const upsellLine = computed<string | null>(() => {
  const target = resolveQuotaUpsellTarget(
    props.mediaKey,
    props.currentTier,
    currentQuota.value,
    props.offers,
  )
  if (!target) return null
  return `Passez au plan ${target.tierName} pour ${target.quota} ${MEDIA_NOUN[props.mediaKey](target.quota)}.`
})
</script>

<template>
  <div class="mt-1 mb-6 space-y-1.5" :data-testid="`media-quota-${mediaKey}`">
    <p class="text-sm text-slate-500">{{ description }}</p>

    <p
      v-if="quotaLine"
      class="text-sm text-slate-500"
      :data-testid="`media-quota-current-${mediaKey}`"
    >
      {{ quotaLine }}
    </p>

    <p
      v-if="upsellLine"
      class="text-sm font-medium text-[#198496]"
      :data-testid="`media-quota-upsell-${mediaKey}`"
    >
      {{ upsellLine }}
    </p>
  </div>
</template>

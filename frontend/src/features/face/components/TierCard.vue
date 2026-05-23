<script setup lang="ts">
import { computed } from 'vue'
import { Check, Crown } from 'lucide-vue-next'
import type { SubscriptionOffer } from '@/features/face/types'
import { TIER_PRESENTATION, buildTierFeatureLines } from '@/features/face/tierPresentation'

type TierRelation = 'current' | 'upgrade' | 'downgrade' | 'unavailable'

interface Props {
  offer: SubscriptionOffer
  relation: TierRelation
  ctaEnabled: boolean
  ladderIndex: number // 0-3 — segments filled up to and including this index
}

const props = defineProps<Props>()
const emit = defineEmits<{ select: [] }>()

const presentation = computed(() => TIER_PRESENTATION[props.offer.tier])
const isElite = computed(() => props.offer.tier === 'elite')
const isCurrent = computed(() => props.relation === 'current')
const features = computed(() =>
  buildTierFeatureLines(props.offer.tier, props.offer.capabilities),
)

const priceLabel = computed(() =>
  props.offer.price === 0
    ? 'Gratuit'
    : new Intl.NumberFormat('fr-FR').format(props.offer.price),
)

const ctaLabel = computed(() => {
  switch (props.relation) {
    case 'current':
      return 'Renouveler'
    case 'upgrade':
      return `Passer à ${presentation.value.name}`
    case 'downgrade':
      return `Revenir à ${presentation.value.name}`
    default:
      return ''
  }
})

// The Free card when the Face holds a paid tier (relation 'unavailable') shows no CTA.
const showCta = computed(() => props.relation !== 'unavailable')

const ctaVariantClass = computed<string>(() => {
  if (props.offer.tier === 'elite') {
    return 'bg-white text-[#0F1419] hover:bg-gray-100'
  }
  if (props.offer.tier === 'pro') {
    return 'bg-[#198496] text-white hover:bg-[#146c7a]'
  }
  return 'text-[#198496] border border-[#198496] hover:bg-[#198496]/5'
})

function ladderSegmentClass(segmentIndex: number): string {
  const filled = segmentIndex <= props.ladderIndex
  if (isElite.value) {
    return filled ? 'bg-white' : 'bg-gray-700'
  }
  return filled ? 'bg-[#198496]' : 'bg-gray-200'
}

function featureTextClass(highlight: boolean): string {
  if (isElite.value) {
    return highlight ? 'text-white font-semibold' : 'text-gray-300'
  }
  return highlight ? 'text-gray-900 font-semibold' : 'text-gray-700'
}

function onCtaClick(): void {
  if (!props.ctaEnabled) return
  emit('select')
}
</script>

<template>
  <div
    :data-testid="`tier-card-${offer.tier}`"
    class="flex flex-col p-7 relative rounded-2xl border border-gray-200 transition-shadow"
    :class="[
      isElite ? 'bg-[#0F1419] text-white' : 'bg-white',
      isCurrent ? 'ring-2 ring-[#198496] ring-offset-2' : '',
    ]"
  >
    <!-- Ladder indicator -->
    <div class="flex gap-1 mb-5">
      <span
        v-for="segment in 4"
        :key="segment"
        class="w-5 h-[3px] rounded-full"
        :class="ladderSegmentClass(segment - 1)"
      />
    </div>

    <!-- Name + badge -->
    <div class="flex items-center gap-2 mb-1.5">
      <h3
        class="text-xl font-bold tracking-tight"
        :class="isElite ? 'text-white' : 'text-gray-900'"
      >
        {{ presentation.name }}
      </h3>
      <Crown v-if="isElite" class="h-3.5 w-3.5 text-white" />
      <span
        v-else-if="presentation.badge"
        class="text-[9px] font-bold tracking-[0.12em] uppercase text-white bg-[#198496] px-1.5 py-0.5 rounded"
      >
        {{ presentation.badge }}
      </span>
    </div>

    <!-- Tagline -->
    <p
      class="text-sm leading-snug mb-6 min-h-[38px]"
      :class="isElite ? 'text-gray-400' : 'text-gray-500'"
    >
      {{ presentation.tagline }}
    </p>

    <!-- Price -->
    <div class="mb-5 flex items-baseline gap-1.5">
      <span
        class="text-[38px] font-bold tracking-tight leading-none"
        :class="isElite ? 'text-white' : 'text-gray-900'"
        data-testid="tier-card-price"
      >
        {{ priceLabel }}
      </span>
      <span
        v-if="offer.price > 0"
        class="text-sm font-medium"
        :class="isElite ? 'text-gray-400' : 'text-gray-500'"
      >
        F CFA
      </span>
    </div>

    <!-- Current-tier marker -->
    <p
      v-if="isCurrent"
      class="mb-3 text-sm font-semibold text-[#198496]"
      data-testid="tier-card-current-marker"
    >
      Palier actuel
    </p>

    <!-- CTA -->
    <button
      v-if="showCta"
      type="button"
      :data-testid="`tier-card-cta-${offer.tier}`"
      :disabled="!ctaEnabled"
      class="w-full text-center text-sm font-semibold py-2.5 px-4 rounded-md transition-colors"
      :class="[ctaVariantClass, !ctaEnabled ? 'opacity-50 cursor-not-allowed' : '']"
      @click="onCtaClick"
    >
      {{ ctaLabel }}
    </button>

    <!-- Feature list -->
    <p
      class="mt-6 mb-3.5 text-[11px] font-bold uppercase tracking-[0.10em]"
      :class="isElite ? 'text-gray-500' : 'text-gray-400'"
    >
      Inclus
    </p>
    <ul class="space-y-2.5">
      <li
        v-for="(feature, index) in features"
        :key="index"
        class="flex items-start gap-2.5"
      >
        <Check
          class="h-4 w-4 mt-0.5 shrink-0"
          :class="isElite ? 'text-white' : 'text-[#198496]'"
        />
        <span class="text-sm leading-snug" :class="featureTextClass(feature.highlight)">
          {{ feature.text }}
        </span>
      </li>
    </ul>
  </div>
</template>

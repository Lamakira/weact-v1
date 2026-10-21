<script setup lang="ts">
import { computed } from 'vue'
import type { MissionEligibility } from '../types'

// Lot D8 : pastilles sobres sur les cartes de missions côté Face.
// « Déjà candidaté » (avec son statut court), genre incompatible, abonnement manquant.
const props = defineProps<{
  myCandidature?: string | null
  myCandidatureLabel?: string | null
  eligibility?: MissionEligibility
}>()

const applied = computed(() => {
  if (!props.myCandidature) return null
  return props.myCandidatureLabel
    ? `Déjà candidaté · ${props.myCandidatureLabel}`
    : 'Déjà candidaté'
})

const incompatible = computed(() => props.eligibility === 'gender_mismatch')

const subscriptionRequired = computed(
  () =>
    props.eligibility === 'subscription_required' ||
    props.eligibility === 'ugc_subscription_required',
)

const hasAnyMark = computed(
  () => applied.value !== null || incompatible.value || subscriptionRequired.value,
)
</script>

<template>
  <div v-if="hasAnyMark" class="flex flex-wrap items-center gap-1.5" data-testid="mission-marks">
    <span
      v-if="applied"
      class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
      data-testid="mark-applied"
    >
      {{ applied }}
    </span>
    <span
      v-if="incompatible"
      class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
      data-testid="mark-incompatible"
    >
      Non compatible avec votre profil
    </span>
    <span
      v-if="subscriptionRequired"
      class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
      data-testid="mark-subscription"
    >
      Abonnement requis
    </span>
  </div>
</template>

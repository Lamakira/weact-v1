<script setup lang="ts">
import { computed } from 'vue'
import { FileSignature } from 'lucide-vue-next'
import { dureeLabel } from '../constants'
import type { MissionImageTerms } from '../types'

const props = defineProps<{
  terms: MissionImageTerms | null | undefined
}>()

const signedOn = computed(() => {
  const iso = props.terms?.producer_signed_at
  if (!iso) return ''
  const date = new Date(iso)
  if (isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(date)
})

const cells = computed(() => {
  const terms = props.terms
  if (!terms) return []
  return [
    { key: 'finalite', label: 'Finalité', value: terms.finalite },
    { key: 'supports', label: 'Supports', value: terms.supports.map((support) => support.label).join(', ') },
    { key: 'territoire', label: 'Territoire', value: terms.territoire.label },
    { key: 'duree', label: 'Durée', value: dureeLabel(terms.duree_annees) },
  ]
})
</script>

<template>
  <section
    v-if="terms"
    class="rounded-xl border border-border bg-card px-4 py-3"
    data-testid="mission-image-terms-band"
  >
    <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-foreground" data-testid="mission-image-terms-band-title">
      <FileSignature :size="16" class="text-primary" aria-hidden="true" />
      {{ signedOn ? `Conditions signées le ${signedOn}` : 'Conditions signées' }}
    </h3>
    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-4">
      <div v-for="cell in cells" :key="cell.key" class="min-w-0">
        <dt class="text-xs text-muted-foreground">{{ cell.label }}</dt>
        <dd class="font-medium text-foreground" :data-testid="`band-${cell.key}`">{{ cell.value }}</dd>
      </div>
    </dl>
  </section>
</template>

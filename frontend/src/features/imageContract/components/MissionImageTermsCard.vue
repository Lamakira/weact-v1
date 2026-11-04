<script setup lang="ts">
import { computed } from 'vue'
import { FileSignature } from 'lucide-vue-next'
import { dureeLabel } from '../constants'
import type { MissionImageTerms } from '../types'

const props = defineProps<{
  terms: MissionImageTerms
  /** La Face est déjà retenue : la phrase annonce la signature à faire plutôt qu'une éventualité. */
  retained?: boolean
}>()

const rows = computed(() => [
  { key: 'finalite', label: 'Finalité', value: props.terms.finalite },
  { key: 'supports', label: 'Supports', value: props.terms.supports.map((support) => support.label).join(', ') },
  { key: 'territoire', label: 'Territoire', value: props.terms.territoire.label },
  { key: 'duree', label: 'Durée', value: dureeLabel(props.terms.duree_annees) },
])
</script>

<template>
  <section
    class="rounded-2xl border border-gray-100 bg-white p-4 min-[376px]:p-6 shadow-sm"
    data-testid="mission-image-terms-card"
  >
    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
      <FileSignature :size="16" class="text-primary" aria-hidden="true" />
      Utilisation de votre image
    </h2>

    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
      <div v-for="row in rows" :key="row.key" class="min-w-0">
        <dt class="text-xs text-gray-500">{{ row.label }}</dt>
        <dd class="font-medium text-gray-900" :data-testid="`image-terms-${row.key}`">{{ row.value }}</dd>
      </div>
    </dl>

    <p class="mt-4 border-t border-gray-100 pt-3 text-sm text-gray-600" data-testid="mission-image-terms-note">
      <template v-if="retained">
        Vous êtes retenue : vous signerez ces conditions avec un code reçu par e-mail pour confirmer votre participation.
      </template>
      <template v-else>
        Si vous êtes retenue, vous signerez ces conditions avec un code reçu par e-mail. Candidater ne vous engage pas encore.
      </template>
    </p>
  </section>
</template>

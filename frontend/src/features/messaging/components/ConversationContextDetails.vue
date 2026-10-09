<script setup lang="ts">
/**
 * Détails du contexte d'une conversation : étapes, date, lieu et montants selon le
 * rôle. Partagé entre le panneau desktop et la barre repliable mobile.
 */
import { computed } from 'vue'
import { Check } from 'lucide-vue-next'
import { formatXof } from '@/lib/formatCurrency'
import type {
  ConversationContext,
  FaceContextAmounts,
  ProducerContextAmounts,
} from '../types'
import { formatContextDate } from '../utils/messageFormat'

const props = defineProps<{
  context: ConversationContext
  role: 'face' | 'producer'
}>()

const date = computed(() => formatContextDate(props.context.date_tournage))

interface AmountRow {
  label: string
  value: string
  strong?: boolean
}

const amountRows = computed<AmountRow[]>(() => {
  const rows: AmountRow[] = []
  if (props.role === 'face') {
    const amounts = props.context.amounts as FaceContextAmounts
    if (amounts.face_receives !== null) {
      rows.push({ label: 'Vous recevez', value: formatXof(amounts.face_receives), strong: true })
    }
    if (amounts.product_value !== null) {
      rows.push({ label: 'Valeur du produit', value: formatXof(amounts.product_value) })
    }
    return rows
  }
  const amounts = props.context.amounts as ProducerContextAmounts
  if (amounts.cachet !== null) rows.push({ label: 'Cachet', value: formatXof(amounts.cachet) })
  if (amounts.service_fee !== null) {
    rows.push({ label: 'Frais de service', value: formatXof(amounts.service_fee) })
  }
  if (amounts.total !== null) {
    rows.push({ label: 'Total payé', value: formatXof(amounts.total), strong: true })
  }
  if (amounts.product_value !== null) {
    rows.push({ label: 'Valeur du produit', value: formatXof(amounts.product_value) })
  }
  return rows
})

const rows = computed(() => {
  const list: AmountRow[] = []
  if (date.value) list.push({ label: 'Date', value: date.value })
  if (props.context.lieu) list.push({ label: 'Lieu', value: props.context.lieu })
  return [...list, ...amountRows.value]
})
</script>

<template>
  <div class="space-y-4 text-[12.5px]" data-testid="conversation-context-details">
    <p
      v-if="context.closed"
      class="text-ink-3"
      data-testid="context-closed"
    >
      Candidature {{ context.candidature_status_label.toLowerCase() }}
    </p>

    <ol class="space-y-2.5" aria-label="Avancement">
      <li
        v-for="step in context.steps"
        :key="step.key"
        class="flex items-center gap-2.5"
        :class="step.state === 'todo' ? 'text-ink-3' : 'text-ink'"
        :aria-current="step.state === 'current' ? 'step' : undefined"
        :data-state="step.state"
        data-testid="context-step"
      >
        <span
          v-if="step.state === 'todo'"
          class="size-4 rounded-full ring-1 ring-state-neutral"
          aria-hidden="true"
        />
        <span
          v-else
          class="grid size-4 place-items-center rounded-full bg-weact-600 text-white"
          aria-hidden="true"
        >
          <Check class="!size-2.5" :stroke-width="3" />
        </span>
        <span :class="step.state === 'current' ? 'font-semibold' : ''">{{ step.label }}</span>
      </li>
    </ol>

    <dl v-if="rows.length > 0" class="space-y-2 border-t border-line pt-3">
      <div
        v-for="row in rows"
        :key="row.label"
        class="flex justify-between gap-3"
        :class="row.strong ? 'font-semibold text-ink' : ''"
      >
        <dt :class="row.strong ? '' : 'text-ink-3'">{{ row.label }}</dt>
        <dd class="text-right">{{ row.value }}</dd>
      </div>
    </dl>
  </div>
</template>

<script setup lang="ts">
/**
 * ReputationPanel — module « Réputation » du dashboard Producteur.
 * Note moyenne + nombre d'avis + répartition 5 → 1, puis acceptation, candidatures
 * reçues et collaborateurs. Les chiffres viennent de /producer/dashboard/stats.
 */
import { computed } from 'vue'
import RPanel from '@/components/regie/RPanel.vue'
import { Skeleton } from '@/components/ui/skeleton'
import type { ProducerDashboardStats } from '../../types'

interface Props {
  stats: ProducerDashboardStats | null
  isLoading?: boolean
  error?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  error: null,
})

defineEmits<{ retry: [] }>()

const SCORES = ['5', '4', '3', '2', '1'] as const

const average = computed(() => {
  const value = props.stats?.average_rating
  return value === null || value === undefined ? '—' : value.toFixed(1).replace('.', ',')
})

const ratingsCount = computed(() => props.stats?.ratings_count ?? 0)

const reviewsLabel = computed(() => {
  const n = ratingsCount.value
  if (n === 0) return 'Aucun avis'
  return `${n} avis`
})

const distribution = computed(() =>
  SCORES.map((score) => {
    const count = props.stats?.rating_distribution?.[score] ?? 0
    const percent = ratingsCount.value > 0 ? Math.round((count / ratingsCount.value) * 100) : 0
    return { score, count, percent }
  }),
)

const acceptance = computed(() => {
  const rate = props.stats?.acceptance_rate
  if (rate === null || rate === undefined) return '—'
  return `${Math.round(rate)} %`
})
</script>

<template>
  <RPanel title="Réputation" data-testid="module-reputation">
    <div v-if="isLoading" class="space-y-3 p-4" data-testid="reputation-loading">
      <Skeleton class="h-16 w-full" />
      <Skeleton class="h-10 w-full" />
    </div>

    <p v-else-if="error" class="p-4 text-dash text-ink-2" role="alert" data-testid="reputation-error">
      {{ error }}
      <button type="button" class="font-semibold text-weact-700 hover:underline" @click="$emit('retry')">
        Réessayer
      </button>
    </p>

    <template v-else-if="stats">
      <div class="grid grid-cols-[auto_1fr] items-center gap-5 p-4 sm:px-5">
        <div>
          <p class="text-[34px] font-semibold leading-none tracking-[-0.02em] text-ink" data-testid="reputation-average">
            {{ average }}
          </p>
          <p class="mt-1 text-[12px] text-ink-3" data-testid="reputation-reviews">{{ reviewsLabel }}</p>
        </div>

        <ul v-if="ratingsCount > 0" class="space-y-1 text-[11.5px] text-ink-3" data-testid="reputation-distribution">
          <li v-for="row in distribution" :key="row.score" class="flex items-center gap-2" :data-score="row.score">
            <span class="w-3">{{ row.score }}</span>
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sidebar">
              <div class="h-full rounded-full bg-ink" :style="{ width: `${row.percent}%` }" />
            </div>
            <span class="w-5 text-right" data-testid="reputation-bar-count">{{ row.count }}</span>
          </li>
        </ul>
        <p v-else class="text-dash text-ink-2" data-testid="reputation-empty">
          Vos premiers avis apparaîtront ici après vos premières collaborations.
        </p>
      </div>

      <dl class="grid grid-cols-3 divide-x divide-line border-t border-line text-center">
        <div class="flex flex-col-reverse py-2.5">
          <dt class="text-[11.5px] text-ink-3">acceptation</dt>
          <dd class="text-[16px] font-semibold text-ink" data-testid="reputation-acceptance">{{ acceptance }}</dd>
        </div>
        <div class="flex flex-col-reverse py-2.5">
          <dt class="text-[11.5px] text-ink-3">candidatures</dt>
          <dd class="text-[16px] font-semibold text-ink" data-testid="reputation-candidatures">{{ stats.total_candidatures }}</dd>
        </div>
        <div class="flex flex-col-reverse py-2.5">
          <dt class="text-[11.5px] text-ink-3">collaborateurs</dt>
          <dd class="text-[16px] font-semibold text-ink" data-testid="reputation-collaborators">{{ stats.unique_collaborators }}</dd>
        </div>
      </dl>
    </template>
  </RPanel>
</template>

<script setup lang="ts">
/**
 * ProducerKpiRow — rangée compacte des 4 indicateurs missions (Publiées, En cours,
 * Clôturées, Terminées) : point d'état + libellé, chiffre, contexte. Reprend les
 * chiffres de /producer/dashboard/stats ; pas de micro-tendance (l'API n'a pas de
 * série mensuelle Producteur). 2 × 2 sur mobile, 4 colonnes dès md.
 */
import { AlertCircle, RefreshCw } from 'lucide-vue-next'
import RStatusDot from '@/components/regie/RStatusDot.vue'
import type { StatusTone } from '@/components/regie/statusTone'
import { Skeleton } from '@/components/ui/skeleton'
import { PRODUCER_KPI_CONFIGS, type ProducerDashboardStats } from '../../types'

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

const tones: Record<string, StatusTone> = {
  published: 'success',
  in_progress: 'progress',
  closed: 'pending',
  completed: 'done',
}

function value(key: string): number {
  const raw = props.stats?.[key as keyof ProducerDashboardStats]
  return typeof raw === 'number' ? raw : 0
}

function context(key: string): string | null {
  if (key !== 'published') return null
  const n = props.stats?.total_candidatures ?? 0
  return `${n} candidature${n > 1 ? 's' : ''} reçue${n > 1 ? 's' : ''}`
}
</script>

<template>
  <div
    v-if="error"
    class="flex items-center justify-between gap-3 rounded-panel bg-white p-4 ring-1 ring-line"
    data-testid="stats-error"
    role="alert"
  >
    <div class="flex items-center gap-3">
      <AlertCircle class="size-5 text-destructive" aria-hidden="true" />
      <span class="text-dash text-ink">{{ error }}</span>
    </div>
    <button
      type="button"
      :disabled="isLoading"
      class="inline-flex items-center gap-2 rounded-control px-3 py-1.5 text-dash font-semibold text-weact-700 ring-1 ring-line hover:bg-sidebar disabled:opacity-50"
      data-testid="retry-button"
      @click="$emit('retry')"
    >
      <RefreshCw :class="{ 'animate-spin': isLoading }" class="size-4" aria-hidden="true" />
      Réessayer
    </button>
  </div>

  <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4" data-testid="kpi-cards-grid">
    <div
      v-for="kpi in PRODUCER_KPI_CONFIGS"
      :key="kpi.key"
      class="rounded-panel bg-white p-3 ring-1 ring-line sm:p-4"
      :data-testid="'kpi-card-' + kpi.key"
    >
      <RStatusDot :tone="tones[kpi.key] ?? 'neutral'" :label="kpi.title" class="text-[12.5px] text-ink-3" />
      <Skeleton v-if="isLoading" class="mt-2 h-8 w-12" />
      <p
        v-else
        class="mt-2 text-[28px] font-semibold leading-none tracking-[-0.02em] text-ink"
        :data-testid="'kpi-card-' + kpi.key + '-value'"
      >
        {{ value(kpi.key) }}
      </p>
      <p
        v-if="!isLoading && context(kpi.key)"
        class="mt-2 text-[12px] text-ink-3"
        :data-testid="'kpi-card-' + kpi.key + '-context'"
      >
        {{ context(kpi.key) }}
      </p>
    </div>
  </div>
</template>

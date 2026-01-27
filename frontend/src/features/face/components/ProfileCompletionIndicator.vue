<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next'
import type { ProfileCompletionMissingItem } from '../types'

interface Props {
  percentage?: number
  missingItems?: ProfileCompletionMissingItem[]
  isComplete?: boolean
  variant?: 'compact' | 'full'
}

const props = withDefaults(defineProps<Props>(), {
  percentage: undefined,
  missingItems: () => [],
  isComplete: false,
  variant: 'compact',
})

defineEmits<{
  'click-item': [key: string]
}>()

const circumference = 2 * Math.PI * 28
const dashOffset = computed(() => {
  const p = props.percentage ?? 0
  return circumference - (p / 100) * circumference
})

const isLoading = computed(() => props.percentage === undefined)
</script>

<template>
  <div class="w-full" data-testid="profile-completion-indicator">
    <!-- LOADING SKELETON -->
    <div v-if="isLoading" class="animate-pulse flex items-center gap-4" data-testid="completion-loading">
      <div v-if="variant === 'compact'" class="w-16 h-16 rounded-full bg-muted" />
      <div v-else class="w-full space-y-2">
        <div class="h-4 w-1/4 bg-muted rounded" />
        <div class="h-2 w-full bg-muted rounded-full" />
      </div>
    </div>

    <!-- COMPACT VARIANT -->
    <div
      v-else-if="variant === 'compact'"
      class="flex flex-col items-center gap-2"
      data-testid="completion-compact"
    >
      <div class="relative inline-flex items-center justify-center">
        <svg class="w-16 h-16 transform -rotate-90">
          <circle
            cx="32"
            cy="32"
            r="28"
            stroke="currentColor"
            stroke-width="4"
            fill="transparent"
            class="text-muted/20"
          />
          <circle
            cx="32"
            cy="32"
            r="28"
            stroke="var(--color-weact)"
            stroke-width="4"
            fill="transparent"
            stroke-linecap="round"
            class="transition-all duration-700 ease-in-out"
            :style="{
              strokeDasharray: circumference,
              strokeDashoffset: dashOffset,
            }"
            data-testid="progress-ring"
          />
        </svg>
        <span
          class="absolute text-sm font-semibold text-foreground"
          data-testid="completion-percentage"
        >
          {{ percentage }}%
        </span>
      </div>
      <span
        v-if="isComplete"
        class="text-xs font-medium text-[var(--color-weact)]"
        data-testid="complete-text"
      >
        Profil complet
      </span>
      <span
        v-else-if="percentage === 0"
        class="text-xs font-medium text-muted-foreground"
        data-testid="start-text"
      >
        Commencez à compléter
      </span>
    </div>

    <!-- FULL VARIANT -->
    <div v-else class="space-y-4" data-testid="completion-full">
      <div class="flex items-center justify-between mb-1">
        <span class="text-sm font-medium text-foreground">
          {{ isComplete ? 'Profil complet' : 'Complétez votre profil' }}
        </span>
        <span
          class="text-sm font-bold text-[var(--color-weact)]"
          data-testid="completion-percentage"
        >
          {{ percentage }}%
        </span>
      </div>

      <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
        <div
          class="h-full bg-[var(--color-weact)] transition-all duration-1000 ease-out rounded-full"
          :style="{ width: `${percentage}%` }"
          data-testid="progress-bar"
        />
      </div>

      <div
        v-if="isComplete"
        class="flex items-center gap-2 p-3 rounded-xl bg-[var(--color-weact-50)] border border-[var(--color-weact-100)] text-[var(--color-weact-600)]"
        data-testid="complete-message"
      >
        <CheckCircle2 class="w-5 h-5" />
        <span class="text-sm font-medium">Félicitations ! Votre profil est à jour.</span>
      </div>

      <div v-else-if="missingItems.length > 0" class="space-y-2" data-testid="missing-items-list">
        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
          Éléments manquants
        </p>
        <div class="grid gap-2">
          <button
            v-for="item in missingItems"
            :key="item.key"
            class="flex items-center gap-3 p-2 rounded-lg border border-border bg-background hover:border-[var(--color-weact-200)] hover:bg-[var(--color-weact-50)] transition-all group text-left"
            :data-testid="`missing-item-${item.key}`"
            @click="$emit('click-item', item.key)"
          >
            <AlertCircle
              class="w-4 h-4 text-muted-foreground group-hover:text-[var(--color-weact)] transition-colors"
            />
            <span class="text-sm text-foreground font-medium group-hover:text-[var(--color-weact-600)]">
              {{ item.label }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

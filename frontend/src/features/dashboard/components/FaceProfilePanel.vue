<script setup lang="ts">
/**
 * FaceProfilePanel — panneau « Profil » du dashboard Face (direction Régie) :
 * barre de complétion + éléments restant à compléter (liste existante
 * `profile_completion_missing`, l'API ne renvoie pas les éléments déjà faits).
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { CheckCircle2, Circle } from 'lucide-vue-next'
import RPanel from '@/components/regie/RPanel.vue'
import { Skeleton } from '@/components/ui/skeleton'
import type { ProfileCompletionMissingItem } from '@/features/face/types'

interface Props {
  percentage: number
  missingItems: ProfileCompletionMissingItem[]
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
})

const MAX_VISIBLE = 4

const visibleItems = computed(() => props.missingItems.slice(0, MAX_VISIBLE))
const hiddenCount = computed(() => Math.max(0, props.missingItems.length - MAX_VISIBLE))
</script>

<template>
  <RPanel title="Profil" data-testid="profile-completion-panel">
    <div class="p-4 sm:p-5">
      <Skeleton v-if="isLoading && percentage === 0" class="h-6 w-full" data-testid="profile-completion-skeleton" />
      <template v-else>
        <div class="flex items-baseline justify-between">
          <p class="text-dash text-ink-3">Complétion</p>
          <p class="text-[18px] font-semibold tracking-[-0.02em] text-ink" data-testid="profile-completion-percentage">
            {{ percentage }}%
          </p>
        </div>
        <div
          class="mt-2 h-1.5 overflow-hidden rounded-full bg-sidebar ring-1 ring-line"
          role="progressbar"
          aria-label="Complétion du profil"
          :aria-valuenow="percentage"
          aria-valuemin="0"
          aria-valuemax="100"
        >
          <div class="h-full rounded-full bg-weact-600" :style="{ width: `${percentage}%` }" />
        </div>

        <p
          v-if="missingItems.length === 0"
          class="mt-4 flex items-center gap-2 text-dash text-ink-2"
          data-testid="profile-completion-done"
        >
          <CheckCircle2 class="size-4 text-state-success" aria-hidden="true" />
          Profil complet
        </p>
        <ul v-else class="mt-4 space-y-1.5" data-testid="profile-completion-missing">
          <li v-for="item in visibleItems" :key="item.key">
            <RouterLink
              :to="{ name: 'face-profile' }"
              class="flex items-center gap-2 text-dash text-ink-2 hover:text-ink hover:underline"
              data-testid="profile-completion-item"
            >
              <Circle class="size-4 shrink-0 text-ink-3" aria-hidden="true" />
              <span class="min-w-0 truncate">{{ item.label }}</span>
            </RouterLink>
          </li>
          <li v-if="hiddenCount > 0" class="pl-6 text-[12.5px] text-ink-3" data-testid="profile-completion-more">
            + {{ hiddenCount }} autre{{ hiddenCount > 1 ? 's' : '' }}
          </li>
        </ul>
      </template>
    </div>
  </RPanel>
</template>

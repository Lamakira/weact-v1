<script setup lang="ts">
import { computed } from 'vue'
import { Clock } from 'lucide-vue-next'
import { formatCountdown, type NextBlockContent } from '../utils/missionDetailModel'

/**
 * Bloc « Et ensuite ? » : ce qui se passe maintenant, qui doit agir, avant quand.
 * `now` est fourni par la page (tic toutes les minutes) : le compte à rebours n'est pas animé.
 */
const props = defineProps<{
  content: NextBlockContent
  now: Date
}>()

const countdown = computed(() => formatCountdown(props.content.deadlineAt, props.now))
</script>

<template>
  <section
    class="rounded-[10px] border border-primary bg-primary/10 p-4"
    aria-label="Et ensuite ?"
    data-testid="mission-next-block"
  >
    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-semibold text-primary">
      <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
      Et ensuite ?
    </span>
    <h2 class="mt-2 text-base font-bold text-foreground" data-testid="mission-next-headline">{{ content.headline }}</h2>
    <p class="mt-1.5 text-sm text-foreground" data-testid="mission-next-text">{{ content.text }}</p>
    <div
      v-if="countdown"
      class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[13px] font-bold tabular-nums text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
      data-testid="mission-next-countdown"
    >
      <Clock class="size-3.5" aria-hidden="true" />
      {{ countdown }}
      <span v-if="content.deadlineLabel" class="font-medium"> · {{ content.deadlineLabel }}</span>
    </div>
    <p class="mt-2 text-xs text-muted-foreground" data-testid="mission-next-who">{{ content.who }}</p>
  </section>
</template>

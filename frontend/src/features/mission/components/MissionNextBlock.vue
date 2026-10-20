<script setup lang="ts">
import { computed } from 'vue'
import { Clock } from 'lucide-vue-next'
import { formatCountdown, formatDeadlineLabel, type NextBlockContent } from '../utils/missionDetailModel'

/**
 * Bloc « Et ensuite ? » : ce qui se passe maintenant, qui doit agir, avant quand.
 * `now` est fourni par la page (tic toutes les minutes) : le compte à rebours n'est pas animé.
 */
const props = defineProps<{
  content: NextBlockContent
  now: Date
}>()

const countdown = computed(() => formatCountdown(props.content.deadlineAt, props.now))

// Échéance passée (la tâche d'expiration n'est pas encore passée) : on dit « dépassée
// depuis le … » au lieu d'accoler « atteinte » et « avant le … ».
const isOverdue = computed(() => {
  if (!props.content.deadlineAt) return false
  const time = new Date(props.content.deadlineAt).getTime()
  return !Number.isNaN(time) && time <= props.now.getTime()
})
const overdueSince = computed(() => formatDeadlineLabel(props.content.deadlineAt))
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
    <p
      v-if="countdown"
      class="mt-2.5 flex items-start gap-1.5 rounded-lg px-2.5 py-1.5 text-[13px] leading-snug"
      :class="isOverdue
        ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400'
        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'"
      data-testid="mission-next-countdown"
    >
      <Clock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
      <span v-if="isOverdue">
        <span class="font-bold">Échéance dépassée</span><template v-if="overdueSince"> depuis le {{ overdueSince }}</template>
      </span>
      <span v-else>
        <span class="font-bold">{{ countdown }}</span><template v-if="content.deadlineLabel"> · {{ content.deadlineLabel }}</template>
      </span>
    </p>
    <p class="mt-2 text-xs text-muted-foreground" data-testid="mission-next-who">{{ content.who }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Check } from 'lucide-vue-next'
import type { StepDef } from '../utils/missionDetailModel'

/**
 * Barre d'étapes de la page détail mission : où en est la mission. Le trait de
 * progression se remplit en `scaleX` (transform seul) et part de 0 au premier rendu.
 */
const props = defineProps<{
  steps: StepDef[]
  /** Index de l'étape courante (les précédentes sont faites). */
  current: number
  /** Date courte affichée sous chaque étape (même ordre que `steps`). */
  whens?: (string | null)[]
}>()

const target = computed(() =>
  props.steps.length > 1 ? Math.min(Math.max(props.current, 0) / (props.steps.length - 1), 1) : 0,
)

// Part de 0 puis rejoint la cible après deux frames : la transition CSS joue au montage.
const fill = ref(0)
onMounted(() => {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      fill.value = target.value
    })
  })
})
watch(target, (value) => {
  fill.value = value
})
</script>

<template>
  <nav class="rounded-[10px] border border-border bg-card px-4 py-3.5 max-sm:pb-9" aria-label="Étapes de la mission" data-testid="mission-step-bar">
    <ol class="steps relative grid list-none p-0" :style="{ gridTemplateColumns: `repeat(${steps.length}, 1fr)`, '--n': steps.length }">
      <span class="steps-track" aria-hidden="true" />
      <span class="steps-fill" aria-hidden="true" :style="{ transform: `scaleX(${fill})` }" />
      <li
        v-for="(step, i) in steps"
        :key="step.key"
        class="relative z-[1] grid justify-items-center gap-1.5 px-1 text-center"
        :aria-current="i === current ? 'step' : undefined"
        :data-state="i < current ? 'done' : i === current ? 'now' : 'todo'"
        :data-testid="`mission-step-${step.key}`"
      >
        <span
          class="step-dot grid size-6 place-items-center rounded-full border-2 text-xs font-bold"
          :class="
            i < current
              ? 'border-primary bg-primary text-primary-foreground'
              : i === current
                ? 'border-primary bg-card text-primary ring-4 ring-primary/15'
                : 'border-border bg-card text-muted-foreground'
          "
        >
          <Check v-if="i < current" class="size-3" :stroke-width="3" />
          <template v-else>{{ i + 1 }}</template>
        </span>
        <span
          class="text-xs leading-tight"
          :class="[
            i === current ? 'font-semibold text-foreground' : 'text-muted-foreground',
            i === current ? 'max-sm:absolute max-sm:top-[30px] max-sm:whitespace-nowrap' : 'max-sm:hidden',
          ]"
        >
          {{ step.label }}
        </span>
        <span v-if="whens?.[i]" class="text-[11px] text-muted-foreground max-sm:hidden">{{ whens[i] }}</span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.steps-track,
.steps-fill {
  position: absolute;
  top: 11px;
  left: calc(50% / var(--n));
  right: calc(50% / var(--n));
  height: 2px;
  border-radius: 2px;
}
.steps-track {
  background: var(--border);
}
.steps-fill {
  background: var(--primary);
  transform-origin: left center;
  transition: transform 520ms cubic-bezier(0.23, 1, 0.32, 1);
}
.step-dot {
  transition:
    background-color 200ms ease,
    border-color 200ms ease,
    color 200ms ease;
}
@media (prefers-reduced-motion: reduce) {
  .steps-fill {
    transition: none;
  }
  .step-dot {
    transition: none;
  }
}
</style>

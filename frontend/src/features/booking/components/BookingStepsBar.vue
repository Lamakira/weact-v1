<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Check, X } from 'lucide-vue-next'

const props = defineProps<{
  labels: string[]
  /** Index de l'étape courante (0 = première). */
  current: number
  /** Parcours arrêté (refus, expiration, annulation, absence). */
  halted?: boolean
}>()

const count = computed(() => props.labels.length)
const target = computed(() =>
  count.value > 1 ? Math.min(Math.max(props.current, 0), count.value - 1) / (count.value - 1) : 0,
)

// Le remplissage démarre à 0 puis glisse vers sa cible (transform seul, jamais width).
const scale = ref(0)

function applyTarget(): void {
  if (typeof requestAnimationFrame === 'function') {
    requestAnimationFrame(() => {
      scale.value = target.value
    })
  } else {
    scale.value = target.value
  }
}

onMounted(() => {
  void nextTick(applyTarget)
})
watch(target, applyTarget)

function stateOf(index: number): 'done' | 'now' | 'todo' {
  if (index < props.current) return 'done'
  return index === props.current ? 'now' : 'todo'
}
</script>

<template>
  <nav
    class="rounded-[10px] border border-gray-200 bg-white px-4 pt-3.5 pb-3.5 max-sm:pb-9"
    aria-label="Étapes du booking"
    data-testid="booking-steps"
  >
    <ol class="relative grid" :style="{ gridTemplateColumns: `repeat(${count}, minmax(0, 1fr))` }">
      <span
        class="absolute top-[11px] h-0.5 rounded bg-gray-200"
        :style="{ left: `calc(50% / ${count})`, right: `calc(50% / ${count})` }"
        aria-hidden="true"
      />
      <span
        class="steps-fill absolute top-[11px] h-0.5 origin-left rounded"
        :class="halted ? 'bg-red-400' : 'bg-weact'"
        :style="{ left: `calc(50% / ${count})`, right: `calc(50% / ${count})`, transform: `scaleX(${scale})` }"
        aria-hidden="true"
      />
      <li
        v-for="(label, index) in labels"
        :key="label"
        class="relative z-10 grid justify-items-center gap-1.5 px-1 text-center"
        :data-state="stateOf(index)"
        :aria-current="index === current ? 'step' : undefined"
      >
        <span
          class="grid h-6 w-6 place-items-center rounded-full border-2 text-xs font-bold"
          :class="[
            stateOf(index) === 'done' && 'border-weact bg-weact text-white',
            stateOf(index) === 'now' && !halted && 'border-weact bg-white text-weact ring-4 ring-weact-50',
            stateOf(index) === 'now' && halted && 'border-red-400 bg-white text-red-600 ring-4 ring-red-50',
            stateOf(index) === 'todo' && 'border-gray-200 bg-white text-gray-500',
          ]"
        >
          <Check v-if="stateOf(index) === 'done'" class="h-3 w-3" :stroke-width="3" />
          <X v-else-if="stateOf(index) === 'now' && halted" class="h-3 w-3" :stroke-width="3" />
          <template v-else>{{ index + 1 }}</template>
        </span>
        <span
          class="text-xs leading-tight"
          :class="
            index === current
              ? 'font-semibold text-gray-900 max-sm:absolute max-sm:top-[30px] max-sm:whitespace-nowrap'
              : 'text-gray-500 max-sm:hidden'
          "
        >
          {{ label }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.steps-fill {
  transition: transform 520ms cubic-bezier(0.23, 1, 0.32, 1);
}

@media (prefers-reduced-motion: reduce) {
  .steps-fill {
    transition: none;
  }
}
</style>

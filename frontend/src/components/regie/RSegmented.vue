<script setup lang="ts">
/**
 * RSegmented — contrôle segmenté (ex. Candidatures | Bookings sur mobile).
 * Sémantique radio : `radiogroup` + `radio`, tabindex itinérant, flèches /
 * Home / End déplacent la sélection.
 */
import { ref } from 'vue'

export interface RSegmentedOption {
  value: string
  label: string
}

interface Props {
  options: RSegmentedOption[]
  groupLabel: string
}

const props = defineProps<Props>()

const model = defineModel<string>({ required: true })

const buttons = ref<HTMLButtonElement[]>([])

function select(index: number): void {
  const count = props.options.length
  const next = props.options[((index % count) + count) % count]
  if (!next) return
  model.value = next.value
  buttons.value[props.options.indexOf(next)]?.focus()
}

function onKeydown(event: KeyboardEvent, index: number): void {
  switch (event.key) {
    case 'ArrowRight':
    case 'ArrowDown':
      event.preventDefault()
      select(index + 1)
      break
    case 'ArrowLeft':
    case 'ArrowUp':
      event.preventDefault()
      select(index - 1)
      break
    case 'Home':
      event.preventDefault()
      select(0)
      break
    case 'End':
      event.preventDefault()
      select(props.options.length - 1)
      break
  }
}

function isChecked(value: string): boolean {
  return model.value === value
}
</script>

<template>
  <div
    role="radiogroup"
    :aria-label="groupLabel"
    class="grid gap-0.5 rounded-control bg-sidebar p-1 ring-1 ring-line"
    :style="{ gridTemplateColumns: `repeat(${options.length}, minmax(0, 1fr))` }"
    data-testid="r-segmented"
  >
    <button
      v-for="(option, index) in options"
      :key="option.value"
      :ref="(el) => { if (el) buttons[index] = el as HTMLButtonElement }"
      type="button"
      role="radio"
      :aria-checked="isChecked(option.value)"
      :tabindex="isChecked(option.value) ? 0 : -1"
      class="h-10 rounded-[8px] text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
      :class="
        isChecked(option.value)
          ? 'bg-white font-semibold text-ink ring-1 ring-line'
          : 'text-ink-3 hover:text-ink'
      "
      @click="model = option.value"
      @keydown="onKeydown($event, index)"
    >
      {{ option.label }}
    </button>
  </div>
</template>

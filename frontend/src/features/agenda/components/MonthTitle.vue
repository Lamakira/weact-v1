<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import MonthPicker from './MonthPicker.vue'

/**
 * Titre de période de l'agenda devenu bouton : ouvre le sélecteur de mois, rend le focus au bouton à la
 * fermeture. `variant` : `popover` (ordinateur) ou `sheet` (téléphone).
 */
const props = defineProps<{
  title: string
  /** Mois affiché (AAAA-MM-JJ) et aujourd'hui, pour le sélecteur. */
  month: string
  today: string
  variant: 'popover' | 'sheet'
  headingClass: string
  /** data-testid du titre ; le bouton porte `<testid>-button`. */
  testid: string
}>()

const emit = defineEmits<{ select: [month: string] }>()

const open = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const buttonRef = ref<HTMLButtonElement | null>(null)

async function close(): Promise<void> {
  if (!open.value) return
  open.value = false
  await nextTick()
  buttonRef.value?.focus()
}

function onSelect(month: string): void {
  emit('select', month)
  void close()
}

function onOutside(event: MouseEvent): void {
  if (props.variant !== 'popover' || !open.value) return
  if (rootRef.value && !rootRef.value.contains(event.target as Node)) void close()
}

onMounted(() => document.addEventListener('mousedown', onOutside))
onUnmounted(() => document.removeEventListener('mousedown', onOutside))
</script>

<template>
  <div ref="rootRef" class="relative min-w-0">
    <h2 :class="headingClass" aria-live="polite" :data-testid="testid">
      <button
        ref="buttonRef"
        type="button"
        class="-mx-2 inline-flex min-h-9 items-center gap-1 rounded-lg px-2 hover:bg-sidebar"
        aria-haspopup="dialog"
        :aria-expanded="open"
        :data-testid="`${testid}-button`"
        @click="open = !open"
      >
        {{ title }}
        <ChevronDown class="size-4 shrink-0 text-ink-3" aria-hidden="true" />
      </button>
    </h2>
    <MonthPicker v-if="open" :month="month" :today="today" :variant="variant" @select="onSelect" @close="close" />
  </div>
</template>

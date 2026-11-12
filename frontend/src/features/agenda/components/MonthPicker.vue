<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import { monthPickerLabel, monthStart } from '../utils/agendaDates'

/**
 * Sélecteur de mois : année (de l'année d'aujourd'hui moins 1 à plus 1, l'horizon de blocage est d'un an)
 * et grille de 12 mois. `popover` = petit panneau ancré sous son parent ; `sheet` = panneau du bas
 * (téléphone). Émet `select` (premier jour du mois choisi) et `close` ; le parent gère focus et ouverture.
 */
const props = defineProps<{
  /** Mois affiché par l'agenda (AAAA-MM-JJ). */
  month: string
  /** Aujourd'hui (AAAA-MM-JJ). */
  today: string
  variant: 'popover' | 'sheet'
}>()

const emit = defineEmits<{
  select: [month: string]
  close: []
}>()

const minYear = computed(() => Number(props.today.slice(0, 4)) - 1)
const maxYear = computed(() => Number(props.today.slice(0, 4)) + 1)

function clampYear(year: number): number {
  return Math.min(maxYear.value, Math.max(minYear.value, year))
}

const year = ref(clampYear(Number(props.month.slice(0, 4))))

const months = computed(() =>
  Array.from({ length: 12 }, (_, i) => {
    const iso = `${year.value}-${String(i + 1).padStart(2, '0')}-01`
    return {
      iso,
      label: monthPickerLabel(i),
      shown: iso === monthStart(props.month),
      current: iso === monthStart(props.today),
    }
  }),
)

const dialogRef = ref<HTMLElement | null>(null)

function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape') {
    emit('close')
    return
  }
  if (e.key === 'Tab' && props.variant === 'sheet' && dialogRef.value) {
    const focusable = dialogRef.value.querySelectorAll<HTMLElement>('button:not([disabled])')
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (!first || !last) return
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

useDismissOnDeactivate(() => true, () => emit('close'))
onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  dialogRef.value?.querySelector<HTMLElement>('[data-autofocus]')?.focus()
})
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <Teleport to="body" :disabled="variant === 'popover'">
    <div
      :class="
        variant === 'sheet'
          ? 'fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center'
          : 'absolute left-0 top-full z-30 mt-2'
      "
      data-testid="month-picker-backdrop"
      @click.self="variant === 'sheet' && emit('close')"
    >
      <div
        ref="dialogRef"
        role="dialog"
        aria-label="Choisir un mois"
        :aria-modal="variant === 'sheet'"
        :class="
          variant === 'sheet'
            ? 'w-full max-w-md rounded-t-2xl bg-white px-5 pb-6 pt-2 shadow-xl sm:rounded-2xl sm:pt-5'
            : 'w-72 rounded-xl bg-white p-3 shadow-lg ring-1 ring-line'
        "
        data-testid="month-picker"
      >
        <div v-if="variant === 'sheet'" class="mx-auto mb-3 h-1 w-10 rounded-full bg-slate-300 sm:hidden" aria-hidden="true" />

        <div class="mb-3 flex items-center justify-between gap-2">
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="grid size-9 place-items-center rounded-full text-ink-2 hover:bg-sidebar disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Année précédente"
              :disabled="year <= minYear"
              data-testid="month-picker-prev-year"
              @click="year = clampYear(year - 1)"
            >
              <ChevronLeft class="size-5" aria-hidden="true" />
            </button>
            <span class="min-w-14 text-center text-[15px] font-semibold text-ink" data-testid="month-picker-year">{{
              year
            }}</span>
            <button
              type="button"
              class="grid size-9 place-items-center rounded-full text-ink-2 hover:bg-sidebar disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Année suivante"
              :disabled="year >= maxYear"
              data-testid="month-picker-next-year"
              @click="year = clampYear(year + 1)"
            >
              <ChevronRight class="size-5" aria-hidden="true" />
            </button>
          </div>
          <button
            type="button"
            class="grid size-9 shrink-0 place-items-center rounded-full text-ink-3 hover:bg-sidebar hover:text-ink"
            aria-label="Fermer"
            data-testid="month-picker-close"
            @click="emit('close')"
          >
            <X class="size-5" aria-hidden="true" />
          </button>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="item in months"
            :key="item.iso"
            type="button"
            class="min-h-11 rounded-lg text-[14px] font-medium"
            :class="[
              item.shown ? 'bg-slate-900 text-white' : 'text-ink hover:bg-sidebar',
              item.current && !item.shown ? 'ring-1 ring-inset ring-slate-400' : '',
            ]"
            :aria-pressed="item.shown"
            :aria-current="item.current ? 'date' : undefined"
            :data-autofocus="item.shown ? '' : undefined"
            :data-testid="`month-option-${item.iso}`"
            @click="emit('select', item.iso)"
          >
            {{ item.label }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

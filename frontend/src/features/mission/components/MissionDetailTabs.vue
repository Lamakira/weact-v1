<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

export interface MissionTab {
  id: string
  label: string
  /** Compteur affiché dans la pastille. */
  count?: number | null
  /** Pastille mise en avant (action attendue). */
  hot?: boolean
}

/**
 * Onglets de la page détail mission. L'indicateur d'encre glisse sous l'onglet actif
 * (transform + width seulement) ; flèches gauche/droite pour naviguer au clavier.
 */
const props = defineProps<{
  tabs: MissionTab[]
  modelValue: string
}>()

const emit = defineEmits<{
  'update:modelValue': [id: string]
}>()

const buttons = ref<Record<string, HTMLElement | null>>({})
const inkStyle = ref<{ transform: string; width: string }>({ transform: 'translateX(0px)', width: '0px' })
// Le premier placement ne s'anime pas : l'encre apparaît directement sous l'onglet actif.
const animateInk = ref(false)
let resizeObserver: ResizeObserver | null = null

function setButton(id: string, el: unknown): void {
  buttons.value[id] = (el as HTMLElement | null) ?? null
}

function moveInk(): void {
  const el = buttons.value[props.modelValue]
  if (!el) return
  inkStyle.value = { transform: `translateX(${el.offsetLeft}px)`, width: `${el.offsetWidth}px` }
}

function select(id: string): void {
  if (id !== props.modelValue) emit('update:modelValue', id)
}

function onKeydown(event: KeyboardEvent, index: number): void {
  if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return
  event.preventDefault()
  const delta = event.key === 'ArrowRight' ? 1 : -1
  const next = props.tabs[(index + delta + props.tabs.length) % props.tabs.length]
  if (!next) return
  select(next.id)
  void nextTick(() => buttons.value[next.id]?.focus())
}

watch(
  () => [props.modelValue, props.tabs.map((t) => t.id).join('|'), props.tabs.map((t) => t.count).join('|')],
  () => void nextTick(moveInk),
)

onMounted(() => {
  moveInk()
  // Active la transition après le premier placement.
  requestAnimationFrame(() => {
    animateInk.value = true
  })
  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => moveInk())
    for (const el of Object.values(buttons.value)) if (el) resizeObserver.observe(el)
  }
})

onBeforeUnmount(() => resizeObserver?.disconnect())
</script>

<template>
  <div class="tabs-bar relative flex gap-1 overflow-x-auto overflow-y-hidden border-b border-border px-2" role="tablist" aria-label="Sections de la mission">
    <button
      v-for="(tab, index) in tabs"
      :id="`mission-tab-${tab.id}`"
      :key="tab.id"
      :ref="(el) => setButton(tab.id, el)"
      type="button"
      role="tab"
      class="mission-tab inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-3 text-sm font-semibold transition-colors duration-150"
      :class="modelValue === tab.id ? 'text-foreground' : 'text-muted-foreground'"
      :aria-selected="modelValue === tab.id"
      :aria-controls="`mission-panel-${tab.id}`"
      :tabindex="modelValue === tab.id ? 0 : -1"
      :data-testid="`mission-tab-${tab.id}`"
      @click="select(tab.id)"
      @keydown="onKeydown($event, index)"
    >
      {{ tab.label }}
      <span
        v-if="tab.count !== null && tab.count !== undefined"
        class="rounded-full border px-1.5 text-[11px]"
        :class="
          tab.hot
            ? 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
            : 'border-border bg-muted text-muted-foreground'
        "
      >
        {{ tab.count }}
      </span>
    </button>
    <span class="tabs-ink" :class="{ 'tabs-ink-animated': animateInk }" :style="inkStyle" aria-hidden="true" />
  </div>
</template>

<style scoped>
/* Défilement horizontal possible sur téléphone, sans barre de défilement visible. */
.tabs-bar {
  scrollbar-width: none;
}
.tabs-bar::-webkit-scrollbar {
  display: none;
}
.tabs-ink {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 2px;
  border-radius: 2px;
  background: var(--primary);
}
.tabs-ink-animated {
  transition:
    transform 220ms cubic-bezier(0.77, 0, 0.175, 1),
    width 220ms cubic-bezier(0.77, 0, 0.175, 1);
}
@media (prefers-reduced-motion: reduce) {
  .tabs-ink-animated {
    transition: none;
  }
}
</style>

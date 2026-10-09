<script setup lang="ts">
/**
 * RPanel — panneau de la direction « Régie » : blanc, anneau 1 px, rayon 14 px,
 * aucune ombre portée. En-tête optionnel (titre + actions) et pied optionnel.
 */
import { useSlots, computed } from 'vue'

interface Props {
  title?: string
  /** Niveau de titre sémantique de l'en-tête (défaut h2) */
  headingTag?: 'h2' | 'h3' | 'h4'
}

withDefaults(defineProps<Props>(), {
  title: undefined,
  headingTag: 'h2',
})

const slots = useSlots()
const hasHeader = computed(() => Boolean(slots.title || slots.actions))
</script>

<template>
  <section class="rounded-panel bg-white ring-1 ring-line" data-testid="r-panel">
    <header
      v-if="title || hasHeader"
      class="flex min-h-12 items-center justify-between gap-3 border-b border-line px-4 py-2 sm:px-5"
      data-testid="r-panel-header"
    >
      <component :is="headingTag" class="text-[14px] font-semibold text-ink">
        <slot name="title">{{ title }}</slot>
      </component>
      <div v-if="$slots.actions" class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </header>

    <slot />

    <footer
      v-if="$slots.footer"
      class="border-t border-line px-4 py-3 sm:px-5"
      data-testid="r-panel-footer"
    >
      <slot name="footer" />
    </footer>
  </section>
</template>

<script setup lang="ts">
/**
 * RTodoList — file « À faire » (liste d'RTodoItem dans un RPanel).
 * `count` pilote l'affichage du compteur et de l'état vide.
 */
import RPanel from './RPanel.vue'

interface Props {
  title?: string
  count?: number
  /** Texte secondaire à droite de l'en-tête (ex. « Trié par urgence ») */
  hint?: string
  emptyText?: string
}

withDefaults(defineProps<Props>(), {
  title: 'À faire',
  count: undefined,
  hint: undefined,
  emptyText: 'Rien à faire pour le moment',
})
</script>

<template>
  <RPanel data-testid="r-todo-list">
    <template #title>
      {{ title }}
      <span v-if="count" class="font-normal text-ink-3" data-testid="r-todo-count">· {{ count }}</span>
    </template>
    <template v-if="hint" #actions>
      <span class="text-[12.5px] text-ink-3">{{ hint }}</span>
    </template>

    <ul v-if="count === undefined ? !!$slots.default : count !== 0" class="divide-y divide-line">
      <slot />
    </ul>
    <p v-else class="px-5 py-8 text-center text-dash text-ink-3" data-testid="r-todo-empty">
      {{ emptyText }}
    </p>
  </RPanel>
</template>

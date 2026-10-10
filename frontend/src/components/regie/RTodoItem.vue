<script setup lang="ts">
/**
 * RTodoItem — ligne de la file « À faire » : icône, titre, ligne de meta (avec
 * partie urgente en ambre), bouton d'action à droite.
 * Toute la ligne est cliquable via le titre (lien ou bouton étiré en ::after) ;
 * le bouton d'action reste un contrôle distinct, au-dessus.
 */
import type { Component } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { RouterLink } from 'vue-router'

interface Props {
  title: string
  meta?: string
  /** Partie urgente de la meta, affichée en ambre après `meta` */
  urgentMeta?: string
  icon?: Component
  actionLabel?: string
  actionVariant?: 'primary' | 'secondary'
  /** Si fourni, le titre est un lien ; sinon un bouton qui émet `select` */
  to?: RouteLocationRaw
}

withDefaults(defineProps<Props>(), {
  meta: undefined,
  urgentMeta: undefined,
  icon: undefined,
  actionLabel: undefined,
  actionVariant: 'secondary',
  to: undefined,
})

const emit = defineEmits<{
  select: []
  action: []
}>()

const titleClass =
  "truncate text-left text-dash font-semibold text-ink after:absolute after:inset-0 after:content-[''] focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-inset focus-visible:after:ring-weact-600"
</script>

<template>
  <li
    class="relative flex items-center gap-3 px-4 py-3 hover:bg-sidebar sm:px-5"
    data-testid="r-todo-item"
  >
    <span
      v-if="icon"
      class="grid size-8 shrink-0 place-items-center rounded-[9px]"
      :class="urgentMeta ? 'bg-urgent-soft text-urgent' : 'bg-sidebar text-ink-2'"
      aria-hidden="true"
    >
      <component :is="icon" class="size-4" />
    </span>

    <div class="min-w-0 flex-1">
      <p class="min-w-0">
        <RouterLink v-if="to" :to="to" :class="titleClass" class="block" @click="emit('select')">
          {{ title }}
        </RouterLink>
        <button v-else type="button" :class="titleClass" class="block max-w-full" @click="emit('select')">
          {{ title }}
        </button>
      </p>
      <p v-if="meta || urgentMeta" class="truncate text-[12.5px] text-ink-3" data-testid="r-todo-meta">
        <span v-if="meta">{{ meta }}</span>
        <span v-if="meta && urgentMeta"> · </span>
        <span v-if="urgentMeta" class="text-urgent" data-testid="r-todo-urgent">{{ urgentMeta }}</span>
      </p>
    </div>

    <button
      v-if="actionLabel"
      type="button"
      class="relative z-10 inline-flex h-8 shrink-0 items-center rounded-control px-3 text-[13px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 focus-visible:ring-offset-1 max-md:h-10"
      :class="
        actionVariant === 'primary'
          ? 'bg-weact-600 text-white hover:bg-weact-700'
          : 'bg-white text-ink ring-1 ring-line hover:bg-sidebar'
      "
      data-testid="r-todo-action"
      @click="emit('action')"
    >
      {{ actionLabel }}
    </button>
  </li>
</template>

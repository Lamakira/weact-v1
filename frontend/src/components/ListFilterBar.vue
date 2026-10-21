<script setup lang="ts" generic="T extends string">
import { computed } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

/**
 * Filtres réduits des listes (lot F4) : quelques pastilles visibles (Tous, À traiter, En cours,
 * Terminés) et un menu « Plus » pour les statuts rares. Le filtre choisi dans « Plus » s'affiche
 * dans le bouton (« Plus : Annulés »). Composant sans état : la valeur vient de l'URL de la page.
 */
export interface FilterOption<V extends string = string> {
  value: V
  label: string
  /** Nombre affiché dans une pastille (ex. « À traiter ») ; masqué quand il vaut 0. */
  count?: number
}

const props = defineProps<{
  modelValue: T
  primary: FilterOption<T>[]
  more: FilterOption<T>[]
}>()

const emit = defineEmits<{ 'update:modelValue': [value: T] }>()

const selectedMore = computed(() => props.more.find((option) => option.value === props.modelValue) ?? null)
const moreLabel = computed(() => (selectedMore.value ? `Plus : ${selectedMore.value.label}` : 'Plus'))

function select(value: T): void {
  emit('update:modelValue', value)
}
</script>

<template>
  <div
    class="filter-bar flex flex-nowrap items-center gap-2 overflow-x-auto pb-2 sm:flex-wrap sm:overflow-visible sm:pb-0 -mx-4 px-4 sm:mx-0 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    role="group"
    aria-label="Filtrer la liste"
  >
    <button
      v-for="option in primary"
      :key="option.value"
      type="button"
      class="filter-pill shrink-0 rounded-full px-4 py-1.5 text-sm font-medium"
      :class="
        modelValue === option.value
          ? 'bg-primary text-white'
          : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
      "
      :aria-pressed="modelValue === option.value"
      @click="select(option.value)"
    >
      {{ option.label }}
      <span
        v-if="(option.count ?? 0) > 0"
        class="ml-1.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1 text-[11px] font-semibold"
        :class="modelValue === option.value ? 'bg-white/25 text-white' : 'bg-primary text-white'"
        data-testid="to-process-count"
      >
        {{ option.count }}
      </span>
    </button>

    <DropdownMenu v-if="more.length > 0">
      <DropdownMenuTrigger as-child>
        <button
          type="button"
          class="filter-pill inline-flex shrink-0 items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium"
          :class="
            selectedMore
              ? 'bg-primary text-white'
              : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
          "
          :aria-pressed="selectedMore !== null"
          data-testid="filter-more-trigger"
        >
          {{ moreLabel }}
          <ChevronDown class="size-3.5" aria-hidden="true" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" class="filter-menu min-w-44" data-testid="filter-more-menu">
        <DropdownMenuItem
          v-for="option in more"
          :key="option.value"
          :data-testid="`filter-more-${option.value}`"
          :class="modelValue === option.value ? 'font-semibold text-foreground' : ''"
          @select="select(option.value)"
        >
          {{ option.label }}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>

<style scoped>
/* Appui franc (transform seul) et changement de fond bref ; supprimés si l'utilisateur réduit les animations. */
.filter-pill {
  transition:
    transform 160ms cubic-bezier(0.23, 1, 0.32, 1),
    background-color 160ms ease-out,
    color 160ms ease-out;
}
.filter-pill:active {
  transform: scale(0.97);
}
@media (prefers-reduced-motion: reduce) {
  .filter-pill {
    transition: none;
  }
  .filter-pill:active {
    transform: none;
  }
}
</style>

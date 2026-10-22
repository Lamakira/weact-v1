<script setup lang="ts">
import { computed, ref, type Component, type HTMLAttributes } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import Combobox from './Combobox.vue'
import ComboboxAnchor from './ComboboxAnchor.vue'
import ComboboxEmpty from './ComboboxEmpty.vue'
import ComboboxInput from './ComboboxInput.vue'
import ComboboxItem from './ComboboxItem.vue'
import ComboboxItemIndicator from './ComboboxItemIndicator.vue'
import ComboboxList from './ComboboxList.vue'
import ComboboxTrigger from './ComboboxTrigger.vue'
import ComboboxViewport from './ComboboxViewport.vue'

/**
 * Liste déroulante avec recherche (Combobox shadcn-vue), pour les longues listes (villes).
 * Même contrat que SimpleSelect : valeurs émises en chaîne, '' pour « aucune valeur »
 * (option `emptyLabel`), attributs portés par le déclencheur.
 */
defineOptions({ inheritAttrs: false })

// Reka n'accepte pas de valeur vide pour un item : sentinelle interne.
const EMPTY_SENTINEL = '__empty__'

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    options: ReadonlyArray<{ value: string | number; label: string }>
    placeholder?: string
    emptyLabel?: string
    searchPlaceholder?: string
    emptyText?: string
    icon?: Component
    disabled?: boolean
    triggerClass?: HTMLAttributes['class']
  }>(),
  {
    modelValue: '',
    placeholder: '',
    emptyLabel: '',
    searchPlaceholder: 'Rechercher…',
    emptyText: 'Aucun résultat',
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const open = ref(false)
const searchTerm = ref('')

const current = computed(() =>
  props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue),
)

const selectedLabel = computed(
  () => props.options.find((option) => String(option.value) === current.value)?.label ?? '',
)

function onUpdate(value: unknown): void {
  const next = String(value ?? '')
  emit('update:modelValue', next === EMPTY_SENTINEL ? '' : next)
  open.value = false
}

// Remet la recherche à zéro à chaque fermeture.
function onOpenChange(isOpen: boolean): void {
  open.value = isOpen
  if (!isOpen) searchTerm.value = ''
}
</script>

<template>
  <Combobox
    :model-value="current || undefined"
    :open="open"
    :disabled="disabled"
    @update:model-value="onUpdate"
    @update:open="onOpenChange"
  >
    <ComboboxAnchor class="w-full">
      <ComboboxTrigger
        v-bind="$attrs"
        :data-placeholder="selectedLabel ? undefined : ''"
        :class="cn('group flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 transition-[color,box-shadow] outline-none hover:border-primary/30 focus-visible:border-primary focus-visible:ring-3 focus-visible:ring-primary/25 data-[state=open]:border-primary data-[state=open]:ring-3 data-[state=open]:ring-primary/25 disabled:cursor-not-allowed disabled:opacity-50 data-[placeholder]:text-muted-foreground', triggerClass)"
      >
        <span class="flex min-w-0 flex-1 items-center gap-2">
          <component :is="icon" v-if="icon" aria-hidden="true" class="size-4 shrink-0 text-gray-400" />
          <span class="block truncate text-left">{{ selectedLabel || placeholder }}</span>
        </span>
        <ChevronDown
          aria-hidden="true"
          class="size-4 shrink-0 opacity-50 transition-transform duration-200 ease-out group-data-[state=open]:rotate-180 motion-reduce:transition-none"
        />
      </ComboboxTrigger>
    </ComboboxAnchor>
    <ComboboxList>
      <ComboboxInput v-model="searchTerm" :placeholder="searchPlaceholder" :aria-label="searchPlaceholder" />
      <ComboboxViewport class="p-1">
        <ComboboxEmpty>{{ emptyText }}</ComboboxEmpty>
        <ComboboxItem v-if="emptyLabel" :value="EMPTY_SENTINEL" class="text-gray-500">
          {{ emptyLabel }}
        </ComboboxItem>
        <ComboboxItem v-for="option in options" :key="option.value" :value="String(option.value)">
          <ComboboxItemIndicator>
            <Check class="size-4" />
          </ComboboxItemIndicator>
          {{ option.label }}
        </ComboboxItem>
      </ComboboxViewport>
    </ComboboxList>
  </Combobox>
</template>

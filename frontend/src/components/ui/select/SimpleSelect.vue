<script setup lang="ts">
import { computed, type Component, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import Select from './Select.vue'
import SelectContent from './SelectContent.vue'
import SelectItem from './SelectItem.vue'
import SelectTrigger from './SelectTrigger.vue'
import SelectValue from './SelectValue.vue'

/**
 * Liste déroulante WeAct basée sur le Select shadcn-vue.
 * - `emptyLabel` ajoute une première option (« Toutes les… ») qui émet '' : retour à « aucun filtre ».
 * - Les valeurs émises sont toujours des chaînes (comme un <select> natif).
 * - Les attributs (data-testid, aria-label, id…) sont portés par le déclencheur.
 */
defineOptions({ inheritAttrs: false })

// Reka interdit une valeur d'option vide : sentinelle interne pour « aucune valeur ».
const EMPTY_SENTINEL = '__empty__'

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    options: ReadonlyArray<{ value: string | number; label: string }>
    placeholder?: string
    emptyLabel?: string
    icon?: Component
    disabled?: boolean
    required?: boolean
    triggerClass?: HTMLAttributes['class']
    contentClass?: HTMLAttributes['class']
  }>(),
  {
    modelValue: '',
    placeholder: '',
    emptyLabel: '',
    disabled: false,
    required: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const current = computed(() =>
  props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue),
)

// Libellé affiché : calculé ici (Reka ne le connaît qu'une fois la liste montée).
const selectedLabel = computed(
  () => props.options.find((option) => String(option.value) === current.value)?.label ?? '',
)

function onUpdate(value: unknown): void {
  const next = String(value ?? '')
  emit('update:modelValue', next === EMPTY_SENTINEL ? '' : next)
}
</script>

<template>
  <Select
    :model-value="current"
    :disabled="disabled"
    :required="required"
    @update:model-value="onUpdate"
  >
    <SelectTrigger
      v-bind="$attrs"
      :class="cn('h-10 data-[size=default]:h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-700 shadow-none hover:border-primary/30', triggerClass)"
    >
      <span class="flex min-w-0 flex-1 items-center gap-2">
        <component :is="icon" v-if="icon" aria-hidden="true" class="size-4 shrink-0 text-gray-400" />
        <SelectValue :placeholder="placeholder" class="block truncate text-left">
          {{ selectedLabel || placeholder || emptyLabel }}
        </SelectValue>
      </span>
    </SelectTrigger>
    <SelectContent :class="contentClass">
      <SelectItem v-if="emptyLabel" :value="EMPTY_SENTINEL" class="text-gray-500">
        {{ emptyLabel }}
      </SelectItem>
      <SelectItem v-for="option in options" :key="option.value" :value="String(option.value)">
        {{ option.label }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue'
import { SimpleSelect } from '@/components/ui/select'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue: string | number
    label: string
    icon?: Component
    error?: string
    id: string
    required?: boolean
    disabled?: boolean
    options: Array<{ value: string | number; label: string }>
    placeholder?: string
  }>(),
  {
    required: false,
    disabled: false,
    placeholder: '',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()

const hasValue = computed(() => {
  return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined
})

// Sans placeholder, la première option « Sélectionnez » (valeur vide) reste choisissable, comme avant.
const emptyLabel = computed(() => (props.placeholder ? '' : 'Sélectionnez'))

// Hauteur, bordure et anneau de focus identiques aux autres champs flottants ; rouge en cas d'erreur.
const triggerClass = computed(() => [
  'peer h-[46px] data-[size=default]:h-[46px] py-3 pr-3 text-gray-900 duration-200 disabled:bg-gray-50',
  'data-[placeholder]:text-transparent focus-visible:data-[placeholder]:text-gray-400 data-[state=open]:data-[placeholder]:text-gray-400',
  props.icon ? 'pl-10' : 'pl-4',
  props.error
    ? 'border-red-300 focus-visible:border-red-500 focus-visible:ring-red-200 data-[state=open]:border-red-500 data-[state=open]:ring-red-200'
    : 'border-gray-300 focus-visible:border-weact focus-visible:ring-weact/20 data-[state=open]:border-weact data-[state=open]:ring-weact/20',
])
</script>

<template>
  <div>
    <div class="relative">
      <!-- Le Select shadcn remplace l'ancien <select> ; l'étiquette flottante réagit au focus et à l'ouverture -->
      <SimpleSelect
        v-bind="$attrs"
        :id="id"
        :model-value="modelValue"
        :options="options"
        :empty-label="emptyLabel"
        :disabled="disabled"
        :trigger-class="triggerClass"
        @update:model-value="emit('update:modelValue', $event)"
      />

      <!-- Icon -->
      <component
        v-if="icon"
        :is="icon"
        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 transition-colors duration-200 pointer-events-none"
        :class="error ? 'text-red-400' : 'text-gray-400 peer-focus:text-weact peer-data-[state=open]:text-weact'"
      />

      <!-- Floating Label -->
      <label
        :for="id"
        class="absolute text-gray-400 transition-all duration-200 pointer-events-none bg-white px-1"
        :class="[
          icon ? 'left-9' : 'left-3',
          error
            ? 'peer-focus:text-red-500 peer-data-[state=open]:text-red-500'
            : 'peer-focus:text-weact peer-data-[state=open]:text-weact',
          hasValue
            ? 'top-0 text-xs -translate-y-1/2 text-gray-500'
            : 'top-1/2 -translate-y-1/2 text-sm',
          'peer-focus:top-0 peer-focus:text-xs peer-focus:-translate-y-1/2 peer-data-[state=open]:top-0 peer-data-[state=open]:text-xs peer-data-[state=open]:-translate-y-1/2',
          hasValue && !error ? 'text-gray-500' : '',
        ]"
      >
        {{ label }}<span v-if="required" class="text-red-500 ml-0.5">*</span>
      </label>
    </div>

    <!-- Error Message -->
    <p v-if="error" :id="`${id}-error`" :data-testid="`${id}-error`" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
  </div>
</template>

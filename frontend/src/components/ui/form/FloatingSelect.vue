<script setup lang="ts">
import { computed, type Component } from 'vue'

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

const onChange = (event: Event) => {
  const target = event.target as HTMLSelectElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div>
    <div class="relative">
      <select
        :id="id"
        :value="modelValue"
        @change="onChange"
        :required="required"
        :disabled="disabled"
        v-bind="$attrs"
        class="peer w-full rounded-lg border bg-white text-gray-900 transition-all duration-200 outline-none appearance-none disabled:bg-gray-50 disabled:cursor-not-allowed"
        :class="[
          icon ? 'pl-10 pr-10' : 'pl-4 pr-10',
          error
            ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-200'
            : 'border-gray-300 focus:border-weact focus:ring-2 focus:ring-weact/20',
          'py-3 text-sm',
          !hasValue ? 'text-gray-400' : '',
        ]"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option v-else value="">{{ `Sélectionnez` }}</option>
        <option
          v-for="option in options"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>

      <!-- Icon -->
      <component
        v-if="icon"
        :is="icon"
        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 transition-colors duration-200 pointer-events-none"
        :class="error ? 'text-red-400' : 'text-gray-400 peer-focus:text-weact'"
      />

      <!-- Chevron icon -->
      <svg
        class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
      </svg>

      <!-- Floating Label -->
      <label
        :for="id"
        class="absolute text-gray-400 transition-all duration-200 pointer-events-none bg-white px-1"
        :class="[
          icon ? 'left-9' : 'left-3',
          error
            ? 'peer-focus:text-red-500'
            : 'peer-focus:text-weact',
          hasValue
            ? 'top-0 text-xs -translate-y-1/2 text-gray-500'
            : 'top-1/2 -translate-y-1/2 text-sm',
          'peer-focus:top-0 peer-focus:text-xs peer-focus:-translate-y-1/2',
          hasValue && !error ? 'text-gray-500' : '',
        ]"
      >
        {{ label }}<span v-if="required" class="text-red-500 ml-0.5">*</span>
      </label>
    </div>

    <!-- Error Message -->
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
  </div>
</template>

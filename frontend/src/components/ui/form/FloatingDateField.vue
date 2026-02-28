<script setup lang="ts">
import { computed, type Component } from 'vue'
import { CalendarDays } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label: string
    icon?: Component
    error?: string
    id: string
    required?: boolean
    disabled?: boolean
    min?: string
    max?: string
  }>(),
  {
    required: false,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

/**
 * Format ISO date (YYYY-MM-DD) to dd/mm/yyyy for display
 */
const formattedDate = computed(() => {
  if (!props.modelValue) return ''
  const [year, month, day] = props.modelValue.split('-')
  if (!year || !month || !day) return ''
  return `${day}/${month}/${year}`
})

const hasValue = computed(() => props.modelValue !== '' && props.modelValue != null)

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}

function onClick(event: Event): void {
  const target = event.target as HTMLInputElement
  try {
    target.showPicker()
  } catch {
    // Fallback: some browsers don't support showPicker()
  }
}
</script>

<template>
  <div>
    <div class="relative">
      <!-- Native date input (fully invisible but clickable for calendar popup) -->
      <input
        :id="id"
        type="date"
        :value="modelValue"
        :required="required"
        :disabled="disabled"
        :min="min"
        :max="max"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="error ? `${id}-error` : undefined"
        class="peer absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10 disabled:cursor-not-allowed"
        @input="onInput"
        @click="onClick"
      />

      <!-- Visible styled display -->
      <div
        class="w-full rounded-lg border bg-white text-gray-900 transition-all duration-200 pointer-events-none"
        :class="[
          icon ? 'pl-10 pr-10' : 'pl-4 pr-10',
          error
            ? 'border-red-300 peer-focus:border-red-500 peer-focus:ring-2 peer-focus:ring-red-200'
            : 'border-gray-300 peer-focus:border-weact peer-focus:ring-2 peer-focus:ring-weact/20',
          disabled ? 'bg-gray-50' : '',
          'py-3 text-sm',
        ]"
      >
        <span :class="hasValue ? 'text-gray-900' : 'text-transparent'">
          {{ hasValue ? formattedDate : 'jj/mm/aaaa' }}
        </span>
      </div>

      <!-- Icon -->
      <component
        v-if="icon"
        :is="icon"
        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 transition-colors duration-200 pointer-events-none z-20"
        :class="error ? 'text-red-400' : 'text-gray-400 peer-focus:text-weact'"
      />

      <!-- Calendar picker icon (visible, clicks pass through to native input) -->
      <CalendarDays
        class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none z-20"
      />

      <!-- Floating Label -->
      <label
        :for="id"
        class="absolute transition-all duration-200 pointer-events-none bg-white px-1 z-20"
        :class="[
          icon ? 'left-9' : 'left-3',
          error
            ? 'peer-focus:text-red-500'
            : 'peer-focus:text-weact',
          'peer-focus:top-0 peer-focus:text-xs peer-focus:-translate-y-1/2',
          hasValue
            ? 'top-0 text-xs -translate-y-1/2 text-gray-500'
            : 'top-1/2 -translate-y-1/2 text-sm text-gray-400',
          hasValue && error ? 'text-red-500' : '',
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

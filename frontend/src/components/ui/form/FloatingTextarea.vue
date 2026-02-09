<script setup lang="ts">
import { computed, type Component } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue: string
    label: string
    icon?: Component
    error?: string
    id: string
    required?: boolean
    disabled?: boolean
    rows?: number
    maxlength?: number
  }>(),
  {
    required: false,
    disabled: false,
    rows: 4,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const hasValue = computed(() => {
  return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined
})

const onInput = (event: Event) => {
  const target = event.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div>
    <div class="relative">
      <textarea
        :id="id"
        :value="modelValue"
        @input="onInput"
        :required="required"
        :disabled="disabled"
        :rows="rows"
        :maxlength="maxlength"
        placeholder=" "
        v-bind="$attrs"
        class="peer w-full rounded-lg border bg-white text-gray-900 transition-all duration-200 outline-none resize-none disabled:bg-gray-50 disabled:cursor-not-allowed"
        :class="[
          icon ? 'pl-10 pr-4' : 'pl-4 pr-4',
          error
            ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-200'
            : 'border-gray-300 focus:border-weact focus:ring-2 focus:ring-weact/20',
          'py-3 pt-5 text-sm',
        ]"
      ></textarea>

      <!-- Icon -->
      <component
        v-if="icon"
        :is="icon"
        class="absolute left-3 top-5 w-4 h-4 transition-colors duration-200 pointer-events-none"
        :class="error ? 'text-red-400' : 'text-gray-400 peer-focus:text-weact'"
      />

      <!-- Floating Label -->
      <label
        :for="id"
        class="absolute transition-all duration-200 pointer-events-none bg-white px-1"
        :class="[
          icon ? 'left-9' : 'left-3',
          error
            ? 'peer-focus:text-red-500'
            : 'peer-focus:text-weact',
          'peer-focus:top-0 peer-focus:text-xs peer-focus:-translate-y-1/2',
          hasValue
            ? 'top-0 text-xs -translate-y-1/2 text-gray-500'
            : 'top-4 text-sm text-gray-400',
          hasValue && error ? 'text-red-500' : '',
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

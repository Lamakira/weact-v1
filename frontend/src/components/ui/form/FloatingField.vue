<script setup lang="ts">
import { ref, computed, type Component } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue: string | number
    label: string
    icon?: Component
    type?: string
    error?: string
    id: string
    required?: boolean
    autocomplete?: string
    disabled?: boolean
    passwordToggle?: boolean
  }>(),
  {
    type: 'text',
    required: false,
    disabled: false,
    passwordToggle: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()

const showPassword = ref(false)

const actualType = computed(() => {
  if (props.passwordToggle && showPassword.value) return 'text'
  return props.type
})

const hasValue = computed(() => {
  return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined
})

const onInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (props.type === 'number') {
    emit('update:modelValue', target.value === '' ? '' : Number(target.value))
  } else {
    emit('update:modelValue', target.value)
  }
}
</script>

<template>
  <div>
    <div class="relative">
      <input
        :id="id"
        :type="actualType"
        :value="modelValue"
        @input="onInput"
        :required="required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        placeholder=" "
        v-bind="$attrs"
        class="peer w-full rounded-lg border bg-white text-gray-900 transition-all duration-200 outline-none disabled:bg-gray-50 disabled:cursor-not-allowed"
        :class="[
          icon ? 'pl-10 pr-4' : 'pl-4 pr-4',
          passwordToggle ? 'pr-12' : '',
          error
            ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-200'
            : 'border-gray-300 focus:border-weact focus:ring-2 focus:ring-weact/20',
          'py-3 text-sm',
        ]"
      />

      <!-- Icon -->
      <component
        v-if="icon"
        :is="icon"
        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 transition-colors duration-200 pointer-events-none"
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
            : 'top-1/2 -translate-y-1/2 text-sm text-gray-400',
          hasValue && error ? 'text-red-500' : '',
        ]"
      >
        {{ label }}<span v-if="required" class="text-red-500 ml-0.5">*</span>
      </label>

      <!-- Password Toggle -->
      <button
        v-if="passwordToggle"
        type="button"
        @click="showPassword = !showPassword"
        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
        :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
        tabindex="-1"
      >
        <Eye v-if="!showPassword" class="w-5 h-5" />
        <EyeOff v-else class="w-5 h-5" />
      </button>
    </div>

    <!-- Error Message -->
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
  </div>
</template>

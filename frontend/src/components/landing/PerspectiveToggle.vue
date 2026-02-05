<script setup lang="ts">
import type { Perspective } from './types'

interface Props {
  modelValue: Perspective
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [value: Perspective]
}>()

const options: { value: Perspective; label: string }[] = [
  { value: 'face', label: 'Face' },
  { value: 'producer', label: 'Producteur' },
]

function selectOption(value: Perspective): void {
  if (value !== props.modelValue) {
    emit('update:modelValue', value)
  }
}

function handleKeydown(event: KeyboardEvent, currentValue: Perspective): void {
  const currentIndex = options.findIndex((opt) => opt.value === currentValue)
  let newIndex = currentIndex

  if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
    event.preventDefault()
    newIndex = (currentIndex + 1) % options.length
  } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
    event.preventDefault()
    newIndex = (currentIndex - 1 + options.length) % options.length
  } else if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    return // Current option is already selected
  }

  if (newIndex !== currentIndex) {
    const option = options[newIndex]
    if (option) {
      emit('update:modelValue', option.value)
    }
  }
}
</script>

<template>
  <div class="flex flex-col items-center" data-testid="perspective-toggle">
    <!-- Centered Label -->
    <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-1">Je suis :</span>

    <!-- Curved Arrows pointing DOWN (between label and toggle) -->
    <div class="flex justify-center gap-12 h-6 mb-1">
      <!-- Left curved arrow (Face) -->
      <svg
        class="w-5 h-5 transition-colors duration-300"
        :class="modelValue === 'face' ? 'text-[#198496]' : 'text-slate-300'"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="M16 2C12 2 6 6 6 18M6 18L2 14M6 18L10 14"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>

      <!-- Right curved arrow (Producer) -->
      <svg
        class="w-5 h-5 transition-colors duration-300"
        :class="modelValue === 'producer' ? 'text-[#198496]' : 'text-slate-300'"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="M8 2C12 2 18 6 18 18M18 18L14 14M18 18L22 14"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </div>

    <!-- Toggle buttons below -->
    <div
      role="tablist"
      aria-label="Choisir votre profil"
      class="inline-flex bg-slate-100 rounded-full p-1 border border-slate-200"
    >
      <button
        v-for="option in options"
        :key="option.value"
        :data-testid="`toggle-${option.value}`"
        role="tab"
        :aria-selected="modelValue === option.value"
        :tabindex="modelValue === option.value ? 0 : -1"
        :class="[
          'px-5 py-2 text-sm font-semibold rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-1',
          modelValue === option.value
            ? 'bg-[#198496] text-white shadow-md'
            : 'text-slate-500 hover:text-slate-700 hover:bg-white/50',
        ]"
        @click="selectOption(option.value)"
        @keydown="handleKeydown($event, option.value)"
      >
        {{ option.label }}
      </button>
    </div>
  </div>
</template>

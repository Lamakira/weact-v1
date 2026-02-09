<script setup lang="ts">
import type { Perspective } from './types'

interface Props {
  modelValue: Perspective
  compact?: boolean
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
    <!-- Centered Label (hidden in compact mode) -->
    <span v-show="!compact" class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest mb-0">Je suis :</span>


    <!-- Toggle buttons -->
    <div
      role="tablist"
      aria-label="Choisir votre profil"
      :class="[
        'inline-flex rounded-full transition-all duration-200',
        compact
          ? 'bg-white p-0.5 border border-gray-200 shadow-md'
          : 'bg-white p-0.5 border border-gray-200',
      ]"
    >
      <button
        v-for="option in options"
        :key="option.value"
        :data-testid="`toggle-${option.value}`"
        role="tab"
        :aria-selected="modelValue === option.value"
        :tabindex="modelValue === option.value ? 0 : -1"
        :class="[
          'font-semibold rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-1',
          'px-4 py-1 text-xs',
          modelValue === option.value
            ? 'bg-black text-white shadow-md'
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

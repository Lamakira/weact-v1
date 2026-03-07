<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const modelValue = defineModel<string>({ default: '' })

function onInput(event: Event): void {
  const target = event.target as HTMLTextAreaElement
  modelValue.value = target.value
}
</script>

<template>
  <textarea
    :value="modelValue"
    @input="onInput"
    v-bind="$attrs"
    :class="cn(
      'w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-weact focus:ring-2 focus:ring-weact/20 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:opacity-60',
      props.class,
    )"
  />
</template>

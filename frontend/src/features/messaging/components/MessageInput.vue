<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { Send, Loader2 } from 'lucide-vue-next'

interface Props {
  modelValue: string
  isLoading?: boolean
  error?: string | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  isLoading: false,
  error: null,
  placeholder: 'Écrivez votre message...',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'submit', value: string): void
}>()

const textareaRef = ref<HTMLTextAreaElement | null>(null)

const adjustHeight = () => {
  const textarea = textareaRef.value
  if (!textarea) return

  textarea.style.height = 'auto'
  textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`
}

const handleInput = (e: Event) => {
  const target = e.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)
  adjustHeight()
}

const handleSubmit = () => {
  if (props.isLoading || !props.modelValue.trim()) return
  emit('submit', props.modelValue.trim())
}

const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSubmit()
  }
}

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal === '') {
      // Reset height when cleared from parent
      setTimeout(adjustHeight, 0)
    }
  },
)

onMounted(() => {
  adjustHeight()
})
</script>

<template>
  <div class="sticky bottom-0 w-full bg-background border-t border-border p-4 sm:px-6">
    <div class="mx-auto max-w-4xl">
      <div class="flex items-end gap-2">
        <div class="relative flex-1">
          <textarea
            ref="textareaRef"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="isLoading"
            rows="1"
            class="w-full resize-none rounded-xl border border-input bg-background px-4 py-3 text-sm ring-ring transition-shadow focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
            @input="handleInput"
            @keydown="onKeydown"
          />
        </div>

        <button
          type="button"
          :disabled="isLoading || !modelValue.trim()"
          class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm transition-all hover:opacity-90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          @click="handleSubmit"
        >
          <Loader2 v-if="isLoading" class="h-5 w-5 animate-spin" />
          <Send v-else class="h-5 w-5 translate-x-0.5" />
          <span class="sr-only">Envoyer</span>
        </button>
      </div>

      <!-- Error State -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform -translate-y-2 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform -translate-y-2 opacity-0"
      >
        <p v-if="error" class="mt-2 text-xs font-medium text-destructive">
          {{ error }}
        </p>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
/* Ensure the textarea doesn't show scrollbar until max-height reached */
textarea {
  scrollbar-width: thin;
  scrollbar-color: var(--color-border) transparent;
}
textarea::-webkit-scrollbar {
  width: 4px;
}
textarea::-webkit-scrollbar-thumb {
  background-color: var(--color-border);
  border-radius: 10px;
}
</style>

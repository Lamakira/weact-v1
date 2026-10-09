<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { Send, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

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

// Entrée envoie, Maj+Entrée insère un retour à la ligne
const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
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
  <div
    class="shrink-0 border-t border-line bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
    data-testid="message-input"
  >
    <div class="rounded-[12px] ring-1 ring-line focus-within:ring-2 focus-within:ring-weact-600">
      <textarea
        ref="textareaRef"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="isLoading"
        :aria-label="placeholder"
        rows="1"
        class="block max-h-40 min-h-[44px] w-full resize-none rounded-t-[12px] bg-transparent px-3 pb-1 pt-2.5 text-[14px] text-ink placeholder:text-ink-3 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        @input="handleInput"
        @keydown="onKeydown"
      />
      <div class="flex items-center justify-end gap-3 px-2 pb-2">
        <span class="hidden text-[11.5px] text-ink-3 lg:inline">
          Entrée pour envoyer · Maj+Entrée pour un retour à la ligne
        </span>
        <Button
          type="button"
          variant="regie"
          size="regie"
          :disabled="isLoading || !modelValue.trim()"
          data-testid="message-send"
          @click="handleSubmit"
        >
          <Loader2 v-if="isLoading" class="animate-spin" />
          <Send v-else />
          Envoyer
        </Button>
      </div>
    </div>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform -translate-y-2 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform -translate-y-2 opacity-0"
    >
      <p v-if="error" class="mt-2 text-xs font-medium text-destructive" role="alert">
        {{ error }}
      </p>
    </Transition>
  </div>
</template>

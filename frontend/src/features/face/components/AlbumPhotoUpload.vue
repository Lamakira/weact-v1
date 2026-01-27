<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  isFull?: boolean
  isUploading?: boolean
  error?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  isFull: false,
  isUploading: false,
  error: null,
})

const emit = defineEmits<{
  upload: [file: File]
}>()

// File input ref
const fileInputRef = ref<HTMLInputElement | null>(null)

// Drag state
const isDragging = ref(false)

/**
 * Trigger file input click
 */
function triggerFileInput(): void {
  if (!props.isFull && !props.isUploading) {
    fileInputRef.value?.click()
  }
}

/**
 * Handle file selection from input
 */
function handleFileSelect(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (file) {
    emit('upload', file)
  }

  // Reset input to allow selecting the same file again
  input.value = ''
}

/**
 * Handle file drop
 */
function handleDrop(event: DragEvent): void {
  isDragging.value = false

  if (props.isFull || props.isUploading) return

  const file = event.dataTransfer?.files?.[0]
  if (file) {
    emit('upload', file)
  }
}

/**
 * Handle drag events
 */
function handleDragOver(event: DragEvent): void {
  if (props.isFull || props.isUploading) return
  event.preventDefault()
  isDragging.value = true
}

function handleDragLeave(): void {
  isDragging.value = false
}
</script>

<template>
  <div class="album-photo-upload" data-testid="album-photo-upload">
    <!-- Error message -->
    <div
      v-if="error"
      class="mb-4 rounded-md bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="album-error"
    >
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Upload area -->
    <div
      class="border-2 border-dashed rounded-lg p-6 text-center transition-all"
      :class="[
        isDragging && !isFull ? 'border-teal-500 bg-teal-50' : 'border-gray-300',
        isFull ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-teal-400',
      ]"
      @click="triggerFileInput"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @drop.prevent="handleDrop"
      data-testid="upload-area"
    >
      <!-- Upload icon -->
      <div class="flex flex-col items-center gap-2">
        <div
          v-if="isUploading"
          class="flex items-center justify-center"
          data-testid="uploading-spinner"
        >
          <svg
            class="animate-spin h-8 w-8 text-teal-600"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            ></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
        </div>

        <template v-else>
          <svg
            class="w-10 h-10"
            :class="isFull ? 'text-gray-300' : 'text-gray-400'"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
            />
          </svg>

          <p class="text-sm" :class="isFull ? 'text-gray-400' : 'text-gray-600'">
            <template v-if="isFull"> Album complet (4 photos maximum) </template>
            <template v-else>
              <span class="font-medium text-teal-600">Cliquez pour ajouter</span>
              ou glissez une photo ici
            </template>
          </p>
        </template>
      </div>

      <!-- Help text -->
      <p class="mt-2 text-xs text-gray-500">Format JPG ou PNG. Taille max: 5 Mo</p>
    </div>

    <!-- Hidden file input -->
    <input
      ref="fileInputRef"
      type="file"
      accept="image/jpeg,image/png"
      class="hidden"
      :disabled="isFull || isUploading"
      @change="handleFileSelect"
      data-testid="file-input"
    />
  </div>
</template>

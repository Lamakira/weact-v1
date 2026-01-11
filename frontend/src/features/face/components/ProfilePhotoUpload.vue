<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { FaceProfile } from '../types'

interface Props {
  profile: FaceProfile | null
  isUploading?: boolean
  isDeleting?: boolean
  error?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  isUploading: false,
  isDeleting: false,
  error: null,
})

const emit = defineEmits<{
  upload: [file: File]
  delete: []
}>()

// File input ref
const fileInputRef = ref<HTMLInputElement | null>(null)

// Drag state
const isDragging = ref(false)

// Preview URL for newly selected file
const previewUrl = ref<string | null>(null)

// Computed properties
const displayUrl = computed(() => {
  if (previewUrl.value) return previewUrl.value
  return props.profile?.profile_photo_url ?? null
})

const hasPhoto = computed(() => !!displayUrl.value)

const isProcessing = computed(() => props.isUploading || props.isDeleting)

// Clear preview when profile updates (after successful upload)
watch(
  () => props.profile?.profile_photo_url,
  () => {
    if (previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
    }
  },
)

// Clear preview when an error occurs (validation failed or upload failed)
watch(
  () => props.error,
  (newError) => {
    if (newError && previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
    }
  },
)

/**
 * Trigger file input click
 */
function triggerFileInput(): void {
  fileInputRef.value?.click()
}

/**
 * Handle file selection from input
 */
function handleFileSelect(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (file) {
    processFile(file)
  }

  // Reset input to allow selecting the same file again
  input.value = ''
}

/**
 * Handle file drop
 */
function handleDrop(event: DragEvent): void {
  isDragging.value = false

  const file = event.dataTransfer?.files?.[0]
  if (file) {
    processFile(file)
  }
}

/**
 * Process the selected file
 */
function processFile(file: File): void {
  // Create preview URL
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }
  previewUrl.value = URL.createObjectURL(file)

  // Emit upload event
  emit('upload', file)
}

/**
 * Handle drag events
 */
function handleDragOver(event: DragEvent): void {
  event.preventDefault()
  isDragging.value = true
}

function handleDragLeave(): void {
  isDragging.value = false
}

/**
 * Handle delete
 */
function handleDelete(): void {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
  emit('delete')
}
</script>

<template>
  <div class="profile-photo-upload" data-testid="profile-photo-upload">
    <!-- Error message -->
    <div
      v-if="error"
      class="mb-4 rounded-md bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="upload-error"
    >
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Photo display area -->
    <div class="flex flex-col items-center gap-4">
      <!-- Avatar container -->
      <div
        class="relative"
        :class="{ 'cursor-pointer': !isProcessing }"
        @click="!isProcessing && triggerFileInput()"
      >
        <!-- Current photo or placeholder -->
        <div
          class="w-32 h-32 rounded-full overflow-hidden border-4 transition-all"
          :class="[
            isDragging ? 'border-teal-500 ring-4 ring-teal-200' : 'border-gray-200',
            isProcessing ? 'opacity-50' : '',
          ]"
          @dragover="handleDragOver"
          @dragleave="handleDragLeave"
          @drop.prevent="handleDrop"
        >
          <!-- Photo -->
          <img
            v-if="displayUrl"
            :src="displayUrl"
            alt="Photo de profil"
            class="w-full h-full object-cover"
            data-testid="profile-photo"
          />

          <!-- Placeholder -->
          <div
            v-else
            class="w-full h-full bg-gray-100 flex items-center justify-center"
            data-testid="photo-placeholder"
          >
            <svg
              class="w-12 h-12 text-gray-400"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
              />
            </svg>
          </div>
        </div>

        <!-- Loading overlay -->
        <div
          v-if="isProcessing"
          class="absolute inset-0 flex items-center justify-center bg-white/50 rounded-full"
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

        <!-- Camera icon overlay (visible on hover when not processing) -->
        <div
          v-if="!isProcessing"
          class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 hover:opacity-100 transition-opacity"
        >
          <svg
            class="w-8 h-8 text-white"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"
            />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"
            />
          </svg>
        </div>
      </div>

      <!-- Hidden file input -->
      <input
        ref="fileInputRef"
        type="file"
        accept="image/jpeg,image/png"
        class="hidden"
        @change="handleFileSelect"
        data-testid="file-input"
      />

      <!-- Action buttons -->
      <div class="flex gap-3">
        <!-- Upload/Change button -->
        <button
          type="button"
          :disabled="isProcessing"
          class="px-4 py-2 text-sm font-medium text-white bg-teal-600 rounded-lg hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          @click="triggerFileInput"
          data-testid="upload-button"
        >
          {{ hasPhoto ? 'Modifier' : 'Ajouter une photo' }}
        </button>

        <!-- Delete button (only show if has photo) -->
        <button
          v-if="hasPhoto && !previewUrl"
          type="button"
          :disabled="isProcessing"
          class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          @click="handleDelete"
          data-testid="delete-button"
        >
          Supprimer
        </button>
      </div>

      <!-- Help text -->
      <p class="text-xs text-gray-500 text-center">
        Format JPG ou PNG. Taille max: 5 Mo
      </p>
    </div>
  </div>
</template>

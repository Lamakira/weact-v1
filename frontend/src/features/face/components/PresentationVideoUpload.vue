<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { PresentationVideoInfo, VideoUploadProgress } from '../types'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

interface Props {
  videoInfo: PresentationVideoInfo | null
  isUploading?: boolean
  isDeleting?: boolean
  error?: string | null
  uploadProgress?: VideoUploadProgress | null
}

const props = withDefaults(defineProps<Props>(), {
  isUploading: false,
  isDeleting: false,
  error: null,
  uploadProgress: null,
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

// Delete confirmation modal state
const showDeleteModal = ref(false)

// Computed properties
const displayThumbnail = computed(() => {
  if (previewUrl.value) return null // Show video preview instead
  return props.videoInfo?.presentation_video_thumbnail_url ?? null
})

const displayVideoUrl = computed(() => {
  return props.videoInfo?.presentation_video_url ?? null
})

const hasVideo = computed(() => !!displayVideoUrl.value || !!previewUrl.value)

const isProcessing = computed(() => props.isUploading || props.isDeleting)

const progressPercentage = computed(() => props.uploadProgress?.percentage ?? 0)

// Clear preview when videoInfo updates (after successful upload)
watch(
  () => props.videoInfo?.presentation_video_url,
  () => {
    if (previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
    }
  },
)

// Clear preview when an error occurs
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
 * Open delete confirmation modal
 */
function handleDelete(): void {
  showDeleteModal.value = true
}

/**
 * Confirm deletion after modal confirmation
 */
function confirmDelete(): void {
  showDeleteModal.value = false

  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
  emit('delete')
}

/**
 * Cancel deletion
 */
function cancelDelete(): void {
  showDeleteModal.value = false
}
</script>

<template>
  <div class="presentation-video-upload" data-testid="presentation-video-upload">
    <!-- Error message -->
    <div
      v-if="error"
      class="mb-4 rounded-md bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="upload-error"
    >
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Video display area -->
    <div class="flex flex-col items-center gap-4">
      <!-- Video container -->
      <div
        class="relative w-full max-w-md"
        :class="{ 'cursor-pointer': !isProcessing }"
        @click="!isProcessing && !hasVideo && triggerFileInput()"
      >
        <!-- Drop zone / Video display -->
        <div
          class="relative aspect-video rounded-lg overflow-hidden border-2 transition-all"
          :class="[
            isDragging ? 'border-teal-500 ring-4 ring-teal-200 border-dashed' : 'border-gray-200',
            isProcessing ? 'opacity-50' : '',
            !hasVideo ? 'border-dashed bg-gray-50' : '',
          ]"
          @dragover="handleDragOver"
          @dragleave="handleDragLeave"
          @drop.prevent="handleDrop"
        >
          <!-- Video player (when video exists) -->
          <video
            v-if="previewUrl || displayVideoUrl"
            :src="previewUrl || displayVideoUrl || undefined"
            :poster="displayThumbnail || undefined"
            controls
            class="w-full h-full object-cover"
            data-testid="video-player"
          >
            Votre navigateur ne supporte pas la lecture vidéo.
          </video>

          <!-- Placeholder (when no video) -->
          <div
            v-else
            class="w-full h-full flex flex-col items-center justify-center p-6 cursor-pointer"
            @click="triggerFileInput"
            data-testid="video-placeholder"
          >
            <svg
              class="w-16 h-16 text-gray-400 mb-3"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"
              />
            </svg>
            <p class="text-sm text-gray-600 text-center">
              Cliquez ou glissez-déposez une vidéo
            </p>
            <p class="text-xs text-gray-400 mt-1">
              MP4, MOV ou AVI - Max 50MB - Max 2 min
            </p>
          </div>

          <!-- Upload progress overlay -->
          <div
            v-if="isUploading && uploadProgress"
            class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 rounded-lg"
            data-testid="upload-progress"
          >
            <!-- Progress bar -->
            <div class="w-3/4 bg-gray-200 rounded-full h-2.5 mb-3">
              <div
                class="bg-teal-500 h-2.5 rounded-full transition-all duration-300"
                :style="{ width: `${progressPercentage}%` }"
              ></div>
            </div>
            <p class="text-white text-sm font-medium">{{ progressPercentage }}%</p>
            <p class="text-white/80 text-xs mt-1">Envoi en cours...</p>
          </div>

          <!-- Delete loading overlay -->
          <div
            v-else-if="isDeleting"
            class="absolute inset-0 flex items-center justify-center bg-black/60 rounded-lg"
          >
            <svg
              class="animate-spin h-10 w-10 text-white"
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
        </div>
      </div>

      <!-- Hidden file input -->
      <input
        ref="fileInputRef"
        type="file"
        accept="video/mp4,video/quicktime,video/x-msvideo,.mp4,.mov,.avi"
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
          {{ hasVideo ? 'Changer la vidéo' : 'Ajouter une vidéo' }}
        </button>

        <!-- Delete button (only show if has video and not in preview mode) -->
        <button
          v-if="displayVideoUrl && !previewUrl"
          type="button"
          :disabled="isProcessing"
          class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          aria-label="Supprimer la vidéo de présentation"
          @click="handleDelete"
          data-testid="delete-button"
        >
          Supprimer
        </button>
      </div>

      <!-- Help text -->
      <p class="text-xs text-gray-500 text-center">
        Format MP4, MOV ou AVI. Taille max: 50MB. Durée max: 2 minutes
      </p>
    </div>

    <!-- Delete confirmation modal -->
    <ConfirmModal
      :is-open="showDeleteModal"
      title="Supprimer la vidéo"
      message="Êtes-vous sûr de vouloir supprimer cette vidéo de présentation ? Cette action est irréversible."
      confirm-text="Supprimer"
      cancel-text="Annuler"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="cancelDelete"
    />
  </div>
</template>

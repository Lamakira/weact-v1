<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Building2, Upload, Trash2, Loader2, AlertCircle } from 'lucide-vue-next'
import { useAgencyLogo } from '../composables/useAgencyLogo'

const {
  logoUrl,
  thumbnailUrl,
  isLoading,
  isUploading,
  isDeleting,
  error,
  hasLogo,
  fetchLogo,
  uploadLogo,
  deleteLogo,
  validateFile,
} = useAgencyLogo()

const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)

// Computed for display URL (prefer thumbnail for preview)
const displayUrl = computed(() => thumbnailUrl.value || logoUrl.value)

// Computed for any loading state
const isProcessing = computed(() => isLoading.value || isUploading.value || isDeleting.value)

// Fetch logo on mount
onMounted(() => {
  fetchLogo()
})

function triggerFileInput() {
  fileInputRef.value?.click()
}

async function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    await handleUpload(file)
  }
  // Reset input to allow re-uploading the same file
  if (target) {
    target.value = ''
  }
}

async function handleUpload(file: File) {
  const validation = validateFile(file)
  if (!validation.valid) {
    // Set error so user sees validation feedback (especially for drag-drop)
    error.value = validation.error || null
    return
  }
  await uploadLogo(file)
}

async function handleDelete() {
  await deleteLogo()
}

function handleDragOver(event: DragEvent) {
  event.preventDefault()
  isDragging.value = true
}

function handleDragLeave() {
  isDragging.value = false
}

async function handleDrop(event: DragEvent) {
  event.preventDefault()
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    await handleUpload(file)
  }
}
</script>

<template>
  <div class="space-y-4" data-testid="agency-logo-upload">
    <!-- Logo Preview Area -->
    <div
      class="relative w-32 h-32 rounded-xl overflow-hidden border-2 transition-colors duration-200"
      :class="[
        isDragging
          ? 'border-weact-500 bg-weact-50'
          : 'border-gray-200 bg-gray-50',
        { 'cursor-pointer hover:border-weact-400': !isProcessing }
      ]"
      @click="!isProcessing && triggerFileInput()"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @drop="handleDrop"
      data-testid="logo-preview-area"
    >
      <!-- Logo Image -->
      <img
        v-if="hasLogo && displayUrl"
        :src="displayUrl"
        alt="Logo de l'agence"
        class="w-full h-full object-cover"
        data-testid="logo-image"
      />

      <!-- Placeholder Icon -->
      <div
        v-else
        class="w-full h-full flex items-center justify-center"
        data-testid="logo-placeholder"
      >
        <Building2 class="w-12 h-12 text-gray-300" />
      </div>

      <!-- Loading Overlay -->
      <div
        v-if="isProcessing"
        class="absolute inset-0 bg-white/80 flex items-center justify-center"
        data-testid="loading-overlay"
      >
        <Loader2 class="w-8 h-8 text-weact-500 animate-spin" />
      </div>

      <!-- Drag Indicator -->
      <div
        v-if="isDragging"
        class="absolute inset-0 bg-weact-500/10 flex items-center justify-center"
        data-testid="drag-indicator"
      >
        <Upload class="w-8 h-8 text-weact-500" />
      </div>
    </div>

    <!-- Hidden File Input -->
    <input
      ref="fileInputRef"
      type="file"
      accept="image/jpeg,image/png"
      class="hidden"
      @change="handleFileChange"
      data-testid="file-input"
    />

    <!-- Action Buttons -->
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-weact-500 rounded-lg hover:bg-weact-600 focus:outline-none focus:ring-2 focus:ring-weact-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        :disabled="isProcessing"
        @click="triggerFileInput"
        data-testid="upload-button"
      >
        <Upload class="w-4 h-4" />
        {{ hasLogo ? 'Changer' : 'Ajouter' }}
      </button>

      <button
        v-if="hasLogo"
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        :disabled="isProcessing"
        @click="handleDelete"
        data-testid="delete-button"
      >
        <Trash2 class="w-4 h-4" />
        Supprimer
      </button>
    </div>

    <!-- Error Message -->
    <div
      v-if="error"
      class="flex items-center gap-2 text-sm text-red-600"
      data-testid="error-message"
    >
      <AlertCircle class="w-4 h-4 flex-shrink-0" />
      <span>{{ error }}</span>
    </div>

    <!-- Help Text -->
    <p class="text-xs text-gray-500" data-testid="help-text">
      Format JPG ou PNG. Taille maximale : 2 Mo.
    </p>
  </div>
</template>

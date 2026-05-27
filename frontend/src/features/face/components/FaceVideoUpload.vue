<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Lock } from 'lucide-vue-next'
import type { FaceVideo, FaceVideoType, VideoUploadProgress } from '../types'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

interface Props {
  type: FaceVideoType
  videos: FaceVideo[]
  maxForType: number
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
  delete: [videoId: string]
  'navigate-pricing': []
}>()

const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const previewUrl = ref<string | null>(null)
const showDeleteModal = ref(false)
const videoIdToDelete = ref<string | null>(null)

const typeLabel = computed<'Acting' | 'UGC'>(() =>
  props.type === 'acting' ? 'Acting' : 'UGC',
)

const sortedVideos = computed(() =>
  [...props.videos].sort((a, b) => a.position - b.position),
)

const canAddMore = computed(
  () => sortedVideos.value.length < props.maxForType && props.maxForType >= 1,
)

const isTierLocked = computed(() => props.maxForType < 1)

const isProcessing = computed(() => props.isUploading || props.isDeleting)

const tierBannerCopy = computed(() =>
  props.type === 'acting'
    ? "L'ajout d'une vidéo Acting nécessite un abonnement Pro ou Élite."
    : "L'ajout d'une vidéo UGC est réservé aux abonnés Élite.",
)

const addCtaLabel = computed(() => `Ajouter une vidéo ${typeLabel.value}`)

const confirmModalMessage = computed(
  () =>
    `Êtes-vous sûr de vouloir supprimer cette vidéo ${typeLabel.value} ? Cette action est irréversible.`,
)

const progressPercentage = computed(() => props.uploadProgress?.percentage ?? 0)

function lockReasonFor(video: FaceVideo): 'tier_below_required' | 'quota_exceeded' | null {
  if (video.position <= props.maxForType) return null
  return props.maxForType < 1 ? 'tier_below_required' : 'quota_exceeded'
}

function lockBadgeTitle(video: FaceVideo): string {
  const reason = lockReasonFor(video)
  if (reason === 'tier_below_required') {
    return `Cette vidéo n'est pas visible publiquement — votre formule actuelle ne permet pas de vidéo ${typeLabel.value}.`
  }
  if (reason === 'quota_exceeded') {
    return `Cette vidéo n'est pas visible publiquement — votre formule actuelle limite à ${props.maxForType} vidéo${props.maxForType > 1 ? 's' : ''} ${typeLabel.value}.`
  }
  return ''
}

function triggerFileInput(): void {
  if (isProcessing.value || isTierLocked.value || !canAddMore.value) return
  fileInputRef.value?.click()
}

function handleFileSelect(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (isProcessing.value || isTierLocked.value || !canAddMore.value) {
    input.value = ''
    return
  }
  if (file) processFile(file)
  input.value = ''
}

function handleDrop(event: DragEvent): void {
  isDragging.value = false
  if (isProcessing.value || isTierLocked.value || !canAddMore.value) return
  const file = event.dataTransfer?.files?.[0]
  if (file) processFile(file)
}

function processFile(file: File): void {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = URL.createObjectURL(file)
  emit('upload', file)
}

function handleDragOver(event: DragEvent): void {
  if (isProcessing.value || isTierLocked.value || !canAddMore.value) return
  event.preventDefault()
  isDragging.value = true
}

function handleDragLeave(): void {
  isDragging.value = false
}

function openDeleteModal(videoId: string): void {
  videoIdToDelete.value = videoId
  showDeleteModal.value = true
}

function confirmDelete(): void {
  if (videoIdToDelete.value) emit('delete', videoIdToDelete.value)
  showDeleteModal.value = false
  videoIdToDelete.value = null
}

function cancelDelete(): void {
  showDeleteModal.value = false
  videoIdToDelete.value = null
}

function onPricingClick(): void {
  emit('navigate-pricing')
}

// Clear preview once a new video lands in the list (upload completed).
watch(
  () => props.videos.length,
  (newLen, oldLen) => {
    if (newLen > oldLen && previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
    }
  },
)

// Clear preview on upload error.
watch(
  () => props.error,
  (newError) => {
    if (newError && previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
    }
  },
)

// Revoke any dangling preview URL when the component unmounts (route change /
// reactive v-if). Without this, navigating away mid-upload leaks the Blob URL
// for the document's lifetime.
onBeforeUnmount(() => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
})
</script>

<template>
  <div :data-testid="`face-video-section-${type}`" class="flex flex-col items-center gap-4">
    <!-- Tier-locked banner (whole type unavailable) -->
    <div
      v-if="isTierLocked"
      class="w-full bg-amber-50 border border-amber-200 rounded-md p-4 flex items-start gap-3"
      data-testid="face-video-tier-locked-banner"
    >
      <Lock class="w-5 h-5 text-amber-700 flex-shrink-0 mt-0.5" />
      <div class="flex-1">
        <p class="text-sm text-amber-800 font-medium">{{ tierBannerCopy }}</p>
        <button
          type="button"
          class="mt-3 px-4 py-2 text-sm font-medium text-white bg-[#198496] rounded-md hover:bg-[#146c7a] transition-colors"
          data-testid="face-video-tier-locked-cta"
          @click="onPricingClick"
        >
          Choisir un abonnement
        </button>
      </div>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      class="w-full rounded-md bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="face-video-error"
    >
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Vertical stack of video cards -->
    <div v-if="sortedVideos.length > 0" class="w-full max-w-md space-y-4">
      <div
        v-for="video in sortedVideos"
        :key="video.id"
        class="relative w-full"
        :data-testid="`face-video-card-${video.id}`"
        :data-video-position="video.position"
        :data-lock-reason="lockReasonFor(video) ?? ''"
      >
        <div
          v-if="lockReasonFor(video) !== null"
          class="mb-2 inline-flex items-center gap-1 bg-amber-500 text-white rounded-md px-2 py-1 text-xs font-medium shadow-sm"
          :data-testid="`face-video-lock-badge-${video.id}`"
          :title="lockBadgeTitle(video)"
        >
          <Lock class="w-3 h-3" />
          Visible en privé
        </div>
        <div class="relative aspect-video rounded-lg overflow-hidden border-2 border-gray-200">
          <video
            :src="video.video_url ?? undefined"
            :poster="video.thumbnail_url ?? undefined"
            controls
            class="w-full h-full object-cover"
          >
            Votre navigateur ne supporte pas la lecture vidéo.
          </video>
        </div>
        <div class="mt-2 flex justify-end">
          <button
            type="button"
            :disabled="isProcessing"
            class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-md hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            :data-testid="`face-video-delete-${video.id}`"
            :aria-label="`Supprimer la vidéo ${typeLabel}`"
            @click="openDeleteModal(video.id)"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Drop zone (only visible when slots remain and not tier-locked) -->
    <div
      v-if="canAddMore"
      class="relative aspect-video rounded-lg overflow-hidden border-2 border-dashed transition-all w-full max-w-md mx-auto"
      :class="[
        isDragging ? 'border-teal-500 ring-4 ring-teal-200' : 'border-gray-300 bg-gray-50',
        isProcessing ? 'opacity-50' : 'cursor-pointer',
      ]"
      :data-testid="`face-video-dropzone-${type}`"
      @click="triggerFileInput"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @drop.prevent="handleDrop"
    >
      <div class="w-full h-full flex flex-col items-center justify-center p-6">
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
          Cliquez ou glissez-déposez une vidéo {{ typeLabel }}
        </p>
        <p class="text-xs text-gray-400 mt-1">MP4, MOV ou AVI - Max 50MB - Max 2 min</p>
      </div>

      <!-- Upload progress overlay -->
      <div
        v-if="isUploading && uploadProgress"
        class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 rounded-lg"
        data-testid="face-video-upload-progress"
      >
        <div
          class="w-3/4 bg-gray-200 rounded-full h-2.5 mb-3"
          role="progressbar"
          :aria-valuenow="progressPercentage"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="`Upload progress: ${progressPercentage}%`"
        >
          <div
            class="bg-teal-500 h-2.5 rounded-full transition-all duration-300"
            :style="{ width: `${progressPercentage}%` }"
          ></div>
        </div>
        <p class="text-white text-sm font-medium">{{ progressPercentage }}%</p>
        <p class="text-white/80 text-xs mt-1">Envoi en cours...</p>
      </div>
    </div>

    <!-- Add CTA -->
    <button
      v-if="canAddMore"
      type="button"
      class="px-4 py-2 text-sm font-medium text-white bg-teal-600 rounded-lg hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      :disabled="isProcessing"
      data-testid="face-video-add-cta"
      @click="triggerFileInput"
    >
      {{ addCtaLabel }}
    </button>

    <!-- Help text — symmetric with PresentationVideoUpload to keep the three video sections visually consistent. -->
    <p
      v-if="canAddMore"
      class="text-xs text-gray-500 text-center"
      data-testid="face-video-help-text"
    >
      Format MP4, MOV ou AVI. Taille max: 50MB. Durée max: 2 minutes
    </p>

    <!-- Hidden file input — only mounted when the user can actually add a video (AC #8 invariant). -->
    <input
      v-if="canAddMore"
      ref="fileInputRef"
      type="file"
      accept="video/mp4,video/quicktime,video/x-msvideo,.mp4,.mov,.avi"
      class="hidden"
      :data-testid="`face-video-file-input-${type}`"
      @change="handleFileSelect"
    />

    <!-- Delete confirmation modal -->
    <ConfirmModal
      :is-open="showDeleteModal"
      title="Supprimer la vidéo"
      :message="confirmModalMessage"
      confirm-text="Supprimer"
      cancel-text="Annuler"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="cancelDelete"
    />
  </div>
</template>

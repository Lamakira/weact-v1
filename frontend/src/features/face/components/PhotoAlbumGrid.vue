<script setup lang="ts">
import { computed, ref } from 'vue'
import { Lock } from 'lucide-vue-next'
import type { FacePhoto } from '../types'

interface Props {
  photos: FacePhoto[]
  isLoading?: boolean
  isDeleting?: boolean
  canAddMore?: boolean
  publicPhotoLimit?: number
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isDeleting: false,
  canAddMore: true,
  publicPhotoLimit: 2,
})

const emit = defineEmits<{
  delete: [photoId: string]
  reorder: [order: string[]]
  'add-click': []
}>()

// Create slots array (always show 4 slots)
const slots = computed(() => {
  const result: (FacePhoto | null)[] = []
  for (let i = 0; i < 4; i++) {
    result.push(props.photos[i] || null)
  }
  return result
})

const isProcessing = computed(() => props.isLoading || props.isDeleting)

function isPhotoLockedForPublic(photo: FacePhoto): boolean {
  return photo.position > (props.publicPhotoLimit ?? 2)
}

// Empty slots are index-based because there is no persisted photo position yet.
function isSlotLockedForPublic(slotIndex: number): boolean {
  return slotIndex + 1 > (props.publicPhotoLimit ?? 2)
}

/**
 * Lightbox state
 */
const lightboxPhoto = ref<FacePhoto | null>(null)

function openLightbox(photo: FacePhoto): void {
  lightboxPhoto.value = photo
}

function closeLightbox(): void {
  lightboxPhoto.value = null
}

/**
 * Handle delete button click
 */
function handleDelete(photoId: string): void {
  emit('delete', photoId)
}

/**
 * Handle add button click
 */
function handleAddClick(): void {
  emit('add-click')
}
</script>

<template>
  <div class="photo-album-grid relative" data-testid="photo-album-grid">
    <!-- Grid of 4 slots -->
    <div class="grid grid-cols-2 gap-3">
      <div
        v-for="(slot, index) in slots"
        :key="index"
        class="relative aspect-square rounded-lg overflow-hidden border-2 transition-all"
        :class="[
          slot ? 'border-gray-200' : 'border-dashed border-gray-300 bg-gray-50',
          isProcessing ? 'opacity-50' : '',
        ]"
        :data-testid="`album-slot-${index}`"
      >
        <!-- Photo -->
        <template v-if="slot">
          <img
            :src="slot.photo_url"
            :alt="`Photo ${index + 1}`"
            class="w-full h-full object-cover"
            :data-testid="`album-photo-${slot.id}`"
          />

          <!-- Locked-for-public badge (positions > publicPhotoLimit) -->
          <div
            v-if="isPhotoLockedForPublic(slot)"
            class="absolute top-2 right-2 inline-flex items-center gap-1 bg-amber-500 text-white rounded-md px-2 py-1 text-xs font-medium shadow-sm pointer-events-none"
            :data-testid="`album-locked-badge-${slot.id}`"
          >
            <Lock class="w-3 h-3" />
            Visible en privé uniquement
          </div>

          <!-- Action buttons overlay -->
          <div
            v-if="!isProcessing"
            class="absolute inset-0 flex items-center justify-center gap-3 bg-black/40 opacity-0 hover:opacity-100 transition-opacity"
          >
            <!-- View / enlarge button -->
            <button
              type="button"
              class="p-2 bg-white rounded-full text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2"
              :data-testid="`view-photo-${slot.id}`"
              aria-label="Voir la photo"
              @click="openLightbox(slot)"
            >
              <svg
                class="w-5 h-5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6"
                />
              </svg>
            </button>

            <!-- Delete button -->
            <button
              type="button"
              class="p-2 bg-red-500 rounded-full text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
              :data-testid="`delete-photo-${slot.id}`"
              aria-label="Supprimer la photo"
              @click="handleDelete(slot.id)"
            >
              <svg
                class="w-5 h-5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                />
              </svg>
            </button>
          </div>
        </template>

        <!-- Empty slot placeholder -->
        <template v-else>
          <button
            v-if="canAddMore && !isProcessing"
            type="button"
            class="w-full h-full flex flex-col items-center justify-center text-gray-400 hover:text-teal-600 hover:bg-teal-50 transition-colors cursor-pointer"
            :data-testid="`add-photo-slot-${index}`"
            @click="handleAddClick"
          >
            <svg
              class="w-8 h-8"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span class="text-xs mt-1">Ajouter</span>
            <span
              v-if="isSlotLockedForPublic(index)"
              class="text-[10px] italic text-gray-400 mt-1 block"
              :data-testid="`album-slot-premium-hint-${index}`"
            >
              Premium requis pour publier
            </span>
          </button>

          <!-- Disabled placeholder when full or processing -->
          <div
            v-else
            class="w-full h-full flex items-center justify-center"
            :data-testid="`empty-slot-${index}`"
          >
            <svg
              class="w-8 h-8 text-gray-300"
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
          </div>
        </template>
      </div>
    </div>

    <!-- Lightbox modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="lightboxPhoto"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80"
          data-testid="photo-lightbox"
          @click.self="closeLightbox"
        >
          <!-- Close button -->
          <button
            type="button"
            class="absolute top-4 right-4 p-2 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors"
            aria-label="Fermer"
            @click="closeLightbox"
          >
            <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <img
            :src="lightboxPhoto.photo_url"
            :alt="'Photo'"
            class="max-w-full max-h-[85vh] rounded-lg object-contain"
          />
        </div>
      </Transition>
    </Teleport>

    <!-- Loading overlay -->
    <div
      v-if="isLoading"
      class="absolute inset-0 flex items-center justify-center bg-white/50"
      data-testid="loading-overlay"
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
  </div>
</template>

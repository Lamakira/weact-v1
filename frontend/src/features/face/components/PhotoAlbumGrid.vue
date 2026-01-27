<script setup lang="ts">
import { computed } from 'vue'
import type { FacePhoto } from '../types'

interface Props {
  photos: FacePhoto[]
  isLoading?: boolean
  isDeleting?: boolean
  canAddMore?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isDeleting: false,
  canAddMore: true,
})

const emit = defineEmits<{
  delete: [photoId: number]
  reorder: [order: number[]]
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

/**
 * Handle delete button click
 */
function handleDelete(photoId: number): void {
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

          <!-- Delete button overlay -->
          <div
            v-if="!isProcessing"
            class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 hover:opacity-100 transition-opacity"
          >
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

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Image, X, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import type { FacePhoto } from '../types'

/**
 * Props
 */
const props = defineProps<{
  photos: FacePhoto[]
}>()

/**
 * State for lightbox
 */
const selectedIndex = ref<number | null>(null)

/**
 * Computed: Has photos
 */
const hasPhotos = computed((): boolean => props.photos.length > 0)

/**
 * Computed: Display photos (limit to 4)
 */
const displayPhotos = computed((): FacePhoto[] => props.photos.slice(0, 4))

/**
 * Computed: Selected photo
 */
const selectedPhoto = computed((): FacePhoto | null => {
  if (selectedIndex.value === null) return null
  return props.photos[selectedIndex.value] || null
})

/**
 * Open lightbox
 */
function openLightbox(index: number): void {
  selectedIndex.value = index
}

/**
 * Close lightbox
 */
function closeLightbox(): void {
  selectedIndex.value = null
}

/**
 * Go to previous photo
 */
function prevPhoto(): void {
  if (selectedIndex.value === null) return
  selectedIndex.value =
    selectedIndex.value > 0 ? selectedIndex.value - 1 : props.photos.length - 1
}

/**
 * Go to next photo
 */
function nextPhoto(): void {
  if (selectedIndex.value === null) return
  selectedIndex.value =
    selectedIndex.value < props.photos.length - 1 ? selectedIndex.value + 1 : 0
}

/**
 * Handle keyboard navigation
 */
function handleKeydown(event: KeyboardEvent): void {
  if (selectedIndex.value === null) return

  switch (event.key) {
    case 'Escape':
      closeLightbox()
      break
    case 'ArrowLeft':
      prevPhoto()
      break
    case 'ArrowRight':
      nextPhoto()
      break
  }
}
</script>

<template>
  <div v-if="hasPhotos" class="rounded-2xl border border-border bg-card p-6">
    <h2 class="mb-4 text-lg font-semibold text-foreground">Portfolio</h2>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <button
        v-for="(photo, index) in displayPhotos"
        :key="photo.id"
        type="button"
        class="group relative aspect-square overflow-hidden rounded-xl bg-muted border border-gray-200"
        @click="openLightbox(index)"
      >
        <img
          :src="photo.photo_url"
          :alt="`Photo ${index + 1}`"
          class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
        />
        <div
          class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20"
        />
      </button>
    </div>

    <!-- Lightbox Modal -->
    <Teleport to="body">
      <div
        v-if="selectedPhoto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
        tabindex="0"
        @click.self="closeLightbox"
        @keydown="handleKeydown"
      >
        <!-- Close button -->
        <button
          type="button"
          class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="closeLightbox"
        >
          <X class="h-6 w-6" />
        </button>

        <!-- Navigation: Previous -->
        <button
          v-if="photos.length > 1"
          type="button"
          class="absolute left-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="prevPhoto"
        >
          <ChevronLeft class="h-8 w-8" />
        </button>

        <!-- Image -->
        <img
          :src="selectedPhoto.photo_url"
          :alt="`Photo ${selectedIndex! + 1}`"
          class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain"
        />

        <!-- Navigation: Next -->
        <button
          v-if="photos.length > 1"
          type="button"
          class="absolute right-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="nextPhoto"
        >
          <ChevronRight class="h-8 w-8" />
        </button>

        <!-- Counter -->
        <div
          class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-4 py-2 text-sm text-white"
        >
          {{ selectedIndex! + 1 }} / {{ photos.length }}
        </div>
      </div>
    </Teleport>
  </div>

  <!-- Empty state -->
  <div
    v-else
    class="rounded-2xl border border-dashed border-border bg-muted/30 p-6 text-center"
  >
    <Image class="mx-auto h-10 w-10 text-muted-foreground/50" />
    <p class="mt-2 text-sm text-muted-foreground">
      Aucune photo dans le portfolio
    </p>
  </div>
</template>

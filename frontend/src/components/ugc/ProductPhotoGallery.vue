<script setup lang="ts">
import { computed, ref } from 'vue'
import { X, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { useHqLightbox } from '@/composables/useHqLightbox'
import type { ProductPhoto } from './ugc'

/**
 * Galerie des photos produit UGC (spec photos produit) — vignettes grid +
 * lightbox large avec « Voir l'original » à la demande (useHqLightbox),
 * calque CandidatePhotoGallery. Ne rend RIEN sans photo (bookings/missions
 * pré-deploy : aucune section, aucun placeholder).
 */
const props = defineProps<{
  photos: ProductPhoto[]
  title?: string
}>()

const selectedIndex = ref<number | null>(null)

const selectedPhoto = computed((): ProductPhoto | null => {
  if (selectedIndex.value === null) return null
  return props.photos[selectedIndex.value] || null
})

// HQ à la demande : la lightbox montre la variante large ; l'original n'est
// chargé qu'après un clic explicite sur « Voir l'original ».
const { showOriginal, lightboxSrc, canViewOriginal, reset: resetHqView } =
  useHqLightbox(selectedPhoto)

function openLightbox(index: number): void {
  selectedIndex.value = index
  resetHqView()
}

function closeLightbox(): void {
  selectedIndex.value = null
  resetHqView()
}

function prevPhoto(): void {
  if (selectedIndex.value === null) return
  selectedIndex.value = selectedIndex.value > 0 ? selectedIndex.value - 1 : props.photos.length - 1
  resetHqView()
}

function nextPhoto(): void {
  if (selectedIndex.value === null) return
  selectedIndex.value = selectedIndex.value < props.photos.length - 1 ? selectedIndex.value + 1 : 0
  resetHqView()
}

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
  <div v-if="photos.length > 0" data-testid="product-photo-gallery">
    <p v-if="title" class="mb-2 text-xs text-gray-500">{{ title }}</p>

    <div class="flex gap-3">
      <button
        v-for="(photo, index) in photos"
        :key="photo.id"
        type="button"
        class="group relative h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
        :aria-label="`Agrandir la photo du produit ${index + 1}`"
        data-testid="product-photo-thumb"
        @click="openLightbox(index)"
      >
        <img
          :src="photo.grid_url || photo.photo_url || ''"
          :alt="`Photo du produit ${index + 1}`"
          class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
          loading="lazy"
        />
        <div class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20" />
      </button>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <div
        v-if="selectedPhoto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
        tabindex="0"
        data-testid="product-photo-lightbox"
        @click.self="closeLightbox"
        @keydown="handleKeydown"
      >
        <button
          type="button"
          class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          aria-label="Fermer"
          @click="closeLightbox"
        >
          <X class="h-6 w-6" />
        </button>

        <button
          v-if="photos.length > 1"
          type="button"
          class="absolute left-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          aria-label="Photo précédente"
          @click="prevPhoto"
        >
          <ChevronLeft class="h-8 w-8" />
        </button>

        <img
          :src="lightboxSrc"
          :alt="`Photo du produit ${selectedIndex! + 1}`"
          class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain"
          data-testid="product-lightbox-image"
        />

        <!-- HQ à la demande -->
        <button
          v-if="!showOriginal && canViewOriginal"
          type="button"
          class="absolute bottom-16 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-4 py-2 text-sm text-white transition-colors hover:bg-white/20"
          data-testid="product-lightbox-view-original"
          @click="showOriginal = true"
        >
          Voir l'original
        </button>

        <button
          v-if="photos.length > 1"
          type="button"
          class="absolute right-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          aria-label="Photo suivante"
          @click="nextPhoto"
        >
          <ChevronRight class="h-8 w-8" />
        </button>

        <div
          v-if="photos.length > 1"
          class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-4 py-2 text-sm text-white"
        >
          {{ selectedIndex! + 1 }} / {{ photos.length }}
        </div>
      </div>
    </Teleport>
  </div>
</template>

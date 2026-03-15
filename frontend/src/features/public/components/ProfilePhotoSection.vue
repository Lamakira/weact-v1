<script setup lang="ts">
import { User, Image as ImageIcon, Lock } from 'lucide-vue-next'

interface Props {
  photoUrl: string | null
  prenom: string
  isAvailable: boolean
  hasAlbumPhotos: boolean
  albumPhotosCount: number
  showAlbumLock?: boolean
}

withDefaults(defineProps<Props>(), {
  showAlbumLock: true,
})

const emit = defineEmits<{ 'album-click': [] }>()

function scrollToAlbum(): void {
  const el = document.getElementById('album-photos')
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  emit('album-click')
}
</script>

<template>
  <div class="relative w-full" data-testid="profile-photo-section">
    <!-- Main Photo Container (3:4 Aspect Ratio) -->
    <div
      class="relative aspect-[3/4] w-full overflow-hidden rounded-2xl shadow-lg bg-gray-100 border border-gray-100 group"
    >
      <!-- Profile Image -->
      <template v-if="photoUrl">
        <img
          :src="photoUrl"
          :alt="`Photo de profil de ${prenom}`"
          class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
          data-testid="profile-photo"
        />
      </template>

      <!-- Placeholder when no photo -->
      <template v-else>
        <div
          class="flex h-full w-full flex-col items-center justify-center text-gray-400"
          data-testid="profile-photo-placeholder"
        >
          <User :size="64" stroke-width="1.5" aria-hidden="true" />
          <span class="mt-2 text-sm font-medium">Photo non disponible</span>
        </div>
      </template>

      <!-- Availability Badge (Overlay Top-Right) -->
      <div class="absolute top-4 right-4 z-10">
        <div
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold shadow-sm backdrop-blur-md"
          :class="[
            isAvailable
              ? 'bg-green-500/90 text-white'
              : 'bg-gray-500/90 text-white',
          ]"
          data-testid="availability-badge"
        >
          <span
            class="h-2 w-2 rounded-full"
            :class="[
              isAvailable ? 'bg-white animate-pulse' : 'bg-gray-300',
            ]"
          ></span>
          {{ isAvailable ? 'Disponible' : 'Indisponible' }}
        </div>
      </div>

      <!-- Album Teaser Overlay (Bottom) -->
      <div
        v-if="hasAlbumPhotos && albumPhotosCount > 0"
        class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/60 to-transparent pt-12"
        data-testid="album-indicator"
      >
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-2">
          <button
            type="button"
            class="flex items-center gap-2 bg-white/20 backdrop-blur-md px-3 py-2 rounded-lg border border-white/30 text-white hover:bg-white/30 transition-colors cursor-pointer"
            :aria-label="`Voir les ${albumPhotosCount} photos de l'album`"
            @click="scrollToAlbum"
          >
            <ImageIcon :size="16" aria-hidden="true" />
            <span class="text-sm font-medium">+{{ albumPhotosCount }} photos</span>
          </button>

          <!-- Registration Tease (guests only) -->
          <div
            v-if="showAlbumLock"
            class="flex items-center gap-1.5 bg-black/30 backdrop-blur-md px-2.5 py-1.5 rounded-lg text-[10px] uppercase tracking-wider font-bold text-white/90"
            aria-label="Album réservé aux producteurs inscrits"
          >
            <Lock :size="12" aria-hidden="true" />
            <span>Réservé aux Producteurs</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Sub-text / Caption -->
    <p class="mt-3 text-center text-xs text-gray-500 italic">
      Photo officielle de {{ prenom }}
    </p>
  </div>
</template>

<style scoped>
/* Ensure smooth transitions for the hover effect */
.group:hover img {
  transform: scale(1.05);
}
</style>

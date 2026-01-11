<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useProfilePhoto } from '@/features/face/composables/useProfilePhoto'
import { usePhotoAlbum } from '@/features/face/composables/usePhotoAlbum'
import ProfilePhotoUpload from '@/features/face/components/ProfilePhotoUpload.vue'
import PhotoAlbumGrid from '@/features/face/components/PhotoAlbumGrid.vue'
import AlbumPhotoUpload from '@/features/face/components/AlbumPhotoUpload.vue'

const router = useRouter()
const { logout, isLoading: isAuthLoading } = useAuth()
const {
  profile,
  isLoading,
  isUploading,
  isDeleting,
  error,
  fetchProfile,
  uploadPhoto,
  deletePhoto,
} = useProfilePhoto()

// Album composable
const {
  photos: albumPhotos,
  isLoading: isAlbumLoading,
  isUploading: isAlbumUploading,
  isDeleting: isAlbumDeleting,
  error: albumError,
  canAddMore,
  isFull,
  fetchPhotos: fetchAlbumPhotos,
  addPhoto: addAlbumPhoto,
  deletePhoto: deleteAlbumPhoto,
} = usePhotoAlbum()

// Success message
const successMessage = ref<string | null>(null)

// Fetch profile and album on mount
onMounted(async () => {
  await Promise.all([fetchProfile(), fetchAlbumPhotos()])
})

/**
 * Handle photo upload
 */
async function handleUpload(file: File): Promise<void> {
  successMessage.value = null
  const result = await uploadPhoto(file)

  if (result.success && result.message) {
    successMessage.value = result.message
  }
}

/**
 * Handle photo delete
 */
async function handleDelete(): Promise<void> {
  successMessage.value = null
  const result = await deletePhoto()

  if (result.success && result.message) {
    successMessage.value = result.message
  }
}

/**
 * Handle logout
 */
async function handleLogout(): Promise<void> {
  await logout()
}

/**
 * Navigate back to dashboard
 */
function goBack(): void {
  router.push({ name: 'face-dashboard' })
}

// File input ref for album upload
const albumFileInputRef = ref<HTMLInputElement | null>(null)

/**
 * Handle album photo upload
 */
async function handleAlbumUpload(file: File): Promise<void> {
  successMessage.value = null
  const result = await addAlbumPhoto(file)

  if (result.success && result.message) {
    successMessage.value = result.message
  }
}

/**
 * Handle album photo delete
 */
async function handleAlbumDelete(photoId: number): Promise<void> {
  successMessage.value = null
  const result = await deleteAlbumPhoto(photoId)

  if (result.success && result.message) {
    successMessage.value = result.message
  }
}

/**
 * Trigger album file input when clicking "add" in grid
 */
function handleAlbumAddClick(): void {
  albumFileInputRef.value?.click()
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <div class="flex items-center gap-4">
          <button
            @click="goBack"
            class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
            aria-label="Retour"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
              />
            </svg>
          </button>
          <h1 class="text-xl font-semibold text-gray-900">Mon profil</h1>
        </div>

        <button
          @click="handleLogout"
          :disabled="isAuthLoading"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          data-testid="logout-button"
        >
          <svg
            v-if="!isAuthLoading"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-5 h-5"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"
            />
          </svg>
          <svg
            v-else
            class="w-5 h-5 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isAuthLoading ? 'Déconnexion...' : 'Déconnexion' }}
        </button>
      </div>
    </header>

    <!-- Main content -->
    <main class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Loading state -->
      <div v-if="isLoading" class="flex justify-center py-12">
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

      <!-- Profile content -->
      <div v-else class="bg-white rounded-lg shadow">
        <!-- Success message -->
        <div
          v-if="successMessage"
          class="mx-6 mt-6 rounded-md bg-green-50 p-4 border border-green-200"
          role="status"
          data-testid="success-message"
        >
          <div class="flex items-center gap-2">
            <svg
              class="w-5 h-5 text-green-600"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="text-sm text-green-700">{{ successMessage }}</p>
          </div>
        </div>

        <!-- Profile photo section -->
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-6">Photo de profil</h2>

          <ProfilePhotoUpload
            :profile="profile"
            :is-uploading="isUploading"
            :is-deleting="isDeleting"
            :error="error"
            @upload="handleUpload"
            @delete="handleDelete"
          />
        </div>

        <!-- Album photos section -->
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-2">Album photos</h2>
          <p class="text-sm text-gray-500 mb-6">
            Ajoutez jusqu'à 4 photos pour montrer votre polyvalence aux producteurs.
          </p>

          <!-- Album grid -->
          <PhotoAlbumGrid
            :photos="albumPhotos"
            :is-loading="isAlbumLoading"
            :is-deleting="isAlbumDeleting"
            :can-add-more="canAddMore"
            @delete="handleAlbumDelete"
            @add-click="handleAlbumAddClick"
          />

          <!-- Album upload -->
          <div class="mt-6">
            <AlbumPhotoUpload
              :is-full="isFull"
              :is-uploading="isAlbumUploading"
              :error="albumError"
              @upload="handleAlbumUpload"
            />
          </div>

          <!-- Hidden file input for grid clicks -->
          <input
            ref="albumFileInputRef"
            type="file"
            accept="image/jpeg,image/png"
            class="hidden"
            @change="(e) => { const file = (e.target as HTMLInputElement).files?.[0]; if (file) handleAlbumUpload(file); (e.target as HTMLInputElement).value = ''; }"
          />
        </div>

        <!-- Profile info section -->
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Informations</h2>

          <dl class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-gray-500 sm:w-32">Nom</dt>
              <dd class="text-sm text-gray-900">{{ profile?.nom ?? '-' }}</dd>
            </div>

            <div class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-gray-500 sm:w-32">Prénom</dt>
              <dd class="text-sm text-gray-900">{{ profile?.prenom ?? '-' }}</dd>
            </div>

            <div class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-gray-500 sm:w-32">Pseudo</dt>
              <dd class="text-sm text-gray-900">@{{ profile?.username ?? '-' }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.focus\:ring-primary:focus {
  --tw-ring-color: #198496;
}
</style>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useProducerProfilePhoto } from '@/features/producer/composables/useProducerProfilePhoto'
import { useProducerBio } from '@/features/producer/composables/useProducerBio'
import { useAgencyLogo } from '@/features/producer/composables/useAgencyLogo'
import { useToast } from '@/composables/useToast'
import ProducerProfilePhotoUpload from '@/features/producer/components/ProducerProfilePhotoUpload.vue'
import ProducerBioEditor from '@/features/producer/components/ProducerBioEditor.vue'
import AgencyLogoUpload from '@/features/producer/components/AgencyLogoUpload.vue'
import BasicInfoSection from '@/features/producer/components/BasicInfoSection.vue'

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
} = useProducerProfilePhoto()

const {
  bio,
  isLoading: isBioLoading,
  isSaving: isBioSaving,
  error: bioError,
  fetchBio,
  saveBio,
} = useProducerBio()

// Agency logo composable (only used for agency producers)
const agencyLogo = useAgencyLogo()

// Toast notifications
const toast = useToast()

// Computed: Check if producer is an agency
const isAgency = computed(() => profile.value?.type === 'agency')

// Success message
const successMessage = ref<string | null>(null)

// Fetch profile and bio on mount
onMounted(async () => {
  await Promise.all([fetchProfile(), fetchBio()])
})

/**
 * Handle photo upload
 */
async function handleUpload(file: File): Promise<void> {
  successMessage.value = null
  const result = await uploadPhoto(file)

  if (result.success && result.message) {
    successMessage.value = result.message
    toast.success(result.message)
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
    toast.success(result.message)
  }
}

/**
 * Handle bio save
 */
async function handleBioSave(newBio: string | null): Promise<void> {
  successMessage.value = null
  const result = await saveBio(newBio)

  if (result.success && result.message) {
    successMessage.value = result.message
    toast.success(result.message)
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
  router.push({ name: 'producer-dashboard' })
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
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-weact transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
          class="animate-spin h-8 w-8 text-weact-600"
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
        <div id="section-profile-photo" class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-6">Photo de profil</h2>

          <ProducerProfilePhotoUpload
            :profile="profile"
            :is-uploading="isUploading"
            :is-deleting="isDeleting"
            :error="error"
            @upload="handleUpload"
            @delete="handleDelete"
          />
        </div>

        <!-- Agency logo section (Agency only) -->
        <div
          v-if="isAgency"
          id="section-agency-logo"
          class="p-6 border-b border-gray-200"
          data-testid="agency-logo-section"
        >
          <h2 class="text-lg font-medium text-gray-900 mb-6">Logo de l'agence</h2>
          <AgencyLogoUpload />
        </div>

        <!-- Bio section -->
        <div id="section-bio" class="p-6 border-b border-gray-200">
          <!-- Bio loading state -->
          <div v-if="isBioLoading" class="flex justify-center py-8">
            <svg
              class="animate-spin h-6 w-6 text-weact-600"
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
          <ProducerBioEditor
            v-else
            :bio="bio"
            :is-saving="isBioSaving"
            :error="bioError"
            @save="handleBioSave"
          />
        </div>

        <!-- Basic info section (agency_name or first_name/last_name) -->
        <div id="section-basic-info" class="p-6 border-b border-gray-200" data-testid="basic-info-section">
          <BasicInfoSection />
        </div>

        <!-- Profile info section -->
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Informations</h2>

          <dl class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-gray-500 sm:w-32">Type</dt>
              <dd class="text-sm text-gray-900">
                {{ profile?.type === 'agency' ? 'Agence' : 'Particulier' }}
              </dd>
            </div>

            <div v-if="profile?.type === 'agency'" class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-gray-500 sm:w-32">Nom de l'agence</dt>
              <dd class="text-sm text-gray-900">{{ profile?.agency_name ?? '-' }}</dd>
            </div>

            <div v-if="profile?.type === 'particulier'" class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-gray-500 sm:w-32">Nom complet</dt>
              <dd class="text-sm text-gray-900">{{ profile?.display_name ?? '-' }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </main>
  </div>
</template>

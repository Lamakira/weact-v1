<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loader2 } from 'lucide-vue-next'
import { useProfilePhoto } from '@/features/face/composables/useProfilePhoto'
import { usePhotoAlbum } from '@/features/face/composables/usePhotoAlbum'
import { usePresentationVideo } from '@/features/face/composables/usePresentationVideo'
import { useActingVideo } from '@/features/face/composables/useActingVideo'
import { useBioLocation } from '@/features/face/composables/useBioLocation'
import { useLangues } from '@/features/face/composables/useLangues'
import { usePersonalInfo } from '@/features/face/composables/usePersonalInfo'
import { usePhysicalCharacteristics } from '@/features/face/composables/usePhysicalCharacteristics'
import { useCategoryNiche } from '@/features/face/composables/useCategoryNiche'
import { useExperiences } from '@/features/face/composables/useExperiences'
import { useTarifs } from '@/features/face/composables/useTarifs'
import { useAvailability } from '@/features/face/composables/useAvailability'
import { useProfileCompletion } from '@/features/face/composables/useProfileCompletion'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import { useToast } from '@/composables/useToast'
import ProfilePhotoUpload from '@/features/face/components/ProfilePhotoUpload.vue'
import PhotoAlbumGrid from '@/features/face/components/PhotoAlbumGrid.vue'
import AlbumPhotoUpload from '@/features/face/components/AlbumPhotoUpload.vue'
import PresentationVideoUpload from '@/features/face/components/PresentationVideoUpload.vue'
import ActingVideoUpload from '@/features/face/components/ActingVideoUpload.vue'
import LanguesTagInput from '@/features/face/components/LanguesTagInput.vue'
import PersonalInfoForm from '@/features/face/components/PersonalInfoForm.vue'
import BioLocationForm from '@/features/face/components/BioLocationForm.vue'
import PhysicalCharacteristicsForm from '@/features/face/components/PhysicalCharacteristicsForm.vue'
import CategoryNicheForm from '@/features/face/components/CategoryNicheForm.vue'
import ExperiencesList from '@/features/face/components/ExperiencesList.vue'
import TarifsForm from '@/features/face/components/TarifsForm.vue'
import AvailabilityToggle from '@/features/face/components/AvailabilityToggle.vue'
import ProfileCompletionIndicator from '@/features/face/components/ProfileCompletionIndicator.vue'
import RatingDisplay from '@/components/RatingDisplay.vue'
import BasicInfoSection from '@/features/face/components/BasicInfoSection.vue'
import SubscriptionPanel from '@/features/face/components/SubscriptionPanel.vue'
import EmailChangeForm from '@/features/auth/components/EmailChangeForm.vue'
import PasswordChangeForm from '@/features/auth/components/PasswordChangeForm.vue'
import DataPrivacySection from '@/components/account/DataPrivacySection.vue'
import type { ExperienceFormData, TarifsFormData } from '@/features/face/types'

const route = useRoute()
const router = useRouter()

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
  fetchPhotos: fetchAlbumPhotos,
  addPhoto: addAlbumPhoto,
  deletePhoto: deleteAlbumPhoto,
} = usePhotoAlbum()

// Presentation video composable
const {
  videoInfo,
  isUploading: isVideoUploading,
  isDeleting: isVideoDeleting,
  error: videoError,
  uploadProgress,
  fetchVideoInfo,
  uploadVideo,
  deleteVideo,
} = usePresentationVideo()

// Acting video composable
const {
  videoInfo: actingVideoInfo,
  isUploading: isActingVideoUploading,
  isDeleting: isActingVideoDeleting,
  error: actingVideoError,
  uploadProgress: actingUploadProgress,
  fetchVideoInfo: fetchActingVideoInfo,
  uploadVideo: uploadActingVideo,
  deleteVideo: deleteActingVideo,
} = useActingVideo()

// Bio and location composable
const {
  bioLocationInfo,
  isSaving: isBioLocationSaving,
  error: bioLocationError,
  fetchBioLocation,
  updateBioLocation,
} = useBioLocation()

// Langues composable
const {
  languesInfo,
  isLoading: isLanguesLoading,
  isSaving: isLanguesSaving,
  error: languesError,
  fetchLangues,
  updateLangues,
  MAX_LANGUES,
  MAX_LANGUE_LENGTH,
} = useLangues()

// Personal info composable
const {
  personalInfo,
  isLoading: isPersonalInfoLoading,
  isSaving: isPersonalInfoSaving,
  error: personalInfoError,
  fetchPersonalInfo,
  updatePersonalInfo,
} = usePersonalInfo()

// Physical characteristics composable
const {
  physicalCharacteristicsInfo,
  isSaving: isPhysicalCharacteristicsSaving,
  error: physicalCharacteristicsError,
  fetchPhysicalCharacteristics,
  updatePhysicalCharacteristics,
} = usePhysicalCharacteristics()

// Category and niche composable
const {
  categoryNicheInfo,
  categoryOptions,
  nicheOptions,
  isLoading: isCategoryNicheLoading,
  isSaving: isCategoryNicheSaving,
  error: categoryNicheError,
  fetchCategoryNiche,
  fetchCategoryOptions,
  fetchNicheOptions,
  updateCategoryNiche,
} = useCategoryNiche()

// Experiences composable
const {
  experiences,
  isLoading: isExperiencesLoading,
  isSaving: isExperiencesSaving,
  isDeleting: isExperiencesDeleting,
  error: experiencesError,
  validationErrors: experiencesValidationErrors,
  fetchExperiences,
  addExperience,
  editExperience,
  removeExperience,
  clearError: clearExperiencesError,
} = useExperiences()

// Tarifs composable
const {
  tarifsInfo,
  isSaving: isTarifsSaving,
  error: tarifsError,
  fetchTarifs,
  updateTarifs,
} = useTarifs()

// Availability composable
const {
  availabilityInfo,
  isLoading: isAvailabilityLoading,
  isSaving: isAvailabilitySaving,
  error: availabilityError,
  fetchAvailability,
  toggleAvailability,
} = useAvailability()

// Profile completion composable
const {
  isLoading: isCompletionLoading,
  error: completionError,
  percentage: completionPercentage,
  missingItems: completionMissingItems,
  isComplete: isProfileComplete,
  fetchCompletion,
} = useProfileCompletion()

// Subscription status composable (FP-2.7) — the SubscriptionPanel owns offers,
// cta and the payment flow; ProfileEditPage only needs the capability matrix
// for the tier-aware album section.
const {
  capabilities,
  maxAlbumPhotos,
  fetchStatus: fetchSubscriptionStatus,
} = useSubscriptionStatus()

// Decision #9 minimal video touch — re-derive the acting-video gate locally off
// the FP-2 capability matrix (the FP-1 canUploadActingVideo computed is gone).
const subscriptionCanUploadActingVideo = computed(
  () => (capabilities.value?.max_acting_videos ?? 0) > 0,
)

// Entitlement-aware "album is full" predicate — fires at the tier's photo quota.
const isFullByEntitlement = computed(
  () => albumPhotos.value.length >= maxAlbumPhotos.value,
)

// Ref to ExperiencesList component for resetting form states
const experiencesListRef = ref<InstanceType<typeof ExperiencesList> | null>(null)

// Toast notifications
const toast = useToast()

// Success message
const successMessage = ref<string | null>(null)

// Fetch profile, album, videos, bio/location, physical characteristics, category/niche, experiences, tarifs, availability, and completion on mount
onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    fetchAlbumPhotos(),
    fetchVideoInfo(),
    fetchActingVideoInfo(),
    fetchBioLocation(),
    fetchLangues(),
    fetchPersonalInfo(),
    fetchPhysicalCharacteristics(),
    fetchCategoryNiche(),
    fetchExperiences(),
    fetchTarifs(),
    fetchAvailability(),
    fetchCompletion(),
    fetchSubscriptionStatus(),
    // Fetch options separately with error handling
    fetchCategoryOptions().catch(() => {
      // Options failed to load - dropdowns will be empty but form still usable
      console.warn('Failed to load category options')
    }),
    fetchNicheOptions().catch(() => {
      // Options failed to load - dropdowns will be empty but form still usable
      console.warn('Failed to load niche options')
    }),
  ])

  // Auto-scroll to WhatsApp field when navigated from banner CTA
  if (route.query.focus === 'whatsapp') {
    await nextTick()
    const section = document.getElementById('section-personal-info')
    if (section) {
      section.scrollIntoView({ behavior: 'smooth', block: 'start' })
      setTimeout(() => {
        const input = document.getElementById('whatsapp-number')
        input?.focus()
      }, 500)
    }
    router.replace({ path: route.path, query: {} })
  }
})

/**
 * Handle photo upload
 */
async function handleUpload(file: File): Promise<void> {
  successMessage.value = null
  const result = await uploadPhoto(file)

  if (result.success && result.message) {
    successMessage.value = result.message
    await fetchCompletion() // Refresh completion after profile photo upload
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
    await fetchCompletion() // Refresh completion after profile photo delete
  }
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
async function handleAlbumDelete(photoId: string): Promise<void> {
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
  if (isFullByEntitlement.value) {
    toast.warning('Votre quota de photos est atteint pour votre abonnement actuel.')
    return
  }

  albumFileInputRef.value?.click()
}

/**
 * Handle video upload
 */
async function handleVideoUpload(file: File): Promise<void> {
  successMessage.value = null
  const result = await uploadVideo(file)

  if (result.success && result.message) {
    successMessage.value = result.message
    await fetchCompletion() // Refresh completion after presentation video upload
  }
}

/**
 * Handle video delete
 */
async function handleVideoDelete(): Promise<void> {
  const result = await deleteVideo()

  if (result.success) {
    toast.success(result.message || 'Vidéo supprimée avec succès')
    await fetchCompletion() // Refresh completion after presentation video delete
  }
}

/**
 * Handle acting video upload
 */
async function handleActingVideoUpload(file: File): Promise<void> {
  if (!subscriptionCanUploadActingVideo.value) {
    toast.warning('La vidéo d\'acting est réservée aux abonnés Premium.')
    return
  }

  successMessage.value = null
  const result = await uploadActingVideo(file)

  if (result.success && result.message) {
    successMessage.value = result.message
    await fetchCompletion() // Refresh completion after acting video upload
  }
}

/**
 * Handle acting video delete
 */
async function handleActingVideoDelete(): Promise<void> {
  const result = await deleteActingVideo()

  if (result.success) {
    toast.success(result.message || 'Vidéo supprimée avec succès')
    await fetchCompletion() // Refresh completion after acting video delete
  }
}

/**
 * Handle bio/location save
 */
async function handleBioLocationSave(data: {
  bio: string | null
  ville: string | null
  pays: string | null
}): Promise<void> {
  const result = await updateBioLocation(data)

  if (result.success) {
    toast.success(result.message || 'Profil mis à jour avec succès')
    await fetchCompletion() // Refresh completion after bio/location save
  }
}

/**
 * Handle langues save
 */
async function handleLanguesSave(langues: string[] | null): Promise<void> {
  const result = await updateLangues(langues)

  if (result.success) {
    toast.success(result.message || 'Langues mises à jour avec succès')
    await fetchCompletion()
  }
}

/**
 * Handle personal info save
 */
async function handlePersonalInfoSave(data: {
  sexe: string | null
  date_naissance: string | null
  nationalite: string | null
  pays: string | null
}): Promise<void> {
  const result = await updatePersonalInfo(data)

  if (result.success) {
    toast.success(result.message || 'Informations personnelles mises à jour avec succès')
  }
}

/**
 * Handle physical characteristics save
 */
async function handlePhysicalCharacteristicsSave(data: {
  taille: number | null
  poids: number | null
}): Promise<void> {
  const result = await updatePhysicalCharacteristics(data)

  if (result.success) {
    toast.success(result.message || 'Profil mis à jour avec succès')
  }
}

/**
 * Handle category/niche save
 */
async function handleCategoryNicheSave(data: {
  categories: string[] | null
  niches: string[] | null
}): Promise<void> {
  const result = await updateCategoryNiche(data)

  if (result.success) {
    toast.success(result.message || 'Profil mis à jour avec succès')
    await fetchCompletion() // Refresh completion after category/niche save
  }
}

/**
 * Handle experience add
 */
async function handleExperienceAdd(data: ExperienceFormData): Promise<void> {
  clearExperiencesError()
  const result = await addExperience(data)

  if (result.success) {
    toast.success(result.message || 'Expérience ajoutée avec succès')
    experiencesListRef.value?.resetFormStates()
  }
}

/**
 * Handle experience edit
 */
async function handleExperienceEdit(id: string, data: ExperienceFormData): Promise<void> {
  clearExperiencesError()
  const result = await editExperience(id, data)

  if (result.success) {
    toast.success(result.message || 'Expérience mise à jour avec succès')
    experiencesListRef.value?.resetFormStates()
  }
}

/**
 * Handle experience delete
 */
async function handleExperienceDelete(id: string): Promise<void> {
  clearExperiencesError()
  const success = await removeExperience(id)

  if (success) {
    toast.success('Expérience supprimée avec succès')
  }
}

/**
 * Handle tarifs save
 */
async function handleTarifsSave(data: TarifsFormData): Promise<void> {
  const result = await updateTarifs(data)

  if (result.success) {
    toast.success(result.message || 'Tarifs mis à jour avec succès')
    await fetchCompletion() // Refresh completion after tarifs save
  }
}

/**
 * Handle availability toggle
 */
async function handleAvailabilityToggle(): Promise<void> {
  const result = await toggleAvailability()

  if (result.success) {
    toast.success(result.message || 'Disponibilité mise à jour avec succès')
  }
}

/**
 * Refresh profile completion after the subscription panel confirms a payment.
 * Album / acting video refetches catch the case where an upgrade unlocks media
 * previously masked by the lower tier — `maxAlbumPhotos` updates reactively but
 * the photo array itself stays cached until forced. The toast belongs on the
 * page that initiates the payment; for resume-pending confirms triggered from
 * here, the visual feedback comes from the panel state flip.
 */
async function handleSubscriptionChanged(): Promise<void> {
  await Promise.all([
    fetchAlbumPhotos(),
    fetchActingVideoInfo(),
    fetchCompletion(),
  ])
}

/**
 * Handle click on missing item in completion indicator
 * Scrolls to the appropriate section
 */
function handleCompletionItemClick(itemKey: string): void {
  // Map missing item keys to section IDs
  const sectionMap: Record<string, string> = {
    profile_photo: 'section-profile-photo',
    presentation_video: 'section-presentation-video',
    acting_video: 'section-acting-video',
    bio: 'section-bio-location',
    ville: 'section-bio-location',
    langues: 'section-langues',
    categorie: 'section-category-niche',
    tarifs: 'section-tarifs',
    whatsapp_number: 'section-personal-info',
  }

  const sectionId = sectionMap[itemKey]
  if (sectionId) {
    const element = document.getElementById(sectionId)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-800">Mon profil</h1>
      <p class="mt-1 text-slate-500">Gérez vos informations et paramètres</p>
    </div>

    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <Loader2 class="h-8 w-8 animate-spin text-primary" />
    </div>

    <!-- Profile content - Two column layout on desktop -->
    <div v-else class="flex flex-col lg:flex-row gap-6">
      <!-- Left Sidebar (sticky on desktop) -->
      <div class="lg:w-80 flex-shrink-0">
        <div class="lg:sticky lg:top-6 space-y-6">
          <!-- Profile photo card -->
          <div id="section-profile-photo" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Photo de mise en avant</h2>
            <ProfilePhotoUpload
              :profile="profile"
              :is-uploading="isUploading"
              :is-deleting="isDeleting"
              :error="error"
              @upload="handleUpload"
              @delete="handleDelete"
            />
          </div>

          <!-- Availability card -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-medium text-gray-900 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-teal-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Disponibilité
            </h2>
            <AvailabilityToggle
              :is-available="availabilityInfo?.is_available ?? true"
              :is-loading="isAvailabilityLoading"
              :is-saving="isAvailabilitySaving"
              :error="availabilityError"
              @toggle="handleAvailabilityToggle"
            />
          </div>

          <!-- Tarif card (sidebar) -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-medium text-gray-900 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-teal-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75" />
              </svg>
              Tarif
            </h2>
            <TarifsForm
              :tarifs-info="tarifsInfo"
              :is-saving="isTarifsSaving"
              :error="tarifsError"
              @save="handleTarifsSave"
            />
          </div>

          <!-- Profile completion card -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-medium text-gray-900 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-teal-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
              </svg>
              Complétion
            </h2>
            <ProfileCompletionIndicator
              :percentage="isCompletionLoading ? undefined : completionPercentage"
              :missing-items="completionMissingItems"
              :is-complete="isProfileComplete"
              variant="full"
              @click-item="handleCompletionItemClick"
            />
            <div v-if="completionError" class="mt-4 rounded-md bg-red-50 p-3 border border-red-200" role="alert">
              <p class="text-sm text-red-700">{{ completionError }}</p>
            </div>
          </div>

          <!-- Rating card -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-medium text-gray-900 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-teal-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
              </svg>
              Ma note
            </h2>
            <RatingDisplay
              :average-rating="profile?.average_rating ?? null"
              :review-count="profile?.ratings_count ?? 0"
            />
          </div>

          <!-- Profile info card -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-medium text-gray-900 mb-4">Informations</h2>
            <dl class="space-y-3 text-sm">
              <div class="flex justify-between">
                <dt class="text-gray-500">Nom</dt>
                <dd class="text-gray-900 font-medium">{{ profile?.nom ?? '-' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-500">Prénom</dt>
                <dd class="text-gray-900 font-medium">{{ profile?.prenom ?? '-' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-500">Pseudo</dt>
                <dd class="text-gray-900 font-medium">@{{ profile?.username ?? '-' }}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      <!-- Right Main Content -->
      <div class="flex-1 min-w-0">
        <!-- Success message -->
        <div
          v-if="successMessage"
          class="mb-6 rounded-xl bg-green-50 p-4 border border-green-200"
          role="status"
          data-testid="success-message"
        >
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-green-700">{{ successMessage }}</p>
          </div>
        </div>

        <!-- Subscription panel (FP-2.7) -->
        <div class="mb-6">
          <SubscriptionPanel @subscription-changed="handleSubscriptionChanged" />
        </div>

        <!-- Basic info section (name/username) -->
        <div class="mb-6">
          <BasicInfoSection />
        </div>

        <!-- Personal info section (sexe, date_naissance, nationalite, pays) -->
        <div id="section-personal-info" class="mb-6 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Identité</h2>
            <p class="text-sm text-gray-500 mt-1">Sexe, date de naissance, nationalité et pays de résidence</p>
          </div>
          <div class="p-6">
            <!-- Loading state -->
            <div v-if="isPersonalInfoLoading" class="flex justify-center py-8">
              <Loader2 class="animate-spin h-6 w-6 text-teal-600" />
            </div>

            <PersonalInfoForm
              v-else
              :personal-info="personalInfo"
              :is-saving="isPersonalInfoSaving"
              :error="personalInfoError"
              @save="handlePersonalInfoSave"
            />
          </div>
        </div>

        <!-- Account security section -->
        <div class="mb-6 bg-white rounded-2xl border border-gray-100 px-6 py-4 space-y-4">
          <EmailChangeForm />
          <div class="border-t border-gray-100 pt-4">
            <PasswordChangeForm />
          </div>
        </div>

        <!-- Main form sections -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">

        <!-- Album photos section -->
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-2">Album photos</h2>
          <p class="text-sm text-gray-500 mb-6">
            Ajoutez jusqu'à {{ maxAlbumPhotos }} photo{{ maxAlbumPhotos > 1 ? 's' : '' }}
            pour montrer votre polyvalence aux producteurs.
          </p>

          <!-- Album grid -->
          <PhotoAlbumGrid
            :photos="albumPhotos"
            :is-loading="isAlbumLoading"
            :is-deleting="isAlbumDeleting"
            :can-add-more="canAddMore && !isFullByEntitlement"
            :max-album-photos="maxAlbumPhotos"
            @delete="handleAlbumDelete"
            @add-click="handleAlbumAddClick"
          />

          <!-- Album upload -->
          <div class="mt-6">
            <AlbumPhotoUpload
              :is-full="isFullByEntitlement"
              :is-uploading="isAlbumUploading"
              :error="albumError"
              :upload-limit="maxAlbumPhotos"
              :current-count="albumPhotos.length"
              :locked-by-quota="isFullByEntitlement"
              @upload="handleAlbumUpload"
            />
          </div>

          <!-- Hidden file input for grid clicks -->
          <input
            ref="albumFileInputRef"
            type="file"
            accept="image/jpeg,image/png"
            class="hidden"
            @change="(e) => {
              const file = (e.target as HTMLInputElement).files?.[0];
              if (isFullByEntitlement) { (e.target as HTMLInputElement).value = ''; return; }
              if (file) handleAlbumUpload(file);
              (e.target as HTMLInputElement).value = '';
            }"
          />
        </div>

        <!-- Presentation video section -->
        <div id="section-presentation-video" class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-2">Vidéo de présentation</h2>
          <p class="text-sm text-gray-500 mb-6">
            Ajoutez une courte vidéo pour vous présenter aux producteurs.
          </p>

          <PresentationVideoUpload
            :video-info="videoInfo"
            :is-uploading="isVideoUploading"
            :is-deleting="isVideoDeleting"
            :error="videoError"
            :upload-progress="uploadProgress"
            @upload="handleVideoUpload"
            @delete="handleVideoDelete"
          />
        </div>

        <!-- Acting video section -->
        <div id="section-acting-video" class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-2">Vidéo d'acting</h2>
          <p class="text-sm text-gray-500">
            Ajoutez une vidéo démontrant votre talent d'acteur aux producteurs.
          </p>
          <p
            v-if="!subscriptionCanUploadActingVideo"
            class="text-amber-700 text-xs mt-1 mb-6"
          >
            Fonctionnalité réservée aux abonnés Premium.
          </p>
          <div v-else class="mb-6"></div>

          <ActingVideoUpload
            :video-info="actingVideoInfo"
            :is-uploading="isActingVideoUploading"
            :is-deleting="isActingVideoDeleting"
            :error="actingVideoError"
            :upload-progress="actingUploadProgress"
            :can-upload="subscriptionCanUploadActingVideo"
            @upload="handleActingVideoUpload"
            @delete="handleActingVideoDelete"
          />
        </div>

        <!-- Bio and location section -->
        <div id="section-bio-location" class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-2">Bio et Localisation</h2>
          <p class="text-sm text-gray-500 mb-6">
            Partagez votre parcours et indiquez votre localisation aux producteurs.
          </p>

          <BioLocationForm
            :bio-location-info="bioLocationInfo"
            :is-saving="isBioLocationSaving"
            :error="bioLocationError"
            @save="handleBioLocationSave"
          />
        </div>

        <!-- Langues section -->
        <div id="section-langues" class="p-6 border-b border-gray-200">
          <!-- Loading state -->
          <div v-if="isLanguesLoading" class="flex justify-center py-8">
            <Loader2 class="animate-spin h-6 w-6 text-teal-600" />
          </div>

          <LanguesTagInput
            v-else
            :langues="languesInfo?.langues ?? null"
            :is-saving="isLanguesSaving"
            :error="languesError"
            :max-langues="MAX_LANGUES"
            :max-langue-length="MAX_LANGUE_LENGTH"
            @save="handleLanguesSave"
          />
        </div>

        <!-- Physical characteristics section -->
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-2">Caractéristiques physiques</h2>
          <p class="text-sm text-gray-500 mb-6">
            Indiquez votre taille et poids pour aider les producteurs à trouver des talents correspondant à leurs besoins.
          </p>

          <PhysicalCharacteristicsForm
            :physical-characteristics-info="physicalCharacteristicsInfo"
            :is-saving="isPhysicalCharacteristicsSaving"
            :error="physicalCharacteristicsError"
            @save="handlePhysicalCharacteristicsSave"
          />
        </div>

        <!-- Category and niche section -->
        <div id="section-category-niche" class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-medium text-gray-900 mb-2">Catégorie et Niche</h2>
          <p class="text-sm text-gray-500 mb-6">
            Sélectionnez votre catégorie et niche pour aider les producteurs à vous trouver selon votre spécialisation.
          </p>

          <!-- Loading state -->
          <div v-if="isCategoryNicheLoading" class="flex justify-center py-8">
            <svg
              class="animate-spin h-6 w-6 text-teal-600"
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

          <CategoryNicheForm
            v-else
            :category-niche-info="categoryNicheInfo"
            :category-options="categoryOptions"
            :niche-options="nicheOptions"
            :is-saving="isCategoryNicheSaving"
            :error="categoryNicheError"
            @save="handleCategoryNicheSave"
          />
        </div>

        <!-- Experiences section -->
        <div class="p-6 border-b border-gray-200">
          <ExperiencesList
            ref="experiencesListRef"
            :experiences="experiences"
            :is-loading="isExperiencesLoading"
            :is-saving="isExperiencesSaving"
            :is-deleting="isExperiencesDeleting"
            :error="experiencesError"
            :validation-errors="experiencesValidationErrors"
            @add="handleExperienceAdd"
            @edit="handleExperienceEdit"
            @delete="handleExperienceDelete"
          />
        </div>

        </div>

        <!-- Data privacy & account actions (bottom of page) -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <DataPrivacySection />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
</style>

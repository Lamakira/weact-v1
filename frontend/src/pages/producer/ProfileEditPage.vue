<script setup lang="ts">
/**
 * ProfileEditPage
 * Producer profile editing page.
 * This component is rendered inside ProducerLayout via nested routing.
 */
import { computed, onMounted, ref } from 'vue'
import { X } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useProducerProfilePhoto } from '@/features/producer/composables/useProducerProfilePhoto'
import { useProducerBio } from '@/features/producer/composables/useProducerBio'
import { useToast } from '@/composables/useToast'
import ProducerProfilePhotoUpload from '@/features/producer/components/ProducerProfilePhotoUpload.vue'
import ProducerBioEditor from '@/features/producer/components/ProducerBioEditor.vue'
import AgencyLogoUpload from '@/features/producer/components/AgencyLogoUpload.vue'
import BasicInfoSection from '@/features/producer/components/BasicInfoSection.vue'
import EmailChangeForm from '@/features/auth/components/EmailChangeForm.vue'
import PasswordChangeForm from '@/features/auth/components/PasswordChangeForm.vue'
import DataPrivacySection from '@/components/account/DataPrivacySection.vue'

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

// Auth store for email
const authStore = useAuthStore()

// Toast notifications
const toast = useToast()

// Computed: Check if producer is an agency
const isAgency = computed(() => profile.value?.type === 'agency')

// Producers deliberately have NO completion percentage: only two items are real
// (visual identity, bio), and a ring built on two booleans is noise. A single
// dismissible line does the same job for none of the machinery.
//
// The dismissal is keyed per user: the browser may be shared between accounts.
// Storage can be unavailable (private mode, quota): it then degrades to
// "dismissed for this page view only".
const nudgeStorageKey = computed<string | null>(() =>
  authStore.user?.id != null ? `producer_profile_nudge_dismissed:${authStore.user.id}` : null
)

function readNudgeDismissed(): boolean {
  if (nudgeStorageKey.value === null) return false

  try {
    return localStorage.getItem(nudgeStorageKey.value) === '1'
  } catch {
    return false
  }
}

const nudgeDismissed = ref(readNudgeDismissed())

const hasVisualIdentity = computed(() =>
  isAgency.value ? !!profile.value?.agency_logo_url : !!profile.value?.profile_photo_url
)

const showProfileNudge = computed(
  () =>
    !nudgeDismissed.value &&
    !isLoading.value &&
    !isBioLoading.value &&
    (!hasVisualIdentity.value || !bio.value)
)

const nudgeMessage = computed<string>(() => {
  const missing: string[] = []

  if (!hasVisualIdentity.value) {
    missing.push(isAgency.value ? 'ajoutez le logo de votre agence' : 'ajoutez une photo de profil')
  }
  if (!bio.value) missing.push('présentez votre activité en quelques lignes')

  return `Complétez votre profil — ${missing.join(' et ')} — les Faces répondent bien plus souvent aux Producteurs identifiables.`
})

function dismissNudge(): void {
  nudgeDismissed.value = true

  if (nudgeStorageKey.value === null) return

  try {
    localStorage.setItem(nudgeStorageKey.value, '1')
  } catch {
    // Not persisted: the nudge reappears on the next visit, which is harmless.
  }
}

// Fetch profile and bio on mount
onMounted(async () => {
  await Promise.all([fetchProfile(), fetchBio()])
})

/**
 * Handle photo upload
 */
async function handleUpload(file: File): Promise<void> {
  const result = await uploadPhoto(file)

  if (result.success && result.message) {
    toast.success(result.message)
  }
}

/**
 * Handle photo delete
 */
async function handleDelete(): Promise<void> {
  const result = await deletePhoto()

  if (result.success && result.message) {
    toast.success(result.message)
  }
}

/**
 * Handle bio save
 */
async function handleBioSave(newBio: string | null): Promise<void> {
  const result = await saveBio(newBio)

  if (result.success && result.message) {
    toast.success(result.message)
  }
}
</script>

<template>
  <div>
    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <svg
        class="animate-spin h-8 w-8 text-primary"
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
    <div v-else>
      <!-- Non-blocking completion nudge (no percentage, see script comment) -->
      <div
        v-if="showProfileNudge"
        class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
        data-testid="producer-profile-nudge"
      >
        <p class="flex-grow text-sm text-amber-900">
          {{ nudgeMessage }}
        </p>
        <button
          type="button"
          class="shrink-0 rounded p-1 text-amber-700 hover:bg-amber-100"
          aria-label="Masquer ce rappel"
          data-testid="producer-profile-nudge-dismiss"
          @click="dismissNudge"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100">
      <!-- Visual identity section: Photo (particulier) OR Logo (agency) -->
      <div id="section-visual-identity" class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-base font-semibold text-slate-800 mb-3">
          {{ isAgency ? 'Logo de l\'agence' : 'Photo de profil' }}
        </h2>

        <!-- Agency logo -->
        <AgencyLogoUpload v-if="isAgency" data-testid="agency-logo-section" />

        <!-- Profile photo (particulier) -->
        <ProducerProfilePhotoUpload
          v-else
          :profile="profile"
          :is-uploading="isUploading"
          :is-deleting="isDeleting"
          :error="error"
          @upload="handleUpload"
          @delete="handleDelete"
        />
      </div>

      <!-- Bio section -->
      <div id="section-bio" class="px-6 py-4 border-b border-gray-100">
        <!-- Bio loading state -->
        <div v-if="isBioLoading" class="flex justify-center py-4">
          <svg
            class="animate-spin h-6 w-6 text-primary"
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

      <!-- Bottom section: 2 columns on desktop -->
      <div class="px-6 py-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Basic info section (agency_name or first_name/last_name) -->
        <div id="section-basic-info" data-testid="basic-info-section">
          <BasicInfoSection />
        </div>

        <!-- Profile info section -->
        <div id="section-info">
          <h2 class="text-base font-semibold text-slate-800 mb-3">Informations</h2>

          <dl class="space-y-2">
            <div class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-slate-500 sm:w-32">Email</dt>
              <dd class="text-sm text-slate-800">{{ authStore.user?.email ?? '-' }}</dd>
            </div>

            <div class="flex flex-col sm:flex-row sm:gap-4">
              <dt class="text-sm font-medium text-slate-500 sm:w-32">Type</dt>
              <dd class="text-sm text-slate-800">
                {{ isAgency ? 'Agence' : 'Particulier' }}
              </dd>
            </div>
          </dl>

          <div class="mt-4 pt-4 border-t border-gray-100">
            <EmailChangeForm />
          </div>
          <div class="mt-4 pt-4 border-t border-gray-100">
            <PasswordChangeForm />
          </div>
          <div class="mt-4 pt-4 border-t border-gray-100">
            <DataPrivacySection />
          </div>
        </div>
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

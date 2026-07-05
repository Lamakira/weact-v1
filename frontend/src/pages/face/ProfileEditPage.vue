<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch, type Component } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Loader2,
  User,
  Image as ImageIcon,
  Briefcase,
  Shield,
  Contact,
  Fingerprint,
  Ruler,
  Globe,
  MapPin,
  Video,
  Clapperboard,
  Sparkles,
  Tag,
  Coins,
  Lock,
  Database,
} from 'lucide-vue-next'
import { useProfilePhoto } from '@/features/face/composables/useProfilePhoto'
import { usePhotoAlbum } from '@/features/face/composables/usePhotoAlbum'
import { usePresentationVideo } from '@/features/face/composables/usePresentationVideo'
import { useFaceVideos } from '@/features/face/composables/useFaceVideos'
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
import { useAuthStore } from '@/stores/auth'
import ProfilePhotoUpload from '@/features/face/components/ProfilePhotoUpload.vue'
import PhotoAlbumGrid from '@/features/face/components/PhotoAlbumGrid.vue'
import AlbumPhotoUpload from '@/features/face/components/AlbumPhotoUpload.vue'
import PresentationVideoUpload from '@/features/face/components/PresentationVideoUpload.vue'
import FaceVideoUpload from '@/features/face/components/FaceVideoUpload.vue'
import MediaQuotaUpsell from '@/features/face/components/MediaQuotaUpsell.vue'
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
import EmailChangeForm from '@/features/auth/components/EmailChangeForm.vue'
import PasswordChangeForm from '@/features/auth/components/PasswordChangeForm.vue'
import DataPrivacySection from '@/components/account/DataPrivacySection.vue'
import type { ExperienceFormData, TarifsFormData } from '@/features/face/types'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

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

// Face videos composable (FP-2.7.1 — typed face_videos API, acting + ugc)
const {
  videos: faceVideos,
  actingVideos,
  ugcVideos,
  isUploading: isFaceVideoUploading,
  uploadingType: faceVideoUploadingType,
  isDeleting: isFaceVideoDeleting,
  deletingType: faceVideoDeletingType,
  error: faceVideoError,
  errorType: faceVideoErrorType,
  uploadProgress: faceVideoUploadProgress,
  fetchVideos,
  uploadVideo: uploadFaceVideo,
  deleteVideo: deleteFaceVideo,
} = useFaceVideos()

// Per-type isolators so the two <FaceVideoUpload> instances only show their own
// upload/delete activity (the composable singletons isUploading / isDeleting are
// shared, but the per-type refs scope them to the active type).
const isActingFaceVideoUploading = computed(
  () => isFaceVideoUploading.value && faceVideoUploadingType.value === 'acting',
)
const isUgcFaceVideoUploading = computed(
  () => isFaceVideoUploading.value && faceVideoUploadingType.value === 'ugc',
)
const isActingFaceVideoDeleting = computed(
  () => isFaceVideoDeleting.value && faceVideoDeletingType.value === 'acting',
)
const isUgcFaceVideoDeleting = computed(
  () => isFaceVideoDeleting.value && faceVideoDeletingType.value === 'ugc',
)
const actingFaceVideoUploadProgress = computed(() =>
  faceVideoUploadingType.value === 'acting' ? faceVideoUploadProgress.value : null,
)
const ugcFaceVideoUploadProgress = computed(() =>
  faceVideoUploadingType.value === 'ugc' ? faceVideoUploadProgress.value : null,
)
// Per-type error scoping (P2 — without this, a UGC upload failure renders the
// red banner inside the Acting section too, since both <FaceVideoUpload> bind
// to the same shared error.ref from the composable).
const actingFaceVideoError = computed(() =>
  faceVideoErrorType.value === 'acting' ? faceVideoError.value : null,
)
const ugcFaceVideoError = computed(() =>
  faceVideoErrorType.value === 'ugc' ? faceVideoError.value : null,
)

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

// Subscription status composable (FP-2.7) — the subscription/payment UI now lives
// in the dedicated Facturation tab (FaceBillingPage). ProfileEditPage only needs
// the capability matrix for the tier-aware album / video sections.
const {
  tier: subscriptionTier,
  capabilities,
  offers,
  maxAlbumPhotos,
  fetchStatus: fetchSubscriptionStatus,
} = useSubscriptionStatus()

// Album/video lock state + completion % are computed server-side per tier. When the
// subscription tier changes mid-session — e.g. the dashboard reconciler just confirmed
// a payment — refetch them so newly unlocked media stops rendering as locked without a
// manual reload. (Navigating INTO the page is already covered by the onMounted fetch.)
// The auth guard matters: logout resets the shared caches while this page is still
// mounted (clearAuth runs before the /login navigation), flipping the tier to 'free' —
// without it the watch would fire 3 token-less GETs (3× 401) on a voluntary logout.
watch(subscriptionTier, (newTier, oldTier) => {
  if (newTier === oldTier || !authStore.isAuthenticated) return
  void Promise.all([fetchAlbumPhotos(), fetchVideos(), fetchCompletion()]).catch(() => {
    // Non-blocking — capability-driven gating already updated reactively.
  })
})

// FP-2.7.1 — the FaceVideoUpload component owns its own per-quota math from the
// capabilities prop; we only gate the presentation video locally for the
// pre-emptive Free-tier UI block.
const subscriptionCanUploadPresentationVideo = computed(
  () => (capabilities.value?.max_presentation_videos ?? 0) > 0,
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

// =============================================================
// Tabbed layout (families → sections) — Profile Tabs refonte
// =============================================================
type FamilyId = 'profil' | 'portfolio' | 'carriere' | 'compte'

interface TabSection {
  id: string
  label: string
  short?: string
  icon: Component
}
interface TabFamily {
  id: FamilyId
  label: string
  icon: Component
  sections: TabSection[]
}

const FAMILIES: TabFamily[] = [
  {
    id: 'profil',
    label: 'Profil',
    icon: User,
    sections: [
      { id: 'infos', label: 'Infos perso', icon: Contact },
      { id: 'identite', label: 'Identité', icon: Fingerprint },
      { id: 'physique', label: 'Caractéristiques physiques', short: 'Physique', icon: Ruler },
      { id: 'langues', label: 'Langues parlées', short: 'Langues', icon: Globe },
      { id: 'bio', label: 'Bio & Localisation', icon: MapPin },
    ],
  },
  {
    id: 'portfolio',
    label: 'Portfolio',
    icon: ImageIcon,
    sections: [
      { id: 'album', label: 'Album photos', short: 'Photos', icon: ImageIcon },
      { id: 'video-pres', label: 'Vidéo de présentation', short: 'Présentation', icon: Video },
      { id: 'video-acting', label: "Vidéo d'acting", short: 'Acting', icon: Clapperboard },
      { id: 'video-ugc', label: 'Vidéo UGC', short: 'UGC', icon: Sparkles },
    ],
  },
  {
    id: 'carriere',
    label: 'Carrière',
    icon: Briefcase,
    sections: [
      { id: 'categorie', label: 'Catégorie & Niche', short: 'Catégorie', icon: Tag },
      { id: 'experiences', label: 'Expériences', icon: Briefcase },
      { id: 'tarif', label: 'Tarif', icon: Coins },
    ],
  },
  {
    id: 'compte',
    label: 'Compte',
    icon: Shield,
    sections: [
      { id: 'securite', label: 'Email & mot de passe', short: 'Sécurité', icon: Lock },
      { id: 'donnees', label: 'Mes données', icon: Database },
    ],
  },
]

const FAMILY_IDS = FAMILIES.map((f) => f.id)

const activeFamily = ref<FamilyId>('profil')
const activeSectionByFamily = ref<Record<FamilyId, string>>({
  profil: 'infos',
  portfolio: 'album',
  carriere: 'categorie',
  compte: 'securite',
})
const currentFamily = computed<TabFamily>(
  () => FAMILIES.find((f) => f.id === activeFamily.value) ?? (FAMILIES[0] as TabFamily),
)
const activeSection = computed(() => activeSectionByFamily.value[activeFamily.value])

// Master/detail panel element — used to scroll it into view after a completion
// click on the stacked (mobile) layout, where the panel sits far below the sidebar.
const panelRef = ref<HTMLElement | null>(null)

function sectionExists(familyId: FamilyId, sectionId: string): boolean {
  return FAMILIES.find((f) => f.id === familyId)?.sections.some((s) => s.id === sectionId) ?? false
}
function isActiveSection(familyId: FamilyId, sectionId: string): boolean {
  return activeFamily.value === familyId && activeSection.value === sectionId
}

// Tab state is reflected in the URL (?tab=&section=) so reloads, shareable links and
// the browser back button all work. Clicks update state directly AND push the URL;
// the watcher syncs state back from the URL on external changes (back button / deep
// link). Both sides guard against no-op writes, so there is no update loop.
function applyRouteToState(): void {
  const tab = route.query.tab
  if (typeof tab === 'string' && (FAMILY_IDS as string[]).includes(tab)) {
    activeFamily.value = tab as FamilyId
  }
  const section = route.query.section
  if (typeof section === 'string' && sectionExists(activeFamily.value, section)) {
    activeSectionByFamily.value[activeFamily.value] = section
  }
}
function syncUrlFromState(): void {
  const tab = activeFamily.value
  const section = activeSection.value
  if (route.query.tab === tab && route.query.section === section) return
  void router.push({ query: { ...route.query, tab, section } })
}
function navigateTab(familyId: FamilyId, sectionId?: string): void {
  activeFamily.value = familyId
  if (sectionId) activeSectionByFamily.value[familyId] = sectionId
  syncUrlFromState()
}
function selectFamily(id: FamilyId): void {
  navigateTab(id)
}
function selectSection(id: string): void {
  navigateTab(activeFamily.value, id)
}

watch(() => [route.query.tab, route.query.section], applyRouteToState, { immediate: true })

// Completion criteria → (family, section). Drives both the missing-item navigation
// and the amber "à compléter" dot on the section nav. `profile_photo` lives in the
// sidebar (no tab) and is handled separately by flashing the photo card.
const COMPLETION_TO_TAB: Record<string, { family: FamilyId; section: string }> = {
  presentation_video: { family: 'portfolio', section: 'video-pres' },
  acting_video: { family: 'portfolio', section: 'video-acting' },
  bio: { family: 'profil', section: 'bio' },
  ville: { family: 'profil', section: 'bio' },
  langues: { family: 'profil', section: 'langues' },
  // Backend emits the key `categories` (plural) — see Face.php profile_completion_missing.
  categories: { family: 'carriere', section: 'categorie' },
  tarifs: { family: 'carriere', section: 'tarif' },
  whatsapp_number: { family: 'profil', section: 'identite' },
}
const incompleteSectionKeys = computed(() => {
  const set = new Set<string>()
  for (const item of completionMissingItems.value ?? []) {
    const target = COMPLETION_TO_TAB[item.key]
    if (target) set.add(`${target.family}:${target.section}`)
  }
  return set
})
function isSectionIncomplete(familyId: FamilyId, sectionId: string): boolean {
  return incompleteSectionKeys.value.has(`${familyId}:${sectionId}`)
}

// Sidebar "photo" completion item has no tab — flash + scroll the photo card instead.
const highlightPhotoCard = ref(false)
let photoFlashTimer: ReturnType<typeof setTimeout> | null = null
function flashPhotoCard(): void {
  document
    .getElementById('section-profile-photo')
    ?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  highlightPhotoCard.value = true
  if (photoFlashTimer) clearTimeout(photoFlashTimer)
  photoFlashTimer = setTimeout(() => {
    highlightPhotoCard.value = false
  }, 1600)
}

// Sidebar "Résumé" — read-only digest derived from the section composables.
const summaryAge = computed<number | null>(() => {
  const dob = personalInfo.value?.date_naissance
  if (!dob) return null
  const d = new Date(dob)
  if (Number.isNaN(d.getTime())) return null
  const now = new Date()
  let age = now.getFullYear() - d.getFullYear()
  const monthDelta = now.getMonth() - d.getMonth()
  if (monthDelta < 0 || (monthDelta === 0 && now.getDate() < d.getDate())) age -= 1
  return age >= 0 ? age : null
})
const tarifDisplay = computed(() => {
  const t = tarifsInfo.value?.tarif_journalier
  return typeof t === 'number' ? `${new Intl.NumberFormat('fr-FR').format(t)} FCFA` : '—'
})

// Fetch profile, album, videos, bio/location, physical characteristics, category/niche, experiences, tarifs, availability, and completion on mount
onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    fetchAlbumPhotos(),
    fetchVideoInfo(),
    fetchVideos(),
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

  // Deep-link from the WhatsApp banner CTA → open Profil → Identité and focus the field.
  if (route.query.focus === 'whatsapp') {
    activeFamily.value = 'profil'
    activeSectionByFamily.value.profil = 'identite'
    await router.replace({ path: route.path, query: { tab: 'profil', section: 'identite' } })
    await nextTick()
    document.getElementById('whatsapp-number')?.focus()
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
 * Handle face video upload (acting or ugc).
 */
async function handleAddFaceVideo(
  type: 'acting' | 'ugc',
  file: File,
): Promise<void> {
  successMessage.value = null
  const result = await uploadFaceVideo(type, file)

  if (result.success) {
    if (result.message) {
      successMessage.value = result.message
    }
    if (type === 'acting') {
      await fetchCompletion() // acting_video is a completion criterion
    }
  }
}

/**
 * Handle face video delete (acting or ugc).
 */
async function handleDeleteFaceVideo(videoId: string): Promise<void> {
  successMessage.value = null
  // Capture the type before delete so we know whether to refresh completion.
  const target = faceVideos.value.find((v) => v.id === videoId)
  const wasActing = target?.type === 'acting'

  const result = await deleteFaceVideo(videoId)
  if (result.success) {
    if (result.message) {
      successMessage.value = result.message
    }
    if (wasActing) {
      await fetchCompletion()
    }
  }
}

/**
 * Navigate to the /pricing page (FP-2.13.1 entry point for tier purchase).
 */
function onNavigatePricing(): void {
  router.push({ name: 'pricing' })
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
 * Handle click on a missing item in the completion indicator.
 * Opens the matching family + section (tab) — except `profile_photo`, which lives
 * in the sidebar and just flashes the photo card.
 */
async function handleCompletionItemClick(itemKey: string): Promise<void> {
  if (itemKey === 'profile_photo') {
    flashPhotoCard()
    return
  }
  const target = COMPLETION_TO_TAB[itemKey]
  if (!target) return
  navigateTab(target.family, target.section)
  // On the stacked (mobile) layout the completion card sits far above the tab panel,
  // so switching section doesn't bring it into view — scroll the panel into view.
  // No-op on desktop (side-by-side, lg ≥ 1024px), where everything is already visible.
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    await nextTick()
    panelRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
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
          <div
            id="section-profile-photo"
            class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-shadow"
            :class="{ 'ring-2 ring-teal-500 ring-offset-2': highlightPhotoCard }"
          >
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

          <!-- Profile completion card (à conserver intacte) -->
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

          <!-- Statut card — Disponibilité + Tarif (lecture seule) + Note -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" data-testid="status-card">
            <h2 class="text-base font-medium text-gray-900 mb-4">Statut</h2>
            <div class="space-y-5">
              <div>
                <AvailabilityToggle
                  :is-available="availabilityInfo?.is_available ?? true"
                  :is-loading="isAvailabilityLoading"
                  :is-saving="isAvailabilitySaving"
                  :error="availabilityError"
                  @toggle="handleAvailabilityToggle"
                />
              </div>

              <!-- Tarif (lecture seule — l'édition vit dans Carrière → Tarif) -->
              <div class="border-t border-gray-100 pt-4 flex items-center justify-between gap-3">
                <span class="text-sm text-gray-500 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-teal-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75" />
                  </svg>
                  Tarif / jour
                </span>
                <span class="text-sm font-semibold text-gray-900" data-testid="sidebar-tarif-value">{{ tarifDisplay }}</span>
              </div>

              <!-- Note -->
              <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-teal-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                  </svg>
                  Ma note
                </h3>
                <RatingDisplay
                  :average-rating="profile?.average_rating ?? null"
                  :review-count="profile?.ratings_count ?? 0"
                />
              </div>
            </div>
          </div>

          <!-- Résumé card (lecture seule) -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" data-testid="resume-card">
            <h2 class="text-base font-medium text-gray-900 mb-4">Résumé</h2>
            <dl class="space-y-3 text-sm">
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Sexe</dt>
                <dd class="text-gray-900 font-medium text-right">{{ personalInfo?.sexe ?? '—' }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Âge</dt>
                <dd class="text-gray-900 font-medium text-right">{{ summaryAge !== null ? `${summaryAge} ans` : '—' }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Taille</dt>
                <dd class="text-gray-900 font-medium text-right">{{ physicalCharacteristicsInfo?.taille ? `${physicalCharacteristicsInfo.taille} cm` : '—' }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Nationalité</dt>
                <dd class="text-gray-900 font-medium text-right">{{ personalInfo?.nationalite ?? '—' }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Ville</dt>
                <dd class="text-gray-900 font-medium text-right">{{ bioLocationInfo?.ville ?? '—' }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Langues</dt>
                <dd class="text-gray-900 font-medium text-right max-w-[150px]">
                  {{ languesInfo?.langues && languesInfo.langues.length ? languesInfo.langues.join(', ') : '—' }}
                </dd>
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

        <!-- Family tabs (underlined) -->
        <nav
          role="tablist"
          aria-label="Familles du profil"
          class="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 px-1"
          data-testid="profile-family-tabs"
        >
          <button
            v-for="f in FAMILIES"
            :key="f.id"
            type="button"
            role="tab"
            :aria-selected="activeFamily === f.id"
            :data-testid="`family-tab-${f.id}`"
            class="relative -mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-3.5 py-2.5 text-sm font-semibold"
            :class="
              activeFamily === f.id
                ? 'border-teal-600 text-teal-600'
                : 'border-transparent text-slate-500 hover:text-teal-600'
            "
            @click="selectFamily(f.id)"
          >
            <component :is="f.icon" class="h-[17px] w-[17px]" />
            {{ f.label }}
          </button>
        </nav>

        <!-- Master / detail panel -->
        <div ref="panelRef" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="grid grid-cols-1 md:grid-cols-[224px_minmax(0,1fr)]">
            <!-- Section nav for the active family -->
            <nav
              class="flex gap-0.5 overflow-x-auto border-b border-gray-100 p-3.5 md:flex-col md:border-b-0 md:border-r"
              :aria-label="`Sections ${currentFamily.label}`"
            >
              <button
                v-for="s in currentFamily.sections"
                :key="s.id"
                type="button"
                :data-testid="`section-nav-${s.id}`"
                class="inline-flex items-center gap-2.5 whitespace-nowrap rounded-[10px] px-3 py-2 text-left text-[13.5px] font-semibold"
                :class="
                  activeSection === s.id ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-teal-50'
                "
                @click="selectSection(s.id)"
              >
                <component :is="s.icon" class="h-[17px] w-[17px] flex-shrink-0" />
                <span class="flex-1">{{ s.short || s.label }}</span>
                <span
                  v-if="isSectionIncomplete(activeFamily, s.id)"
                  class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-600"
                  title="À compléter"
                  :data-testid="`section-dot-${s.id}`"
                />
              </button>
            </nav>

            <!-- Active section content (all sections kept mounted via v-show) -->
            <div class="min-w-0">
              <!-- PROFIL -->
              <div v-show="isActiveSection('profil', 'infos')" class="p-6 md:p-7" data-testid="panel-infos">
                <BasicInfoSection flat />
              </div>

              <div v-show="isActiveSection('profil', 'identite')" class="p-6 md:p-7" data-testid="panel-identite">
                <h2 class="text-lg font-semibold text-slate-900">Identité</h2>
                <p class="mt-1 mb-6 text-sm text-slate-500">
                  Sexe, date de naissance, nationalité et pays de résidence.
                </p>
                <div v-if="isPersonalInfoLoading" class="flex justify-center py-8">
                  <Loader2 class="h-6 w-6 animate-spin text-teal-600" />
                </div>
                <PersonalInfoForm
                  v-else
                  :personal-info="personalInfo"
                  :is-saving="isPersonalInfoSaving"
                  :error="personalInfoError"
                  @save="handlePersonalInfoSave"
                />
              </div>

              <div v-show="isActiveSection('profil', 'physique')" class="p-6 md:p-7" data-testid="panel-physique">
                <h2 class="text-lg font-semibold text-slate-900">Caractéristiques physiques</h2>
                <p class="mt-1 mb-6 text-sm text-slate-500">
                  Indiquez votre taille et poids pour aider les producteurs à trouver des talents correspondant à leurs besoins.
                </p>
                <PhysicalCharacteristicsForm
                  :physical-characteristics-info="physicalCharacteristicsInfo"
                  :is-saving="isPhysicalCharacteristicsSaving"
                  :error="physicalCharacteristicsError"
                  @save="handlePhysicalCharacteristicsSave"
                />
              </div>

              <div v-show="isActiveSection('profil', 'langues')" class="p-6 md:p-7" data-testid="panel-langues">
                <div v-if="isLanguesLoading" class="flex justify-center py-8">
                  <Loader2 class="h-6 w-6 animate-spin text-teal-600" />
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

              <div v-show="isActiveSection('profil', 'bio')" class="p-6 md:p-7" data-testid="panel-bio">
                <h2 class="text-lg font-semibold text-slate-900">Bio &amp; Localisation</h2>
                <p class="mt-1 mb-6 text-sm text-slate-500">
                  Partagez votre parcours et indiquez votre localisation aux producteurs.
                </p>
                <BioLocationForm
                  :bio-location-info="bioLocationInfo"
                  :is-saving="isBioLocationSaving"
                  :error="bioLocationError"
                  @save="handleBioLocationSave"
                />
              </div>

              <!-- PORTFOLIO -->
              <div v-show="isActiveSection('portfolio', 'album')" class="p-6 md:p-7" data-testid="panel-album">
                <h2 class="text-lg font-semibold text-slate-900">Album photos</h2>
                <MediaQuotaUpsell
                  media-key="max_album_photos"
                  description="Montrez votre polyvalence aux producteurs avec votre album photos."
                  :capabilities="capabilities"
                  :current-tier="subscriptionTier"
                  :offers="offers"
                />
                <PhotoAlbumGrid
                  :photos="albumPhotos"
                  :is-loading="isAlbumLoading"
                  :is-deleting="isAlbumDeleting"
                  :can-add-more="canAddMore && !isFullByEntitlement"
                  :max-album-photos="maxAlbumPhotos"
                  @delete="handleAlbumDelete"
                  @add-click="handleAlbumAddClick"
                />
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

              <div v-show="isActiveSection('portfolio', 'video-pres')" class="p-6 md:p-7" data-testid="panel-video-pres">
                <h2 class="text-lg font-semibold text-slate-900">Vidéo de présentation</h2>
                <MediaQuotaUpsell
                  media-key="max_presentation_videos"
                  description="Présentez-vous en vidéo pour faire une première impression aux producteurs."
                  :capabilities="capabilities"
                  :current-tier="subscriptionTier"
                  :offers="offers"
                />
                <PresentationVideoUpload
                  :video-info="videoInfo"
                  :is-uploading="isVideoUploading"
                  :is-deleting="isVideoDeleting"
                  :error="videoError"
                  :upload-progress="uploadProgress"
                  :can-upload="subscriptionCanUploadPresentationVideo"
                  @upload="handleVideoUpload"
                  @delete="handleVideoDelete"
                  @navigate-pricing="onNavigatePricing"
                />
              </div>

              <div v-show="isActiveSection('portfolio', 'video-acting')" class="p-6 md:p-7" data-testid="panel-video-acting">
                <h2 class="text-lg font-semibold text-slate-900">Vidéo d'acting</h2>
                <MediaQuotaUpsell
                  media-key="max_acting_videos"
                  description="Démontrez votre talent d'acteur en vidéo aux producteurs."
                  :capabilities="capabilities"
                  :current-tier="subscriptionTier"
                  :offers="offers"
                />
                <FaceVideoUpload
                  type="acting"
                  :videos="actingVideos"
                  :max-for-type="capabilities?.max_acting_videos ?? 0"
                  :is-uploading="isActingFaceVideoUploading"
                  :is-deleting="isActingFaceVideoDeleting"
                  :error="actingFaceVideoError"
                  :upload-progress="actingFaceVideoUploadProgress"
                  @upload="(file) => handleAddFaceVideo('acting', file)"
                  @delete="handleDeleteFaceVideo"
                  @navigate-pricing="onNavigatePricing"
                />
              </div>

              <div v-show="isActiveSection('portfolio', 'video-ugc')" class="p-6 md:p-7" data-testid="panel-video-ugc">
                <h2 class="text-lg font-semibold text-slate-900">Vidéo UGC</h2>
                <MediaQuotaUpsell
                  media-key="max_ugc_videos"
                  description="Montrez votre style avec une vidéo modèle UGC (User-Generated Content)."
                  :capabilities="capabilities"
                  :current-tier="subscriptionTier"
                  :offers="offers"
                />
                <FaceVideoUpload
                  type="ugc"
                  :videos="ugcVideos"
                  :max-for-type="capabilities?.max_ugc_videos ?? 0"
                  :is-uploading="isUgcFaceVideoUploading"
                  :is-deleting="isUgcFaceVideoDeleting"
                  :error="ugcFaceVideoError"
                  :upload-progress="ugcFaceVideoUploadProgress"
                  @upload="(file) => handleAddFaceVideo('ugc', file)"
                  @delete="handleDeleteFaceVideo"
                  @navigate-pricing="onNavigatePricing"
                />
              </div>

              <!-- CARRIÈRE -->
              <div v-show="isActiveSection('carriere', 'categorie')" class="p-6 md:p-7" data-testid="panel-categorie">
                <h2 class="text-lg font-semibold text-slate-900">Catégorie &amp; Niche</h2>
                <p class="mt-1 mb-6 text-sm text-slate-500">
                  Sélectionnez votre catégorie et niche pour aider les producteurs à vous trouver selon votre spécialisation.
                </p>
                <div v-if="isCategoryNicheLoading" class="flex justify-center py-8">
                  <Loader2 class="h-6 w-6 animate-spin text-teal-600" />
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

              <div v-show="isActiveSection('carriere', 'experiences')" class="p-6 md:p-7" data-testid="panel-experiences">
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

              <div v-show="isActiveSection('carriere', 'tarif')" class="p-6 md:p-7" data-testid="panel-tarif">
                <h2 class="text-lg font-semibold text-slate-900">Tarif</h2>
                <p class="mt-1 mb-6 text-sm text-slate-500">
                  Définissez votre tarif journalier — il est visible par les producteurs et affiché en lecture seule dans la barre latérale.
                </p>
                <TarifsForm
                  :tarifs-info="tarifsInfo"
                  :is-saving="isTarifsSaving"
                  :error="tarifsError"
                  @save="handleTarifsSave"
                />
              </div>

              <!-- COMPTE -->
              <div v-show="isActiveSection('compte', 'securite')" class="p-6 md:p-7 space-y-4" data-testid="panel-securite">
                <EmailChangeForm />
                <div class="border-t border-gray-100 pt-4">
                  <PasswordChangeForm />
                </div>
              </div>

              <div v-show="isActiveSection('compte', 'donnees')" class="p-6 md:p-7" data-testid="panel-donnees">
                <DataPrivacySection />
              </div>
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

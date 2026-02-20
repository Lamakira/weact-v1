<script setup lang="ts">
import { onMounted, ref, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  AlertCircle,
  Loader2,
  Trash2,
  Save,
  MapPin,
  Star,
  Briefcase,
  User,
  X,
  Camera,
  Video,
  ChevronLeft,
  ChevronRight,
  Ruler,
  Wallet,
  Play,
  Clock,
} from 'lucide-vue-next'
import { useAdminFaces } from '@/features/admin/composables/useAdminFaces'
import type { UpdateAdminFaceForm, AdminFacePhoto } from '@/features/admin/services/adminFacesApi'
import { getCategoryLabels, getNicheLabels, getCategoryColor } from '@/features/admin/utils/faceLabels'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const { face, isLoading, error, fetchFace, updateFace, toggleActive, deleteFace } = useAdminFaces()
const toast = useToast()

const faceId = computed(() => Number(route.params.id))
const isEditing = ref(false)
const editForm = ref<UpdateAdminFaceForm>({})
const editErrors = ref<Record<string, string[]>>({})
const isToggling = ref(false)
const showToggleModal = ref(false)
const showDeleteModal = ref(false)

// Lightbox state
const lightboxModalRef = ref<HTMLDivElement | null>(null)
const lightboxIndex = ref<number | null>(null)
const lightboxPhoto = computed((): AdminFacePhoto | null => {
  if (lightboxIndex.value === null || !face.value) return null
  return face.value.photos[lightboxIndex.value] ?? null
})

// Video modal state
const videoModalUrl = ref<string | null>(null)
const videoModalTitle = ref('')

onMounted(() => {
  fetchFace(faceId.value)
})

function startEdit(): void {
  if (!face.value) return
  editForm.value = {
    nom: face.value.nom,
    prenom: face.value.prenom,
    username: face.value.username,
    bio: face.value.bio,
    ville: face.value.ville,
    quartier: face.value.quartier,
    pays: face.value.pays,
    categories: face.value.categories?.map((c: { value: string }) => c.value) ?? [],
    niches: face.value.niches?.map((n: { value: string }) => n.value) ?? [],
    is_available: face.value.is_available,
  }
  editErrors.value = {}
  isEditing.value = true
}

function cancelEdit(): void {
  isEditing.value = false
  editErrors.value = {}
}

async function saveEdit(): Promise<void> {
  editErrors.value = {}

  const result = await updateFace(faceId.value, editForm.value)
  if (result.success) {
    isEditing.value = false
    toast.success(result.message ?? 'Profil mis à jour')
  } else {
    editErrors.value = result.errors ?? {}
  }
}

async function confirmToggleActive(): Promise<void> {
  showToggleModal.value = false
  isToggling.value = true
  const result = await toggleActive(faceId.value)
  isToggling.value = false

  if (result.success) {
    const action = face.value?.is_active !== false ? 'activé' : 'désactivé'
    toast.success(result.message ?? `Compte ${action}`)
  }
}

async function confirmDelete(): Promise<void> {
  showDeleteModal.value = false
  const result = await deleteFace(faceId.value)
  if (result.success) {
    toast.success(result.message ?? 'Profil supprimé avec succès')
    router.push({ name: 'admin-faces-list' })
  } else {
    toast.error(result.message ?? 'Erreur lors de la suppression')
  }
}

function goBack(): void {
  router.push({ name: 'admin-faces-list' })
}

// Lightbox functions
function openLightbox(index: number): void {
  lightboxIndex.value = index
  nextTick(() => {
    lightboxModalRef.value?.focus()
  })
}

function closeLightbox(): void {
  lightboxIndex.value = null
}

function prevPhoto(): void {
  if (lightboxIndex.value === null || !face.value) return
  lightboxIndex.value =
    lightboxIndex.value > 0 ? lightboxIndex.value - 1 : face.value.photos.length - 1
}

function nextPhoto(): void {
  if (lightboxIndex.value === null || !face.value) return
  lightboxIndex.value =
    lightboxIndex.value < face.value.photos.length - 1 ? lightboxIndex.value + 1 : 0
}

function handleLightboxKeydown(event: KeyboardEvent): void {
  if (lightboxIndex.value === null) return
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

// Video modal functions
function openVideoModal(url: string, title: string): void {
  videoModalUrl.value = url
  videoModalTitle.value = title
}

function closeVideoModal(): void {
  videoModalUrl.value = null
  videoModalTitle.value = ''
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back button -->
    <button
      @click="goBack"
      class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
      data-testid="back-button"
    >
      <ArrowLeft class="h-4 w-4" />
      Retour à la liste
    </button>

    <!-- Loading State -->
    <div
      v-if="isLoading && !face"
      class="flex items-center justify-center py-12"
      data-testid="loading-state"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Error State -->
    <div
      v-if="error && !face"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="error-message"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Face Detail -->
    <template v-if="face">
      <!-- Header with actions -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="flex items-center gap-4">
          <img
            v-if="face.thumbnail_url || face.profile_photo_url"
            :src="face.thumbnail_url ?? face.profile_photo_url ?? undefined"
            :alt="`${face.prenom} ${face.nom}`"
            class="h-16 w-16 rounded-full object-cover shrink-0"
          />
          <div v-else class="h-16 w-16 rounded-full bg-primary-100 flex items-center justify-center text-xl font-bold text-primary-700 shrink-0">
            {{ (face.prenom?.[0] ?? '').toUpperCase() }}{{ (face.nom?.[0] ?? '').toUpperCase() }}
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900" data-testid="face-name">
              {{ face.prenom }} {{ face.nom }}
            </h1>
            <div class="flex items-center gap-2">
              <p class="text-sm text-gray-500">@{{ face.username }}</p>
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="face.is_active !== false ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                data-testid="status-badge"
              >
                {{ face.is_active !== false ? 'Actif' : 'Inactif' }}
              </span>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <template v-if="!isEditing">
            <button
              @click="showToggleModal = true"
              :disabled="isToggling"
              class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-colors disabled:opacity-50"
              :class="face.is_active !== false
                ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100'
                : 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100'"
              data-testid="toggle-active-button"
            >
              {{ face.is_active !== false ? 'Désactiver' : 'Activer' }}
            </button>
            <button
              @click="startEdit"
              class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors"
              data-testid="edit-button"
            >
              Modifier
            </button>
            <button
              @click="showDeleteModal = true"
              class="inline-flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition-colors"
              data-testid="delete-button"
            >
              <Trash2 class="h-4 w-4" />
              Supprimer
            </button>
          </template>
          <template v-else>
            <button
              @click="saveEdit"
              :disabled="isLoading"
              class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors disabled:opacity-50"
              data-testid="save-button"
            >
              <Save class="h-4 w-4" />
              Enregistrer
            </button>
            <button
              @click="cancelEdit"
              class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              data-testid="cancel-button"
            >
              <X class="h-4 w-4" />
              Annuler
            </button>
          </template>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Section 1: Personal Info -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="personal-info-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <User class="h-5 w-5 text-gray-400" />
            Informations personnelles
          </h2>

          <template v-if="!isEditing">
            <dl class="space-y-3">
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Prénom</dt>
                <dd class="text-sm font-medium text-gray-900">{{ face.prenom }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Nom</dt>
                <dd class="text-sm font-medium text-gray-900">{{ face.nom }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Username</dt>
                <dd class="text-sm font-medium text-gray-900">@{{ face.username }}</dd>
              </div>
              <div class="flex justify-between items-start">
                <dt class="text-sm text-gray-500">Catégories</dt>
                <dd class="text-sm font-medium text-gray-900 text-right">{{ getCategoryLabels(face.categories) }}</dd>
              </div>
              <div class="flex justify-between items-start">
                <dt class="text-sm text-gray-500">Niches</dt>
                <dd class="text-sm font-medium text-gray-900 text-right">{{ getNicheLabels(face.niches) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Disponibilité</dt>
                <dd>
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="face.is_available ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  >
                    {{ face.is_available ? 'Disponible' : 'Indisponible' }}
                  </span>
                </dd>
              </div>
            </dl>
          </template>

          <!-- Edit Form -->
          <template v-else>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prénom</label>
                <input
                  v-model="editForm.prenom"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-prenom"
                />
                <p v-if="editErrors.prenom" class="mt-1 text-xs text-red-600">{{ editErrors.prenom[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                <input
                  v-model="editForm.nom"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-nom"
                />
                <p v-if="editErrors.nom" class="mt-1 text-xs text-red-600">{{ editErrors.nom[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input
                  v-model="editForm.username"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-username"
                />
                <p v-if="editErrors.username" class="mt-1 text-xs text-red-600">{{ editErrors.username[0] }}</p>
              </div>
              <div data-testid="edit-categories">
                <label class="block text-sm font-medium text-gray-700 mb-2">Catégories</label>
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="cat in [
                      { value: 'acteur', label: 'Acteur' },
                      { value: 'influenceur', label: 'Influenceur' },
                      { value: 'createur', label: 'Créateur de contenu' },
                      { value: 'mannequin', label: 'Mannequin' },
                      { value: 'figurant', label: 'Figurant' },
                      { value: 'modele_photo', label: 'Modèle Photo' },
                      { value: 'egerie', label: 'Égérie' },
                    ]"
                    :key="cat.value"
                    class="inline-flex items-center gap-1.5 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      :value="cat.value"
                      v-model="editForm.categories"
                      class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="text-sm text-gray-700">{{ cat.label }}</span>
                  </label>
                </div>
              </div>
              <div data-testid="edit-niches">
                <label class="block text-sm font-medium text-gray-700 mb-2">Niches</label>
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="niche in [
                      { value: 'beaute', label: 'Beauté' },
                      { value: 'nourriture', label: 'Nourriture' },
                      { value: 'decouverte', label: 'Découverte' },
                      { value: 'mode', label: 'Mode' },
                    ]"
                    :key="niche.value"
                    class="inline-flex items-center gap-1.5 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      :value="niche.value"
                      v-model="editForm.niches"
                      class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="text-sm text-gray-700">{{ niche.label }}</span>
                  </label>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <input
                  v-model="editForm.is_available"
                  type="checkbox"
                  id="edit-availability"
                  class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  data-testid="edit-availability"
                />
                <label for="edit-availability" class="text-sm text-gray-700">Disponible</label>
              </div>
            </div>
          </template>
        </section>

        <!-- Section 2: Bio & Location -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="bio-location-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <MapPin class="h-5 w-5 text-gray-400" />
            Bio & Localisation
          </h2>

          <template v-if="!isEditing">
            <dl class="space-y-3">
              <div>
                <dt class="text-sm text-gray-500 mb-1">Bio</dt>
                <dd class="text-sm text-gray-900">{{ face.bio || '—' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Ville</dt>
                <dd class="text-sm font-medium text-gray-900">{{ face.ville || '—' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Quartier</dt>
                <dd class="text-sm font-medium text-gray-900">{{ face.quartier || '—' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Pays</dt>
                <dd class="text-sm font-medium text-gray-900">{{ face.pays || '—' }}</dd>
              </div>
            </dl>
          </template>

          <template v-else>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                <textarea
                  v-model="editForm.bio"
                  rows="4"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-bio"
                />
                <p v-if="editErrors.bio" class="mt-1 text-xs text-red-600">{{ editErrors.bio[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                <input
                  v-model="editForm.ville"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-ville"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quartier</label>
                <input
                  v-model="editForm.quartier"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-quartier"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Pays</label>
                <input
                  v-model="editForm.pays"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-pays"
                />
              </div>
            </div>
          </template>
        </section>
      </div>

      <!-- Section 3: Photos (full width) -->
      <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="photos-section">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Camera class="h-5 w-5 text-gray-400" />
          Photos
          <span v-if="face.photos_count" class="text-sm font-normal text-gray-500">({{ face.photos_count }})</span>
        </h2>

        <template v-if="face.photos && face.photos.length > 0">
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <button
              v-for="(photo, index) in face.photos"
              :key="photo.id"
              type="button"
              class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100"
              @click="openLightbox(index)"
              :data-testid="`photo-${index}`"
            >
              <img
                :src="photo.thumbnail_url || photo.photo_url"
                :alt="`Photo ${index + 1}`"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
              />
              <div class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20" />
            </button>
          </div>
        </template>

        <div v-else class="text-center py-8 text-gray-400" data-testid="photos-empty">
          <Camera class="mx-auto h-10 w-10 mb-2" />
          <p class="text-sm">Aucune photo importée</p>
        </div>
      </section>

      <!-- Section 4: Videos -->
      <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="videos-section">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Video class="h-5 w-5 text-gray-400" />
          Vidéos
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Presentation Video -->
          <div class="rounded-lg border border-gray-200 p-4" data-testid="presentation-video">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Vidéo de présentation</h3>
            <template v-if="face.presentation_video_url">
              <button
                type="button"
                class="relative w-full aspect-video rounded-lg bg-gray-100 overflow-hidden group"
                @click="openVideoModal(face.presentation_video_url!, 'Vidéo de présentation')"
              >
                <img
                  v-if="face.presentation_video_thumbnail_url"
                  :src="face.presentation_video_thumbnail_url"
                  alt="Miniature vidéo de présentation"
                  class="h-full w-full object-cover"
                />
                <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition-colors">
                  <div class="h-12 w-12 rounded-full bg-white/90 flex items-center justify-center">
                    <Play class="h-6 w-6 text-gray-900 ml-0.5" />
                  </div>
                </div>
              </button>
            </template>
            <div v-else class="text-center py-6 text-gray-400">
              <Video class="mx-auto h-8 w-8 mb-1" />
              <p class="text-xs">Pas de vidéo</p>
            </div>
          </div>

          <!-- Acting Video -->
          <div class="rounded-lg border border-gray-200 p-4" data-testid="acting-video">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Vidéo de casting</h3>
            <template v-if="face.acting_video_url">
              <button
                type="button"
                class="relative w-full aspect-video rounded-lg bg-gray-100 overflow-hidden group"
                @click="openVideoModal(face.acting_video_url!, 'Vidéo de casting')"
              >
                <img
                  v-if="face.acting_video_thumbnail_url"
                  :src="face.acting_video_thumbnail_url"
                  alt="Miniature vidéo de casting"
                  class="h-full w-full object-cover"
                />
                <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition-colors">
                  <div class="h-12 w-12 rounded-full bg-white/90 flex items-center justify-center">
                    <Play class="h-6 w-6 text-gray-900 ml-0.5" />
                  </div>
                </div>
              </button>
            </template>
            <div v-else class="text-center py-6 text-gray-400">
              <Video class="mx-auto h-8 w-8 mb-1" />
              <p class="text-xs">Pas de vidéo</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 5: Experiences -->
      <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="experiences-section">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Briefcase class="h-5 w-5 text-gray-400" />
          Expériences professionnelles
          <span v-if="face.experiences_count" class="text-sm font-normal text-gray-500">({{ face.experiences_count }})</span>
        </h2>

        <template v-if="face.experiences && face.experiences.length > 0">
          <div class="space-y-4">
            <div
              v-for="exp in face.experiences"
              :key="exp.id"
              class="border-l-2 border-primary-200 pl-4 py-1"
              :data-testid="`experience-${exp.id}`"
            >
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ exp.titre }}</h3>
                <span
                  v-if="exp.is_ongoing"
                  class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"
                >
                  En cours
                </span>
              </div>
              <p v-if="exp.description" class="mt-1 text-sm text-gray-600">{{ exp.description }}</p>
              <p class="mt-1 text-xs text-gray-400 flex items-center gap-1">
                <Clock class="h-3 w-3" />
                {{ exp.formatted_period }}
              </p>
            </div>
          </div>
        </template>

        <div v-else class="text-center py-8 text-gray-400" data-testid="experiences-empty">
          <Briefcase class="mx-auto h-10 w-10 mb-2" />
          <p class="text-sm">Aucune expérience renseignée</p>
        </div>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Section 6: Physical Characteristics -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="physical-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Ruler class="h-5 w-5 text-gray-400" />
            Caractéristiques physiques
          </h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Taille</dt>
              <dd class="text-sm font-medium text-gray-900">
                {{ face.taille ? `${face.taille} cm` : 'Non renseigné' }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Poids</dt>
              <dd class="text-sm font-medium text-gray-900">
                {{ face.poids ? `${face.poids} kg` : 'Non renseigné' }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Section 7: Tariffs -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="tariffs-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Wallet class="h-5 w-5 text-gray-400" />
            Tarifs
          </h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Tarif horaire</dt>
              <dd class="text-sm font-medium text-gray-900">
                {{ face.formatted_tarif_horaire || 'Non renseigné' }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Tarif journalier</dt>
              <dd class="text-sm font-medium text-gray-900">
                {{ face.formatted_tarif_journalier || 'Non renseigné' }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Stats & Profile Completion -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="stats-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Star class="h-5 w-5 text-gray-400" />
            Statistiques & Profil
          </h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Note moyenne</dt>
              <dd class="text-sm font-medium text-gray-900">
                {{ face.average_rating ? `${face.average_rating}/5` : '—' }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Nombre d'avis</dt>
              <dd class="text-sm font-medium text-gray-900">{{ face.ratings_count ?? 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Profil complété</dt>
              <dd class="text-sm font-medium" :class="face.profile_completion_is_complete ? 'text-green-600' : 'text-amber-600'">
                {{ face.profile_completion_percentage }}%
              </dd>
            </div>
          </dl>
          <template v-if="face.profile_completion_missing && face.profile_completion_missing.length > 0">
            <div class="mt-3 pt-3 border-t border-gray-100">
              <p class="text-xs text-gray-500 mb-1">Éléments manquants :</p>
              <ul class="space-y-1">
                <li
                  v-for="item in face.profile_completion_missing"
                  :key="item.key"
                  class="text-xs text-amber-600"
                >
                  &bull; {{ item.label }}
                </li>
              </ul>
            </div>
          </template>
        </section>

        <!-- Metadata -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="metadata-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Briefcase class="h-5 w-5 text-gray-400" />
            Métadonnées
          </h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">ID</dt>
              <dd class="text-sm font-medium text-gray-900">#{{ face.id }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Date de création</dt>
              <dd class="text-sm font-medium text-gray-900">
                {{ new Date(face.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) }}
              </dd>
            </div>
          </dl>
        </section>
      </div>
    </template>

    <!-- Photo Lightbox Modal -->
    <Teleport to="body">
      <div
        v-if="lightboxPhoto"
        ref="lightboxModalRef"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4 outline-none"
        tabindex="0"
        @click.self="closeLightbox"
        @keydown="handleLightboxKeydown"
        data-testid="lightbox-modal"
      >
        <button
          type="button"
          class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="closeLightbox"
        >
          <X class="h-6 w-6" />
        </button>

        <button
          v-if="face && face.photos.length > 1"
          type="button"
          class="absolute left-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="prevPhoto"
        >
          <ChevronLeft class="h-8 w-8" />
        </button>

        <img
          :src="lightboxPhoto.photo_url"
          :alt="`Photo ${lightboxIndex! + 1}`"
          class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain"
        />

        <button
          v-if="face && face.photos.length > 1"
          type="button"
          class="absolute right-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="nextPhoto"
        >
          <ChevronRight class="h-8 w-8" />
        </button>

        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-4 py-2 text-sm text-white">
          {{ lightboxIndex! + 1 }} / {{ face?.photos.length }}
        </div>
      </div>
    </Teleport>

    <!-- Toggle Active Confirmation Modal -->
    <ConfirmModal
      v-if="face"
      :is-open="showToggleModal"
      :title="face.is_active !== false ? 'Désactiver ce compte' : 'Réactiver ce compte'"
      :message="face.is_active !== false
        ? `Êtes-vous sûr de vouloir désactiver le compte de ${face.prenom} ${face.nom} ? L'utilisateur ne pourra plus se connecter.`
        : `Êtes-vous sûr de vouloir réactiver le compte de ${face.prenom} ${face.nom} ?`"
      :confirm-text="face.is_active !== false ? 'Désactiver' : 'Réactiver'"
      variant="warning"
      @confirm="confirmToggleActive"
      @cancel="showToggleModal = false"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      v-if="face"
      :is-open="showDeleteModal"
      :title="'Supprimer ce profil'"
      :message="`Êtes-vous sûr de vouloir supprimer le profil de ${face.prenom} ${face.nom} ? Cette action est irréversible.`"
      confirm-text="Supprimer"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDeleteModal = false"
    />

    <!-- Video Player Modal -->
    <Teleport to="body">
      <div
        v-if="videoModalUrl"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
        @click.self="closeVideoModal"
        data-testid="video-modal"
      >
        <button
          type="button"
          class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="closeVideoModal"
        >
          <X class="h-6 w-6" />
        </button>

        <div class="w-full max-w-3xl">
          <h3 class="text-white text-lg font-medium mb-3">{{ videoModalTitle }}</h3>
          <video
            :src="videoModalUrl"
            controls
            autoplay
            class="w-full rounded-lg"
            data-testid="video-player"
          />
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
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
} from 'lucide-vue-next'
import { useAdminFaces } from '@/features/admin/composables/useAdminFaces'
import type { UpdateAdminFaceForm } from '@/features/admin/services/adminFacesApi'
import { getCategoryLabel, getNicheLabel } from '@/features/admin/utils/faceLabels'

const route = useRoute()
const router = useRouter()
const { face, isLoading, error, fetchFace, updateFace, deleteFace } = useAdminFaces()

const faceId = computed(() => Number(route.params.id))
const isEditing = ref(false)
const editForm = ref<UpdateAdminFaceForm>({})
const editErrors = ref<Record<string, string[]>>({})
const successMessage = ref('')
const deleteError = ref('')

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
    categorie: face.value.categorie,
    niche: face.value.niche,
    is_available: face.value.is_available,
  }
  editErrors.value = {}
  successMessage.value = ''
  isEditing.value = true
}

function cancelEdit(): void {
  isEditing.value = false
  editErrors.value = {}
}

async function saveEdit(): Promise<void> {
  editErrors.value = {}
  successMessage.value = ''

  const result = await updateFace(faceId.value, editForm.value)
  if (result.success) {
    isEditing.value = false
    successMessage.value = result.message ?? 'Profil mis à jour'
    setTimeout(() => {
      successMessage.value = ''
    }, 3000)
  } else {
    editErrors.value = result.errors ?? {}
  }
}

async function handleDelete(): Promise<void> {
  if (!face.value) return

  const confirmed = window.confirm(
    `Êtes-vous sûr de vouloir supprimer le profil de ${face.value.prenom} ${face.value.nom} ? Cette action est irréversible.`,
  )
  if (!confirmed) return

  deleteError.value = ''
  const result = await deleteFace(faceId.value)
  if (result.success) {
    router.push({ name: 'admin-faces-list' })
  } else {
    deleteError.value = result.message ?? 'Erreur lors de la suppression'
  }
}

function goBack(): void {
  router.push({ name: 'admin-faces-list' })
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

    <!-- Success Message -->
    <div
      v-if="successMessage"
      class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700"
      data-testid="success-message"
    >
      {{ successMessage }}
    </div>

    <!-- Delete Error -->
    <div
      v-if="deleteError"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="delete-error"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ deleteError }}</p>
    </div>

    <!-- Face Detail -->
    <template v-if="face">
      <!-- Header with actions -->
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-4">
          <div class="h-16 w-16 rounded-full bg-primary-100 flex items-center justify-center text-xl font-bold text-primary-700">
            {{ (face.prenom?.[0] ?? '').toUpperCase() }}{{ (face.nom?.[0] ?? '').toUpperCase() }}
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900" data-testid="face-name">
              {{ face.prenom }} {{ face.nom }}
            </h1>
            <p class="text-sm text-gray-500">@{{ face.username }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <template v-if="!isEditing">
            <button
              @click="startEdit"
              class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors"
              data-testid="edit-button"
            >
              Modifier
            </button>
            <button
              @click="handleDelete"
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
        <!-- Personal Info -->
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
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Catégorie</dt>
                <dd class="text-sm font-medium text-gray-900">{{ getCategoryLabel(face.categorie) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Niche</dt>
                <dd class="text-sm font-medium text-gray-900">{{ getNicheLabel(face.niche) }}</dd>
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
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                <select
                  v-model="editForm.categorie"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-categorie"
                >
                  <option :value="null">Aucune</option>
                  <option value="acteur">Acteur</option>
                  <option value="influenceur">Influenceur</option>
                  <option value="createur">Créateur</option>
                  <option value="mannequin">Mannequin</option>
                  <option value="figurant">Figurant</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Niche</label>
                <select
                  v-model="editForm.niche"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-niche"
                >
                  <option :value="null">Aucune</option>
                  <option value="beaute">Beauté</option>
                  <option value="nourriture">Nourriture</option>
                  <option value="decouverte">Découverte</option>
                  <option value="mode">Mode</option>
                </select>
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

        <!-- Bio & Location -->
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

        <!-- Stats -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="stats-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Star class="h-5 w-5 text-gray-400" />
            Statistiques
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
              <dd class="text-sm font-medium text-gray-900">{{ face.profile_completion_percentage }}%</dd>
            </div>
          </dl>
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
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  AlertCircle,
  Loader2,
  Trash2,
  Save,
  Star,
  Briefcase,
  Building,
  X,
  ClipboardList,
  Mail,
} from 'lucide-vue-next'
import { useAdminProducers } from '@/features/admin/composables/useAdminProducers'
import type { AdminProducerMission, UpdateAdminProducerForm } from '@/features/admin/services/adminProducersApi'
import { getProducerTypeLabel, getProducerTypeColor } from '@/features/admin/utils/producerLabels'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const { producer, isLoading, error, fetchProducer, updateProducer, toggleActive, deleteProducer } =
  useAdminProducers()

const toast = useToast()

const producerId = computed(() => Number(route.params.id))
const isEditing = ref(false)
const editForm = ref<UpdateAdminProducerForm>({})
const editErrors = ref<Record<string, string[]>>({})
const isToggling = ref(false)
const showToggleModal = ref(false)
const showDeleteModal = ref(false)

onMounted(() => {
  fetchProducer(producerId.value)
})

function startEdit(): void {
  if (!producer.value) return
  editForm.value = {
    type: producer.value.type,
    agency_name: producer.value.agency_name,
    first_name: producer.value.first_name,
    last_name: producer.value.last_name,
    bio: producer.value.bio,
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

  const result = await updateProducer(producerId.value, editForm.value)
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
  const result = await toggleActive(producerId.value)
  isToggling.value = false

  if (result.success) {
    const action = producer.value?.is_active !== false ? 'activé' : 'désactivé'
    toast.success(result.message ?? `Compte ${action}`)
  }
}

async function confirmDelete(): Promise<void> {
  showDeleteModal.value = false
  const result = await deleteProducer(producerId.value)
  if (result.success) {
    toast.success(result.message ?? 'Profil supprimé avec succès')
    router.push({ name: 'admin-producers-list' })
  } else {
    toast.error(result.message ?? 'Erreur lors de la suppression')
  }
}

function goBack(): void {
  router.push({ name: 'admin-producers-list' })
}

const missions = computed<AdminProducerMission[]>(() => producer.value?.missions ?? [])

const missionStatusCounts = computed(() => {
  const counts: Record<string, number> = { draft: 0, published: 0, closed: 0, completed: 0 }
  for (const m of missions.value) {
    if (m.status in counts) {
      counts[m.status] = (counts[m.status] ?? 0) + 1
    }
  }
  return counts
})

function getMissionStatusClass(status: string): string {
  switch (status) {
    case 'draft':
      return 'bg-gray-100 text-gray-800'
    case 'published':
      return 'bg-green-100 text-green-800'
    case 'closed':
      return 'bg-orange-100 text-orange-800'
    case 'completed':
      return 'bg-blue-100 text-blue-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
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
      v-if="isLoading && !producer"
      class="flex items-center justify-center py-12"
      data-testid="loading-state"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Error State -->
    <div
      v-if="error && !producer"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="error-message"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Producer Detail -->
    <template v-if="producer">
      <!-- Header with actions -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="flex items-center gap-4">
          <img
            v-if="producer.thumbnail_url || producer.profile_photo_url"
            :src="producer.thumbnail_url ?? producer.profile_photo_url ?? undefined"
            :alt="producer.display_name"
            class="h-16 w-16 rounded-full object-cover shrink-0"
          />
          <div v-else class="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center text-xl font-bold text-purple-700 shrink-0">
            {{ (producer.display_name?.[0] ?? '').toUpperCase() }}
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900" data-testid="producer-name">
              {{ producer.display_name }}
            </h1>
            <div class="flex items-center gap-2 mt-1">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getProducerTypeColor(producer.type)"
              >
                {{ getProducerTypeLabel(producer.type) }}
              </span>
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="producer.is_active !== false ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                data-testid="status-badge"
              >
                {{ producer.is_active !== false ? 'Actif' : 'Inactif' }}
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
              :class="producer.is_active !== false
                ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100'
                : 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100'"
              data-testid="toggle-active-button"
            >
              {{ producer.is_active !== false ? 'Désactiver' : 'Activer' }}
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
        <!-- Identity Info -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="identity-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Building class="h-5 w-5 text-gray-400" />
            Identité
          </h2>

          <template v-if="!isEditing">
            <dl class="space-y-3">
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Type</dt>
                <dd class="text-sm font-medium text-gray-900">{{ getProducerTypeLabel(producer.type) }}</dd>
              </div>
              <div v-if="producer.type === 'agency'" class="flex justify-between">
                <dt class="text-sm text-gray-500">Nom de l'agence</dt>
                <dd class="text-sm font-medium text-gray-900">{{ producer.agency_name || '—' }}</dd>
              </div>
              <div v-if="producer.type === 'agency' && (producer.agency_logo_url || producer.agency_logo_thumbnail_url)" class="flex justify-between items-center">
                <dt class="text-sm text-gray-500">Logo agence</dt>
                <dd>
                  <img
                    :src="producer.agency_logo_thumbnail_url ?? producer.agency_logo_url ?? undefined"
                    :alt="`Logo ${producer.agency_name}`"
                    class="h-10 w-10 rounded object-cover"
                  />
                </dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Prénom</dt>
                <dd class="text-sm font-medium text-gray-900">{{ producer.first_name || '—' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Nom</dt>
                <dd class="text-sm font-medium text-gray-900">{{ producer.last_name || '—' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500 flex items-center gap-1.5">
                  <Mail class="w-4 h-4" />
                  Email
                </dt>
                <dd class="text-sm font-medium text-gray-900">
                  <a v-if="producer.email" :href="`mailto:${producer.email}`" class="text-weact hover:underline">
                    {{ producer.email }}
                  </a>
                  <span v-else>—</span>
                </dd>
              </div>
            </dl>
          </template>

          <!-- Edit Form -->
          <template v-else>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select
                  v-model="editForm.type"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-type"
                >
                  <option value="agency">Agence</option>
                  <option value="particulier">Particulier</option>
                </select>
                <p v-if="editErrors.type" class="mt-1 text-xs text-red-600">{{ editErrors.type[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom de l'agence</label>
                <input
                  v-model="editForm.agency_name"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-agency-name"
                />
                <p v-if="editErrors.agency_name" class="mt-1 text-xs text-red-600">{{ editErrors.agency_name[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prénom</label>
                <input
                  v-model="editForm.first_name"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-first-name"
                />
                <p v-if="editErrors.first_name" class="mt-1 text-xs text-red-600">{{ editErrors.first_name[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                <input
                  v-model="editForm.last_name"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  data-testid="edit-last-name"
                />
                <p v-if="editErrors.last_name" class="mt-1 text-xs text-red-600">{{ editErrors.last_name[0] }}</p>
              </div>
            </div>
          </template>
        </section>

        <!-- Bio -->
        <section class="bg-white rounded-xl border border-gray-200 p-6" data-testid="bio-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Bio</h2>

          <template v-if="!isEditing">
            <p class="text-sm text-gray-900">{{ producer.bio || '—' }}</p>
          </template>

          <template v-else>
            <div>
              <textarea
                v-model="editForm.bio"
                rows="6"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                data-testid="edit-bio"
              />
              <p v-if="editErrors.bio" class="mt-1 text-xs text-red-600">{{ editErrors.bio[0] }}</p>
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
                {{ producer.average_rating ? `${producer.average_rating}/5` : '—' }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Nombre d'avis</dt>
              <dd class="text-sm font-medium text-gray-900">{{ producer.ratings_count ?? 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Missions</dt>
              <dd class="text-sm font-medium text-gray-900" data-testid="missions-count">{{ producer.missions_count ?? 0 }}</dd>
            </div>
          </dl>
        </section>

        <!-- Missions Section -->
        <section class="bg-white rounded-xl border border-gray-200 p-6 lg:col-span-2" data-testid="missions-section">
          <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <ClipboardList class="h-5 w-5 text-gray-400" />
            Missions
            <span v-if="missions.length" class="text-sm font-normal text-gray-500">({{ missions.length }})</span>
          </h2>

          <template v-if="missions.length > 0">
            <!-- Status Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6" data-testid="missions-status-summary">
              <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-center">
                <p class="text-2xl font-bold text-gray-800">{{ missionStatusCounts.draft }}</p>
                <p class="text-xs text-gray-500 mt-1">Brouillon</p>
              </div>
              <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-center">
                <p class="text-2xl font-bold text-green-800">{{ missionStatusCounts.published }}</p>
                <p class="text-xs text-green-600 mt-1">Publiée</p>
              </div>
              <div class="rounded-lg bg-orange-50 border border-orange-200 p-3 text-center">
                <p class="text-2xl font-bold text-orange-800">{{ missionStatusCounts.closed }}</p>
                <p class="text-xs text-orange-600 mt-1">Clôturée</p>
              </div>
              <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-center">
                <p class="text-2xl font-bold text-blue-800">{{ missionStatusCounts.completed }}</p>
                <p class="text-xs text-blue-600 mt-1">Terminée</p>
              </div>
            </div>

            <!-- Recent Missions Table -->
            <div class="overflow-x-auto" data-testid="missions-table">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="text-left py-2 pr-4 font-medium text-gray-500">Titre</th>
                    <th class="text-left py-2 pr-4 font-medium text-gray-500">Statut</th>
                    <th class="text-left py-2 font-medium text-gray-500">Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="mission in missions"
                    :key="mission.id"
                    class="border-b border-gray-100 last:border-0"
                    data-testid="mission-row"
                  >
                    <td class="py-2 pr-4 text-gray-900">{{ mission.title }}</td>
                    <td class="py-2 pr-4">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="getMissionStatusClass(mission.status)"
                      >
                        {{ mission.status_label }}
                      </span>
                    </td>
                    <td class="py-2 text-gray-500">
                      {{ new Date(mission.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Empty State -->
          <div v-else class="text-center py-8 text-gray-400" data-testid="missions-empty">
            <ClipboardList class="h-10 w-10 mx-auto mb-2 opacity-50" />
            <p class="text-sm">Aucune mission créée</p>
          </div>
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
              <dd class="text-sm font-medium text-gray-900">#{{ producer.id }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-gray-500">Date de création</dt>
              <dd class="text-sm font-medium text-gray-900">
                {{ new Date(producer.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) }}
              </dd>
            </div>
          </dl>
        </section>
      </div>
    </template>

    <!-- Toggle Active Confirmation Modal -->
    <ConfirmModal
      v-if="producer"
      :is-open="showToggleModal"
      :title="producer.is_active !== false ? 'Désactiver ce compte' : 'Réactiver ce compte'"
      :message="producer.is_active !== false
        ? `Êtes-vous sûr de vouloir désactiver le compte de ${producer.display_name} ? L'utilisateur ne pourra plus se connecter.`
        : `Êtes-vous sûr de vouloir réactiver le compte de ${producer.display_name} ?`"
      :confirm-text="producer.is_active !== false ? 'Désactiver' : 'Réactiver'"
      variant="warning"
      @confirm="confirmToggleActive"
      @cancel="showToggleModal = false"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      v-if="producer"
      :is-open="showDeleteModal"
      title="Supprimer ce profil"
      :message="`Êtes-vous sûr de vouloir supprimer le profil de ${producer.display_name} ? Cette action est irréversible.`"
      confirm-text="Supprimer"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDeleteModal = false"
    />
  </div>
</template>

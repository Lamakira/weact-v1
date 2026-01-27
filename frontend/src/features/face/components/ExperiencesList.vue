<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Experience, ExperienceFormData } from '../types'
import ExperienceCard from './ExperienceCard.vue'
import ExperienceForm from './ExperienceForm.vue'

const props = defineProps<{
  experiences: Experience[]
  isLoading: boolean
  isSaving: boolean
  isDeleting: boolean
  error: string | null
  validationErrors: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'add', data: ExperienceFormData): void
  (e: 'edit', id: number, data: ExperienceFormData): void
  (e: 'delete', id: number): void
}>()

// Local state for UI modes
const isAddMode = ref(false)
const editingExperienceId = ref<number | null>(null)
const deletingExperienceId = ref<number | null>(null)
const showDeleteConfirm = ref(false)

const hasExperiences = computed(() => props.experiences.length > 0)

const editingExperience = computed(() => {
  if (editingExperienceId.value === null) return null
  return props.experiences.find((e) => e.id === editingExperienceId.value) ?? null
})

// Add mode handlers
const handleShowAddForm = () => {
  isAddMode.value = true
  editingExperienceId.value = null
}

const handleAddSubmit = (data: ExperienceFormData) => {
  emit('add', data)
}

const handleAddCancel = () => {
  isAddMode.value = false
}

// Edit mode handlers
const handleEditClick = (experience: Experience) => {
  editingExperienceId.value = experience.id
  isAddMode.value = false
}

const handleEditSubmit = (data: ExperienceFormData) => {
  if (editingExperienceId.value !== null) {
    emit('edit', editingExperienceId.value, data)
  }
}

const handleEditCancel = () => {
  editingExperienceId.value = null
}

// Delete handlers
const handleDeleteClick = (id: number) => {
  deletingExperienceId.value = id
  showDeleteConfirm.value = true
}

const handleDeleteConfirm = () => {
  if (deletingExperienceId.value !== null) {
    emit('delete', deletingExperienceId.value)
  }
  showDeleteConfirm.value = false
  deletingExperienceId.value = null
}

const handleDeleteCancel = () => {
  showDeleteConfirm.value = false
  deletingExperienceId.value = null
}

// Reset form states when save completes successfully
const resetFormStates = () => {
  isAddMode.value = false
  editingExperienceId.value = null
}

// Expose method for parent to call after successful operations
defineExpose({
  resetFormStates,
})
</script>

<template>
  <div class="space-y-4" data-testid="experiences-list">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900">Expériences professionnelles</h3>
      <button
        v-if="!isAddMode && !editingExperienceId"
        type="button"
        @click="handleShowAddForm"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-teal-600 bg-teal-50 rounded-lg hover:bg-teal-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-600 transition-colors"
        data-testid="add-experience-button"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Ajouter
      </button>
    </div>

    <!-- Add Form -->
    <div
      v-if="isAddMode"
      class="p-4 bg-gray-50 border border-gray-200 rounded-lg"
      data-testid="add-form-container"
    >
      <h4 class="text-sm font-medium text-gray-700 mb-3">Nouvelle expérience</h4>
      <ExperienceForm
        :experience="null"
        :is-saving="isSaving"
        :error="error"
        :validation-errors="validationErrors"
        :show-cancel="true"
        @submit="handleAddSubmit"
        @cancel="handleAddCancel"
      />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="space-y-3" data-testid="loading-skeleton">
      <div
        v-for="i in 3"
        :key="i"
        class="p-4 bg-white border border-gray-200 rounded-lg animate-pulse"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 space-y-2">
            <div class="h-5 w-16 bg-gray-200 rounded-full"></div>
            <div class="h-5 w-3/4 bg-gray-200 rounded"></div>
            <div class="h-4 w-full bg-gray-200 rounded"></div>
          </div>
          <div class="flex gap-1">
            <div class="h-8 w-8 bg-gray-200 rounded"></div>
            <div class="h-8 w-8 bg-gray-200 rounded"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!hasExperiences && !isAddMode"
      class="py-8 text-center"
      data-testid="empty-state"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-12 w-12 mx-auto text-gray-300 mb-3"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="1"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
        />
      </svg>
      <p class="text-sm text-gray-500 mb-3">Aucune expérience ajoutée</p>
      <button
        type="button"
        @click="handleShowAddForm"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-teal-600 hover:text-teal-700 transition-colors"
        data-testid="empty-add-button"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Ajouter votre première expérience
      </button>
    </div>

    <!-- Experiences List -->
    <div v-else-if="!isLoading" class="space-y-3" data-testid="experiences-container">
      <template v-for="experience in experiences" :key="experience.id">
        <!-- Edit Form (inline) -->
        <div
          v-if="editingExperienceId === experience.id"
          class="p-4 bg-gray-50 border border-gray-200 rounded-lg"
          data-testid="edit-form-container"
        >
          <h4 class="text-sm font-medium text-gray-700 mb-3">Modifier l'expérience</h4>
          <ExperienceForm
            :experience="editingExperience"
            :is-saving="isSaving"
            :error="error"
            :validation-errors="validationErrors"
            @submit="handleEditSubmit"
            @cancel="handleEditCancel"
          />
        </div>

        <!-- Experience Card -->
        <ExperienceCard
          v-else
          :experience="experience"
          :is-deleting="isDeleting && deletingExperienceId === experience.id"
          @edit="handleEditClick"
          @delete="handleDeleteClick"
        />
      </template>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Teleport to="body">
      <div
        v-if="showDeleteConfirm"
        class="fixed inset-0 z-50 flex items-center justify-center"
        data-testid="delete-confirm-dialog"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/50"
          @click="handleDeleteCancel"
        ></div>

        <!-- Dialog -->
        <div class="relative bg-white rounded-lg shadow-xl p-6 max-w-sm w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirmer la suppression</h3>
          <p class="text-sm text-gray-600 mb-4">
            Êtes-vous sûr de vouloir supprimer cette expérience ? Cette action est irréversible.
          </p>
          <div class="flex justify-end gap-3">
            <button
              type="button"
              @click="handleDeleteCancel"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
              data-testid="delete-cancel-button"
            >
              Annuler
            </button>
            <button
              type="button"
              @click="handleDeleteConfirm"
              class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors"
              data-testid="delete-confirm-button"
            >
              Supprimer
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

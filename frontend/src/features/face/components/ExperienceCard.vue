<script setup lang="ts">
import type { Experience } from '../types'

const props = defineProps<{
  experience: Experience
  isDeleting: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', experience: Experience): void
  (e: 'delete', id: string): void
}>()

const handleEdit = () => {
  emit('edit', props.experience)
}

const handleDelete = () => {
  emit('delete', props.experience.id)
}
</script>

<template>
  <div
    class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow group"
    data-testid="experience-card"
  >
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 min-w-0">
        <!-- Date Period Badge -->
        <span
          class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium mb-2"
          :class="experience.is_ongoing ? 'bg-teal-100 text-teal-800' : 'bg-gray-100 text-gray-700'"
          data-testid="experience-period"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-3.5 w-3.5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          {{ experience.formatted_period }}
        </span>

        <!-- Title -->
        <h4
          class="text-base font-semibold text-gray-900"
          data-testid="experience-title"
        >
          {{ experience.titre }}
        </h4>

        <!-- Description -->
        <p
          v-if="experience.description"
          class="mt-1 text-sm text-gray-600 line-clamp-2"
          data-testid="experience-description"
        >
          {{ experience.description }}
        </p>
      </div>

      <!-- Action Buttons -->
      <div
        class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
        data-testid="action-buttons"
      >
        <!-- Edit Button -->
        <button
          type="button"
          @click="handleEdit"
          class="p-1.5 text-gray-400 hover:text-teal-600 hover:bg-teal-50 rounded-md transition-colors"
          title="Modifier"
          data-testid="edit-button"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
            />
          </svg>
        </button>

        <!-- Delete Button -->
        <button
          type="button"
          @click="handleDelete"
          :disabled="isDeleting"
          class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          title="Supprimer"
          data-testid="delete-button"
        >
          <svg
            v-if="!isDeleting"
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
            />
          </svg>
          <svg
            v-else
            class="animate-spin h-4 w-4"
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
        </button>
      </div>
    </div>
  </div>
</template>

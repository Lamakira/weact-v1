<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { X, Languages, Loader2 } from 'lucide-vue-next'

const props = defineProps<{
  langues: string[] | null
  isSaving: boolean
  error: string | null
  maxLangues: number
  maxLangueLength: number
}>()

const emit = defineEmits<{
  save: [langues: string[] | null]
}>()

// Local state
const tags = ref<string[]>([...(props.langues ?? [])])
const inputValue = ref('')
const inputRef = ref<HTMLInputElement | null>(null)

// Suggested languages
const suggestions = ['Français', 'Anglais', 'Arabe', 'Fon', 'Yoruba', 'Portugais', 'Espagnol']

// Track if there are unsaved changes
const hasChanges = computed((): boolean => {
  const current = JSON.stringify(tags.value)
  const saved = JSON.stringify(props.langues ?? [])
  return current !== saved
})

// Available suggestions (not already added)
const availableSuggestions = computed((): string[] => {
  return suggestions.filter((s) => !tags.value.includes(s))
})

const canAddMore = computed((): boolean => tags.value.length < props.maxLangues)

// Watch for external changes to langues prop
watch(
  () => props.langues,
  (newVal) => {
    tags.value = [...(newVal ?? [])]
  },
)

/**
 * Add a tag from input
 */
function addTag(): void {
  const value = inputValue.value.trim()
  if (!value) return
  if (value.length > props.maxLangueLength) return
  if (tags.value.includes(value)) {
    inputValue.value = ''
    return
  }
  if (!canAddMore.value) return

  tags.value.push(value)
  inputValue.value = ''
}

/**
 * Add a suggestion as a tag
 */
function addSuggestion(suggestion: string): void {
  if (tags.value.includes(suggestion)) return
  if (!canAddMore.value) return

  tags.value.push(suggestion)
}

/**
 * Remove a tag by index
 */
function removeTag(index: number): void {
  tags.value.splice(index, 1)
}

/**
 * Handle keydown on input (Enter or comma to add, Backspace to remove last)
 */
function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' || event.key === ',') {
    event.preventDefault()
    addTag()
  } else if (event.key === 'Backspace' && inputValue.value === '' && tags.value.length > 0) {
    tags.value.pop()
  }
}

/**
 * Save the current tags
 */
function handleSave(): void {
  emit('save', tags.value.length > 0 ? tags.value : null)
}
</script>

<template>
  <div data-testid="langues-tag-input">
    <h2 class="text-lg font-medium text-gray-900 mb-2 flex items-center gap-2">
      <Languages class="w-5 h-5 text-teal-600" />
      Langues parlées
    </h2>
    <p class="text-sm text-gray-500 mb-4">
      Ajoutez les langues que vous parlez pour aider les producteurs à évaluer votre profil.
    </p>

    <!-- Tags display -->
    <div
      class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2.5 focus-within:border-weact focus-within:ring-2 focus-within:ring-weact/20 min-h-[44px]"
      @click="inputRef?.focus()"
    >
      <span
        v-for="(tag, index) in tags"
        :key="tag"
        class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-3 py-1 text-sm font-medium text-teal-700 border border-teal-200"
        data-testid="langue-tag"
      >
        {{ tag }}
        <button
          type="button"
          class="ml-0.5 inline-flex items-center rounded-full p-0.5 text-teal-600 hover:bg-teal-100 hover:text-teal-800 transition-colors"
          :aria-label="`Supprimer ${tag}`"
          data-testid="remove-tag-button"
          @click.stop="removeTag(index)"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </span>

      <input
        ref="inputRef"
        v-model="inputValue"
        type="text"
        class="flex-1 min-w-[120px] border-none bg-transparent text-sm text-gray-900 placeholder-gray-400 outline-none p-0"
        :placeholder="canAddMore ? 'Ajouter une langue...' : 'Maximum atteint'"
        :disabled="!canAddMore || isSaving"
        :maxlength="maxLangueLength"
        data-testid="langue-input"
        @keydown="handleKeydown"
      />
    </div>

    <!-- Counter -->
    <div class="mt-1.5 flex justify-between text-xs text-gray-400">
      <span>Appuyez sur Entrée ou virgule pour ajouter</span>
      <span :class="{ 'text-red-500': tags.length >= maxLangues }">
        {{ tags.length }}/{{ maxLangues }}
      </span>
    </div>

    <!-- Suggestions -->
    <div v-if="availableSuggestions.length > 0 && canAddMore" class="mt-3">
      <span class="text-xs text-gray-500 mr-2">Suggestions :</span>
      <button
        v-for="suggestion in availableSuggestions"
        :key="suggestion"
        type="button"
        class="mr-1.5 mb-1.5 inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs text-gray-600 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition-colors"
        :disabled="isSaving"
        data-testid="suggestion-button"
        @click="addSuggestion(suggestion)"
      >
        + {{ suggestion }}
      </button>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      class="mt-3 rounded-md bg-red-50 p-3 border border-red-200"
      role="alert"
      data-testid="langues-error"
    >
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Save button -->
    <div class="mt-4">
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-weact px-4 py-2 text-sm font-medium text-white hover:bg-weact/90 focus:outline-none focus:ring-2 focus:ring-weact/50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        :disabled="isSaving || !hasChanges"
        data-testid="langues-save-button"
        @click="handleSave"
      >
        <Loader2 v-if="isSaving" class="h-4 w-4 animate-spin" />
        Enregistrer
      </button>
    </div>
  </div>
</template>

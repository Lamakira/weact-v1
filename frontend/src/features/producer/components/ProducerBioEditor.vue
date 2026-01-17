<script setup lang="ts">
import { ref, watch, computed } from 'vue'

const props = defineProps<{
  bio: string | null
  isSaving: boolean
  error: string | null
  maxLength?: number
}>()

const emit = defineEmits<{
  save: [bio: string | null]
}>()

const MAX_LENGTH = props.maxLength ?? 500

const localBio = ref(props.bio ?? '')
const isDirty = ref(false)

// Sync with prop changes
watch(
  () => props.bio,
  (newVal) => {
    if (!isDirty.value) {
      localBio.value = newVal ?? ''
    }
  }
)

// Track changes
watch(localBio, () => {
  isDirty.value = true
})

const charCount = computed(() => localBio.value.length)
const isOverLimit = computed(() => charCount.value > MAX_LENGTH)
const isNearLimit = computed(() => charCount.value > MAX_LENGTH - 50 && charCount.value <= MAX_LENGTH)
const canSave = computed(() => isDirty.value && !isOverLimit.value && !props.isSaving)

function handleSave() {
  if (!canSave.value) return
  const bioToSave = localBio.value.trim() || null
  emit('save', bioToSave)
  isDirty.value = false
}

function handleReset() {
  localBio.value = props.bio ?? ''
  isDirty.value = false
}
</script>

<template>
  <div class="bg-white rounded-lg shadow p-6" data-testid="bio-editor">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Bio</h2>

    <p class="text-sm text-gray-600 mb-4">
      Décrivez-vous en quelques mots. Les Faces verront cette description sur votre profil.
    </p>

    <!-- Error message -->
    <div
      v-if="error"
      class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm"
      data-testid="bio-error"
    >
      {{ error }}
    </div>

    <!-- Textarea -->
    <div>
      <textarea
        v-model="localBio"
        :disabled="isSaving"
        :maxlength="MAX_LENGTH + 50"
        rows="4"
        class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-weact-500 focus:border-transparent resize-none transition-colors"
        :class="{
          'border-gray-300': !isOverLimit && !isNearLimit,
          'border-yellow-400': isNearLimit,
          'border-red-500': isOverLimit,
          'bg-gray-50 cursor-not-allowed': isSaving,
        }"
        placeholder="Ex: Agence de casting spécialisée dans la publicité depuis 10 ans..."
        data-testid="bio-textarea"
      ></textarea>

      <!-- Counter and error row -->
      <div class="flex justify-between items-center mt-1">
        <!-- Warning message when over limit (left side) -->
        <p
          v-if="isOverLimit"
          class="text-sm text-red-600"
          data-testid="bio-over-limit-warning"
        >
          La bio dépasse la limite de {{ MAX_LENGTH }} caractères
        </p>
        <span v-else></span>

        <!-- Character counter (right side) -->
        <div
          class="text-sm font-medium"
          :class="{
            'text-gray-400': !isNearLimit && !isOverLimit,
            'text-yellow-600': isNearLimit,
            'text-red-600': isOverLimit,
          }"
          data-testid="bio-char-count"
        >
          {{ charCount }}/{{ MAX_LENGTH }}
        </div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="flex items-center gap-3 mt-4">
      <button
        type="button"
        @click="handleSave"
        :disabled="!canSave"
        class="px-4 py-2 bg-weact-600 text-white font-medium rounded-lg hover:bg-weact-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-weact-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        data-testid="bio-save-button"
      >
        {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
      </button>

      <button
        v-if="isDirty"
        type="button"
        @click="handleReset"
        :disabled="isSaving"
        class="px-4 py-2 text-gray-600 font-medium rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        data-testid="bio-reset-button"
      >
        Annuler
      </button>
    </div>
  </div>
</template>

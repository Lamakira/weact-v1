<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Loader2, Star } from 'lucide-vue-next'
import { candidatureApi } from '../services/candidatureApi'
import type { ProducerCandidatureRating } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { Textarea } from '@/components/ui/textarea'

/**
 * Notation facultative d'une Face par le Producteur (lot C8) : étoiles 1 à 5 + commentaire
 * optionnel. Même formulaire que la notation d'un booking (BookingRatingForm), mais ce
 * dernier est lié à l'API bookings : seul le contrat d'appel (`rateCandidature`) diffère.
 */
const MAX_COMMENT_LENGTH = 1000

const props = defineProps<{
  candidatureId: string | null
  faceName: string
}>()

const emit = defineEmits<{
  rated: [candidatureId: string, rating: ProducerCandidatureRating]
  close: []
}>()

const selectedScore = ref(0)
const hoverScore = ref(0)
const comment = ref('')
const isSubmitting = ref(false)
const error = ref<string | null>(null)

const isOpen = computed(() => props.candidatureId !== null)
const displayScore = computed(() => hoverScore.value || selectedScore.value)
const remainingCharacters = computed(() => MAX_COMMENT_LENGTH - comment.value.length)
const canSubmit = computed(() => selectedScore.value >= 1 && !isSubmitting.value)

// Chaque ouverture repart d'un formulaire vierge.
watch(isOpen, (open) => {
  if (!open) return
  selectedScore.value = 0
  hoverScore.value = 0
  comment.value = ''
  error.value = null
})

function close(): void {
  if (isSubmitting.value) return
  emit('close')
}

async function submit(): Promise<void> {
  if (!canSubmit.value || props.candidatureId === null) return
  isSubmitting.value = true
  error.value = null
  try {
    const response = await candidatureApi.rateCandidature(
      props.candidatureId,
      selectedScore.value,
      comment.value.trim() || undefined,
    )
    emit('rated', props.candidatureId, {
      score: response.data.score,
      comment: response.data.comment,
      created_at: response.data.created_at,
    })
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      data-testid="rate-face-dialog"
      @click.self="close"
      @keydown.esc="close"
    >
      <div
        class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rate-face-dialog-title"
      >
        <h3 id="rate-face-dialog-title" class="text-lg font-semibold text-foreground">
          Noter {{ faceName }}
        </h3>
        <p class="mt-1 text-sm text-muted-foreground">
          Votre avis est facultatif et aide les autres Producteurs.
        </p>

        <div class="mt-4 flex items-center gap-1.5" role="radiogroup" aria-label="Choisir une note de 1 à 5">
          <button
            v-for="star in 5"
            :key="star"
            type="button"
            class="rounded-md p-1 transition-colors"
            :class="displayScore >= star ? 'text-amber-400' : 'text-gray-300 hover:text-amber-300'"
            :aria-checked="selectedScore === star"
            :aria-label="`${star} sur 5`"
            :data-testid="`rate-star-${star}`"
            role="radio"
            @mouseenter="hoverScore = star"
            @mouseleave="hoverScore = 0"
            @focus="hoverScore = star"
            @blur="hoverScore = 0"
            @click="selectedScore = star"
          >
            <Star class="h-7 w-7" :fill="displayScore >= star ? 'currentColor' : 'none'" />
          </button>
          <span class="ml-2 text-sm text-muted-foreground">
            {{ selectedScore ? `${selectedScore}/5` : 'Sélectionnez une note' }}
          </span>
        </div>

        <div class="mt-4">
          <label for="rate-face-comment" class="mb-1.5 block text-sm font-medium text-foreground">
            Commentaire (optionnel)
          </label>
          <Textarea
            id="rate-face-comment"
            v-model="comment"
            rows="4"
            :maxlength="MAX_COMMENT_LENGTH"
            placeholder="Partagez votre expérience…"
          />
          <p class="mt-1 text-right text-xs text-muted-foreground">
            {{ remainingCharacters }} caractères restants
          </p>
        </div>

        <p v-if="error" class="mt-3 text-sm text-red-600" role="alert" data-testid="rate-face-error">{{ error }}</p>

        <div class="mt-5 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-60"
            :disabled="isSubmitting"
            @click="close"
          >
            Plus tard
          </button>
          <button
            type="button"
            data-testid="rate-face-submit"
            :disabled="!canSubmit"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-weact px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
            @click="submit"
          >
            <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
            Envoyer ma note
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

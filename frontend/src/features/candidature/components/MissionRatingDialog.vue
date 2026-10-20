<script setup lang="ts">
/**
 * MissionRatingDialog (lot C8) — la Face note le producteur à la fin de la mission.
 * Facultatif : étoiles 1-5 + commentaire optionnel. L'appel réseau vit ici ; le parent reçoit `rated`.
 *
 * Mouvement : opacity / transform uniquement, mêmes courbes que FaceMissionHub, réduit sous
 * prefers-reduced-motion.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Loader2, Star, X } from 'lucide-vue-next'
import { candidatureApi } from '../services/candidatureApi'
import type { MissionRating } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import { Textarea } from '@/components/ui/textarea'

const MAX_COMMENT_LENGTH = 1000

const props = defineProps<{
  open: boolean
  candidatureId: string
  producerName: string
}>()

const emit = defineEmits<{
  close: []
  rated: [rating: MissionRating]
}>()

const selectedScore = ref(0)
const hoverScore = ref(0)
const comment = ref('')
const isSubmitting = ref(false)
const error = ref<string | null>(null)

const displayScore = computed(() => hoverScore.value || selectedScore.value)
const remainingCharacters = computed(() => MAX_COMMENT_LENGTH - comment.value.length)
const canSubmit = computed(() => selectedScore.value >= 1 && !isSubmitting.value)

// Une réouverture repart d'un formulaire vierge.
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      selectedScore.value = 0
      hoverScore.value = 0
      comment.value = ''
      error.value = null
    }
  },
)

// Le dialogue téléporté dans <body> ne doit pas survivre à une page désactivée par <keep-alive>.
useDismissOnDeactivate(() => props.open, () => emit('close'))

function close(): void {
  if (!isSubmitting.value) emit('close')
}

function handleEscape(e: KeyboardEvent): void {
  if (e.key === 'Escape' && props.open) close()
}
onMounted(() => window.addEventListener('keydown', handleEscape))
onUnmounted(() => window.removeEventListener('keydown', handleEscape))

async function submit(): Promise<void> {
  if (!canSubmit.value) return

  isSubmitting.value = true
  error.value = null
  try {
    const rating = await candidatureApi.rateProducer(
      props.candidatureId,
      selectedScore.value,
      comment.value.trim() || undefined,
    )
    emit('rated', rating)
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="rating-dialog">
      <div
        v-if="open"
        class="rating-dialog fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        data-testid="mission-rating-dialog"
        @click.self="close"
      >
        <div
          class="rating-dialog__panel relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="mission-rating-title"
        >
          <button
            type="button"
            class="absolute right-4 top-4 rounded-full p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
            aria-label="Fermer"
            @click="close"
          >
            <X class="h-4 w-4" />
          </button>

          <h2 id="mission-rating-title" class="pr-8 text-lg font-bold text-gray-900">Noter {{ producerName }}</h2>
          <p class="mt-1 text-sm text-gray-500">
            C’est facultatif. Votre avis aide les autres Faces à choisir leurs missions.
          </p>

          <div class="mt-4 flex items-center gap-1.5" role="radiogroup" aria-label="Choisir une note de 1 à 5">
            <button
              v-for="star in 5"
              :key="star"
              type="button"
              class="rating-star rounded-md p-1"
              :class="displayScore >= star ? 'text-amber-400' : 'text-gray-300 hover:text-amber-300'"
              role="radio"
              :aria-checked="selectedScore === star"
              :aria-label="`${star} sur 5`"
              :data-testid="`rating-star-${star}`"
              @mouseenter="hoverScore = star"
              @mouseleave="hoverScore = 0"
              @focus="hoverScore = star"
              @blur="hoverScore = 0"
              @click="selectedScore = star"
            >
              <Star class="h-7 w-7" :fill="displayScore >= star ? 'currentColor' : 'none'" />
            </button>
            <span class="ml-2 text-sm text-gray-600">{{ selectedScore ? `${selectedScore}/5` : 'Sélectionnez une note' }}</span>
          </div>

          <div class="mt-4">
            <label for="mission-rating-comment" class="mb-1.5 block text-sm font-medium text-gray-700">
              Commentaire (facultatif)
            </label>
            <Textarea
              id="mission-rating-comment"
              v-model="comment"
              rows="4"
              maxlength="1000"
              placeholder="Partagez votre expérience…"
              data-testid="rating-comment"
            />
            <p class="mt-1 text-right text-xs text-gray-500">{{ remainingCharacters }} caractères restants</p>
          </div>

          <p v-if="error" class="mt-3 text-sm text-red-600" role="alert" data-testid="rating-error">{{ error }}</p>

          <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
              @click="close"
            >
              Plus tard
            </button>
            <button
              type="button"
              :disabled="!canSubmit"
              class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-colors hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
              data-testid="rating-submit"
              @click="submit"
            >
              <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
              Envoyer mon évaluation
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.rating-dialog {
  --hub-ease: cubic-bezier(0.23, 1, 0.32, 1);
}

.rating-star {
  transition:
    transform 160ms var(--hub-ease),
    color 160ms ease;
}
.rating-star:active {
  transform: scale(0.92);
}

/* Entrée : fondu du voile + panneau 0.96 → 1 ; sortie plus courte. */
.rating-dialog-enter-active {
  transition: opacity 200ms var(--hub-ease);
}
.rating-dialog-leave-active {
  transition: opacity 150ms ease-out;
}
.rating-dialog-enter-from,
.rating-dialog-leave-to {
  opacity: 0;
}
.rating-dialog-enter-active .rating-dialog__panel {
  transition: transform 200ms var(--hub-ease);
}
.rating-dialog-leave-active .rating-dialog__panel {
  transition: transform 150ms ease-out;
}
.rating-dialog-enter-from .rating-dialog__panel,
.rating-dialog-leave-to .rating-dialog__panel {
  transform: scale(0.96);
}

@media (prefers-reduced-motion: reduce) {
  .rating-star,
  .rating-dialog-enter-active .rating-dialog__panel,
  .rating-dialog-leave-active .rating-dialog__panel {
    transition: none;
  }
  .rating-star:active,
  .rating-dialog-enter-from .rating-dialog__panel,
  .rating-dialog-leave-to .rating-dialog__panel {
    transform: none;
  }
}
</style>

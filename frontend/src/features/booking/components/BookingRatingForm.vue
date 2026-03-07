<script setup lang="ts">
import { computed, ref } from 'vue'
import { Loader2, Star } from 'lucide-vue-next'
import type { BookingRating } from '../types'
import { bookingApi } from '../services/bookingApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { Textarea } from '@/components/ui/textarea'

const MAX_COMMENT_LENGTH = 1000

const props = defineProps<{
  bookingId: number
  ratedName: string
}>()

const emit = defineEmits<{
  rated: [rating: BookingRating]
}>()

const selectedScore = ref<number>(0)
const hoverScore = ref<number>(0)
const comment = ref<string>('')
const isSubmitting = ref<boolean>(false)
const error = ref<string | null>(null)

const displayScore = computed<number>(() => hoverScore.value || selectedScore.value)
const remainingCharacters = computed<number>(() => MAX_COMMENT_LENGTH - comment.value.length)
const canSubmit = computed<boolean>(() => selectedScore.value >= 1 && !isSubmitting.value)

async function submitRating(): Promise<void> {
  if (!canSubmit.value) return

  isSubmitting.value = true
  error.value = null

  try {
    const response = await bookingApi.rateBooking(
      props.bookingId,
      selectedScore.value,
      comment.value.trim() || undefined,
    )

    emit('rated', response.data)
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section class="bg-white rounded-xl border border-gray-200 p-5" data-testid="booking-rating-section">
    <h2 class="text-sm font-semibold text-gray-700">Comment s'est passé le booking ?</h2>
    <p class="mt-1 text-sm text-gray-500">Donnez une note à {{ ratedName }}.</p>

    <div class="mt-4">
      <div class="flex items-center gap-1.5" role="radiogroup" aria-label="Choisir une note de 1 à 5">
        <button
          v-for="star in 5"
          :key="star"
          type="button"
          class="rounded-md p-1 transition-colors"
          :class="displayScore >= star ? 'text-amber-400' : 'text-gray-300 hover:text-amber-300'"
          :aria-checked="selectedScore === star"
          role="radio"
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
    </div>

    <div class="mt-4">
      <label for="booking-rating-comment" class="mb-1.5 block text-sm font-medium text-gray-700">
        Commentaire (optionnel)
      </label>
      <Textarea
        id="booking-rating-comment"
        v-model="comment"
        rows="4"
        maxlength="1000"
        placeholder="Partagez votre expérience..."
      />
      <p class="mt-1 text-right text-xs text-gray-500">
        {{ remainingCharacters }} caractères restants
      </p>
    </div>

    <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

    <div class="mt-4 flex justify-end">
      <button
        type="button"
        :disabled="!canSubmit"
        class="inline-flex items-center justify-center gap-2 rounded-lg bg-weact px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
        @click="submitRating"
      >
        <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
        Envoyer mon évaluation
      </button>
    </div>
  </section>
</template>

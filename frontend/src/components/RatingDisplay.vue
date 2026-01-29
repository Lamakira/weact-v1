<script setup lang="ts">
import { computed } from 'vue'
import { Star } from 'lucide-vue-next'

interface Props {
  averageRating: number | null
  reviewCount: number
}

const props = withDefaults(defineProps<Props>(), {
  averageRating: null,
  reviewCount: 0,
})

/**
 * Calculates how many stars should be visually filled based on rounding
 * If rating is 4.5 or higher, it rounds to 5. If 4.4, rounds to 4.
 */
const filledStars = computed(() => {
  if (!props.averageRating || props.reviewCount === 0) return 0
  return Math.round(props.averageRating)
})

/**
 * Formatted text for the review count
 */
const reviewText = computed(() => {
  if (props.reviewCount === 0) return 'Aucun avis'
  return `(${props.reviewCount} avis)`
})

/**
 * Accessibility label for screen readers
 */
const ariaLabel = computed(() => {
  if (props.reviewCount === 0) return 'Aucun avis pour le moment'
  return `Note de ${props.averageRating} sur 5 basée sur ${props.reviewCount} avis`
})
</script>

<template>
  <div
    class="flex items-center gap-2 select-none"
    :aria-label="ariaLabel"
    role="img"
    data-testid="rating-display"
  >
    <!-- Stars Container -->
    <div class="flex items-center -space-x-px" data-testid="rating-stars">
      <Star
        v-for="i in 5"
        :key="i"
        :size="16"
        :data-testid="`rating-star-${i}`"
        :data-filled="i <= filledStars"
        :class="[
          'w-4 h-4 transition-colors duration-200',
          i <= filledStars
            ? 'fill-yellow-400 text-yellow-400'
            : 'fill-transparent text-gray-300',
        ]"
      />
    </div>

    <!-- Review Count Text -->
    <span
      class="text-sm font-sans"
      :class="[props.reviewCount > 0 ? 'text-gray-600' : 'text-gray-400 italic']"
      data-testid="rating-text"
    >
      {{ reviewText }}
    </span>
  </div>
</template>

<style scoped>
/**
 * We use standard Tailwind classes from the WEACT design system.
 * The -space-x-px on the container allows stars to sit slightly tighter,
 * mimicking high-end editorial rating displays.
 */
.font-sans {
  font-family: var(--font-family-sans, 'Inter', ui-sans-serif, system-ui);
}

/* Subtle styling for "Aucun avis" state */
.italic {
  letter-spacing: -0.01em;
}
</style>

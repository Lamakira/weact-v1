<script setup lang="ts">
import { computed } from 'vue'
import type { Review } from '@/features/rating/types'
import ReviewCard from './ReviewCard.vue'

const props = defineProps<{
  reviews: Review[]
  currentPage: number
  lastPage: number
  total: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'page-change', page: number): void
}>()

function goToPage(page: number): void {
  if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
    emit('page-change', page)
  }
}

/**
 * Compute visible page numbers for windowed pagination.
 * Shows at most 5 page numbers with ellipsis for large page counts.
 * Returns array of numbers or 'ellipsis' strings.
 */
const visiblePages = computed((): (number | 'ellipsis')[] => {
  const total = props.lastPage
  const current = props.currentPage

  // If 7 or fewer pages, show all
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }

  const pages: (number | 'ellipsis')[] = []

  // Always show first page
  pages.push(1)

  // Calculate window around current page
  const windowStart = Math.max(2, current - 1)
  const windowEnd = Math.min(total - 1, current + 1)

  // Add ellipsis before window if needed
  if (windowStart > 2) {
    pages.push('ellipsis')
  }

  // Add window pages
  for (let i = windowStart; i <= windowEnd; i++) {
    pages.push(i)
  }

  // Add ellipsis after window if needed
  if (windowEnd < total - 1) {
    pages.push('ellipsis')
  }

  // Always show last page
  pages.push(total)

  return pages
})
</script>

<template>
  <div data-testid="reviews-list">
    <!-- Loading State -->
    <div
      v-if="loading"
      class="py-8 text-center"
      data-testid="reviews-loading"
    >
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
      <p class="mt-2 text-sm text-gray-500">Chargement des avis...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="reviews.length === 0"
      class="text-center py-8"
      data-testid="reviews-empty"
    >
      <div class="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          stroke="currentColor"
          class="w-6 h-6 text-gray-400"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"
          />
        </svg>
      </div>
      <p class="text-gray-500">Aucune note pour le moment</p>
      <p class="text-sm text-gray-400 mt-1">
        Les avis apparaîtront ici après la réalisation de missions.
      </p>
    </div>

    <!-- Reviews List -->
    <template v-else>
      <div class="divide-y divide-gray-100">
        <ReviewCard
          v-for="review in reviews"
          :key="review.id"
          :review="review"
        />
      </div>

      <!-- Pagination -->
      <div
        v-if="lastPage > 1"
        class="flex items-center justify-between pt-4 mt-4 border-t border-gray-100"
        data-testid="reviews-pagination"
      >
        <p class="text-sm text-gray-500">
          {{ total }} avis au total
        </p>
        <div class="flex items-center gap-2">
          <!-- Previous Button -->
          <button
            type="button"
            class="px-3 py-1 text-sm font-medium rounded-md transition-colors"
            :class="
              currentPage === 1
                ? 'text-gray-300 cursor-not-allowed'
                : 'text-gray-700 hover:bg-gray-100'
            "
            :disabled="currentPage === 1"
            data-testid="pagination-prev"
            @click="goToPage(currentPage - 1)"
          >
            Précédent
          </button>

          <!-- Page Numbers -->
          <div class="flex items-center gap-1">
            <template v-for="(page, index) in visiblePages" :key="index">
              <span
                v-if="page === 'ellipsis'"
                class="w-8 h-8 flex items-center justify-center text-gray-400"
                data-testid="pagination-ellipsis"
              >
                ...
              </span>
              <button
                v-else
                type="button"
                class="w-8 h-8 text-sm font-medium rounded-md transition-colors"
                :class="
                  page === currentPage
                    ? 'bg-primary-600 text-white'
                    : 'text-gray-700 hover:bg-gray-100'
                "
                :data-testid="`pagination-page-${page}`"
                @click="goToPage(page)"
              >
                {{ page }}
              </button>
            </template>
          </div>

          <!-- Next Button -->
          <button
            type="button"
            class="px-3 py-1 text-sm font-medium rounded-md transition-colors"
            :class="
              currentPage === lastPage
                ? 'text-gray-300 cursor-not-allowed'
                : 'text-gray-700 hover:bg-gray-100'
            "
            :disabled="currentPage === lastPage"
            data-testid="pagination-next"
            @click="goToPage(currentPage + 1)"
          >
            Suivant
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

interface Props {
  currentPage: number
  totalPages: number
  /** Maximum number of visible page buttons (excluding prev/next) */
  maxVisiblePages?: number
}

const props = withDefaults(defineProps<Props>(), {
  maxVisiblePages: 5,
})

const emit = defineEmits<{
  'page-change': [page: number]
}>()

const hasPrevious = computed(() => props.currentPage > 1)
const hasNext = computed(() => props.currentPage < props.totalPages)

/**
 * Calculate which page numbers to display
 * Returns an array that may include numbers and 'ellipsis' markers
 */
const visiblePages = computed(() => {
  const pages: (number | 'ellipsis')[] = []
  const { currentPage, totalPages, maxVisiblePages } = props

  if (totalPages <= maxVisiblePages) {
    // Show all pages if total fits within max
    for (let i = 1; i <= totalPages; i++) {
      pages.push(i)
    }
  } else {
    // Always show first page
    pages.push(1)

    // Calculate middle range
    const halfVisible = Math.floor((maxVisiblePages - 2) / 2)
    let startPage = Math.max(2, currentPage - halfVisible)
    let endPage = Math.min(totalPages - 1, currentPage + halfVisible)

    // Adjust if near the start
    if (currentPage <= halfVisible + 2) {
      endPage = maxVisiblePages - 1
    }

    // Adjust if near the end
    if (currentPage >= totalPages - halfVisible - 1) {
      startPage = totalPages - maxVisiblePages + 2
    }

    // Add ellipsis before middle pages if needed
    if (startPage > 2) {
      pages.push('ellipsis')
    }

    // Add middle pages
    for (let i = startPage; i <= endPage; i++) {
      pages.push(i)
    }

    // Add ellipsis after middle pages if needed
    if (endPage < totalPages - 1) {
      pages.push('ellipsis')
    }

    // Always show last page
    pages.push(totalPages)
  }

  return pages
})

function goToPage(page: number): void {
  if (page >= 1 && page <= props.totalPages && page !== props.currentPage) {
    emit('page-change', page)
  }
}

function goToPrevious(): void {
  if (hasPrevious.value) {
    emit('page-change', props.currentPage - 1)
  }
}

function goToNext(): void {
  if (hasNext.value) {
    emit('page-change', props.currentPage + 1)
  }
}
</script>

<template>
  <nav
    class="flex items-center justify-center gap-1"
    role="navigation"
    aria-label="Pagination"
  >
    <!-- Previous Button -->
    <button
      type="button"
      :disabled="!hasPrevious"
      class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 transition-all duration-200 hover:bg-gray-50 hover:border-[#198496]/30 hover:text-[#198496] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white disabled:hover:border-gray-200 disabled:hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
      aria-label="Page précédente"
      data-testid="pagination-previous"
      @click="goToPrevious"
    >
      <ChevronLeft class="h-4 w-4" />
    </button>

    <!-- Page Numbers -->
    <template v-for="(page, index) in visiblePages" :key="index">
      <!-- Ellipsis -->
      <span
        v-if="page === 'ellipsis'"
        class="flex h-9 w-9 items-center justify-center text-gray-400"
        aria-hidden="true"
      >
        …
      </span>

      <!-- Page Number Button -->
      <button
        v-else
        type="button"
        :class="[
          'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2',
          page === currentPage
            ? 'bg-[#198496] text-white shadow-sm'
            : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:border-[#198496]/30 hover:text-[#198496]',
        ]"
        :aria-current="page === currentPage ? 'page' : undefined"
        :aria-label="`Page ${page}`"
        :data-testid="`pagination-page-${page}`"
        @click="goToPage(page)"
      >
        {{ page }}
      </button>
    </template>

    <!-- Next Button -->
    <button
      type="button"
      :disabled="!hasNext"
      class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 transition-all duration-200 hover:bg-gray-50 hover:border-[#198496]/30 hover:text-[#198496] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white disabled:hover:border-gray-200 disabled:hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
      aria-label="Page suivante"
      data-testid="pagination-next"
      @click="goToNext"
    >
      <ChevronRight class="h-4 w-4" />
    </button>
  </nav>
</template>

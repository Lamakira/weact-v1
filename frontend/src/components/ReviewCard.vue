<script setup lang="ts">
import type { Review } from '@/features/rating/types'

defineProps<{
  review: Review
}>()
</script>

<template>
  <div
    class="flex gap-4 py-4 border-b border-gray-100 last:border-b-0"
    data-testid="review-card"
  >
    <!-- Rater Avatar -->
    <div class="flex-shrink-0">
      <img
        v-if="review.rater.profile_photo_url"
        :src="review.rater.profile_photo_url"
        :alt="review.rater.display_name"
        class="w-10 h-10 rounded-full object-cover"
        data-testid="rater-avatar"
      />
      <div
        v-else
        class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center"
        data-testid="rater-avatar-placeholder"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          stroke="currentColor"
          class="w-5 h-5 text-gray-400"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
          />
        </svg>
      </div>
    </div>

    <!-- Review Content -->
    <div class="flex-1 min-w-0">
      <!-- Header: Name, Stars, Date -->
      <div class="flex items-start justify-between gap-2">
        <div>
          <p
            class="font-medium text-gray-900 text-sm"
            data-testid="rater-name"
          >
            {{ review.rater.display_name }}
          </p>
          <!-- Stars -->
          <div
            class="flex items-center gap-0.5 mt-0.5"
            data-testid="review-stars"
          >
            <svg
              v-for="star in 5"
              :key="star"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
              class="w-4 h-4"
              :class="star <= review.score ? 'text-yellow-400' : 'text-gray-200'"
              :data-testid="`star-${star}`"
              :data-filled="star <= review.score"
            >
              <path
                fill-rule="evenodd"
                d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
        </div>
        <span
          class="text-xs text-gray-400 whitespace-nowrap"
          data-testid="review-date"
        >
          {{ review.formatted_date }}
        </span>
      </div>

      <!-- Comment (if present) -->
      <p
        v-if="review.comment"
        class="mt-2 text-sm text-gray-600"
        data-testid="review-comment"
      >
        {{ review.comment }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Briefcase, Calendar } from 'lucide-vue-next'
import type { FaceExperience } from '../types'

/**
 * Props
 */
const props = defineProps<{
  experiences: FaceExperience[]
}>()

/**
 * Computed: Has experiences
 */
const hasExperiences = computed((): boolean => props.experiences.length > 0)

/**
 * Computed: Experiences sorted from most recent to oldest
 * Ongoing experiences first, then by date_debut descending
 */
const sortedExperiences = computed(() => {
  return [...props.experiences].sort((a, b) => {
    // Ongoing experiences come first
    if (a.is_ongoing && !b.is_ongoing) return -1
    if (!a.is_ongoing && b.is_ongoing) return 1

    // Then sort by date_debut descending (most recent first)
    const dateA = a.date_debut ? new Date(a.date_debut).getTime() : 0
    const dateB = b.date_debut ? new Date(b.date_debut).getTime() : 0
    return dateB - dateA
  })
})
</script>

<template>
  <div class="rounded-2xl border border-border bg-card p-6">
    <div class="mb-4 flex items-center gap-2">
      <Briefcase class="h-5 w-5 text-primary" />
      <h2 class="text-lg font-semibold text-foreground">Expériences professionnelles</h2>
    </div>

    <div v-if="hasExperiences" class="space-y-4">
      <div
        v-for="experience in sortedExperiences"
        :key="experience.id"
        class="relative border-l-2 border-primary/30 pl-4"
      >
        <!-- Timeline dot -->
        <div
          class="absolute -left-[5px] top-1 h-2 w-2 rounded-full bg-primary"
        />

        <!-- Experience content -->
        <div>
          <h3 class="font-semibold text-foreground">
            {{ experience.titre }}
          </h3>

          <!-- Period -->
          <div
            v-if="experience.formatted_period"
            class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground"
          >
            <Calendar class="h-3.5 w-3.5" />
            <span>{{ experience.formatted_period }}</span>
            <span
              v-if="experience.is_ongoing"
              class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
            >
              En cours
            </span>
          </div>

          <!-- Description -->
          <p
            v-if="experience.description"
            class="mt-2 text-sm leading-relaxed text-muted-foreground"
          >
            {{ experience.description }}
          </p>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="py-6 text-center">
      <Briefcase class="mx-auto h-10 w-10 text-muted-foreground/50" />
      <p class="mt-2 text-sm text-muted-foreground">
        Aucune expérience renseignée
      </p>
    </div>
  </div>
</template>

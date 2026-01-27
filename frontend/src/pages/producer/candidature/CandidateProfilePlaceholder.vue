<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, User, Clock } from 'lucide-vue-next'

/**
 * Router and route
 */
const route = useRoute()
const router = useRouter()

/**
 * Computed: Candidate ID from route params
 */
const candidateId = computed(() => route.params.id)

/**
 * Navigate back
 */
function goBack(): void {
  router.back()
}
</script>

<template>
  <div class="min-h-screen bg-background pb-8">
    <!-- Header Section -->
    <header class="border-b border-border bg-card">
      <div class="container mx-auto px-4 py-6 sm:px-6">
        <!-- Back Button -->
        <button
          type="button"
          class="mb-4 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Retour
        </button>

        <h1 class="text-2xl font-bold text-foreground sm:text-3xl">
          Profil du candidat
        </h1>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto mt-12 px-4 sm:px-6">
      <div
        class="mx-auto flex max-w-md flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-16 text-center"
      >
        <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-primary/10">
          <User class="h-10 w-10 text-primary" />
        </div>

        <h2 class="text-xl font-bold text-foreground">
          Fonctionnalité à venir
        </h2>

        <p class="mt-3 max-w-xs text-muted-foreground">
          La consultation du profil complet des candidats sera disponible dans la prochaine version.
        </p>

        <div class="mt-6 flex items-center gap-2 rounded-full bg-muted px-4 py-2 text-sm text-muted-foreground">
          <Clock class="h-4 w-4" />
          <span>Prévu dans la Story 6-5</span>
        </div>

        <p class="mt-4 text-xs text-muted-foreground">
          ID candidat: {{ candidateId }}
        </p>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { Check } from 'lucide-vue-next'
import ProfileCompletionIndicator from './ProfileCompletionIndicator.vue'

interface Props {
  percentage?: number
  missingCount?: number
  isLoading?: boolean
}

withDefaults(defineProps<Props>(), {
  percentage: 0,
  missingCount: 0,
  isLoading: false,
})
</script>

<template>
  <div
    class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-col h-full"
    data-testid="profile-completion-card"
  >
    <h3 class="text-sm font-semibold text-foreground mb-6">Complétion du profil</h3>

    <div class="flex flex-col items-center justify-center flex-1 gap-5">
      <ProfileCompletionIndicator
        :percentage="isLoading ? undefined : percentage"
        :is-complete="percentage === 100"
        variant="compact"
      />

      <div class="text-center">
        <!-- Loading state -->
        <div v-if="isLoading" class="animate-pulse">
          <div class="h-4 w-32 bg-muted rounded mx-auto mb-4" />
          <div class="h-9 w-36 bg-muted rounded-lg mx-auto" />
        </div>

        <!-- Has missing items -->
        <template v-else-if="missingCount > 0">
          <p class="text-sm text-muted-foreground mb-4" data-testid="missing-count">
            {{ missingCount }} {{ missingCount > 1 ? 'éléments manquants' : 'élément manquant' }}
          </p>
          <RouterLink
            to="/face/profile"
            class="inline-flex items-center justify-center px-5 py-2 text-sm font-medium text-white bg-[var(--color-weact)] hover:bg-[var(--color-weact-600)] rounded-lg transition-colors shadow-sm"
            data-testid="complete-profile-link"
          >
            Compléter mon profil
          </RouterLink>
        </template>

        <!-- Profile complete -->
        <template v-else>
          <div
            class="flex items-center justify-center gap-2 text-[var(--color-weact)] font-semibold py-2"
            data-testid="complete-badge"
          >
            <div class="bg-[var(--color-weact-50)] p-1 rounded-full">
              <Check class="w-4 h-4" stroke-width="3" />
            </div>
            <span class="text-sm">Profil complet</span>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

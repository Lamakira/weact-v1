<script setup lang="ts">
/**
 * ProfileCompletionCard Component
 * Compact card showing profile completion status.
 * Pattern matched to WalletCard and MissionsQuickAccessCard styles.
 */
import { computed } from 'vue'
import { UserCheck, ChevronRight } from 'lucide-vue-next'
import { Skeleton } from '@/components/ui/skeleton'

interface Props {
  percentage?: number
  missingCount?: number
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  percentage: 0,
  missingCount: 0,
  isLoading: false,
})

const emit = defineEmits<{
  click: []
}>()

/** Emit click event for parent navigation handling */
function handleClick(): void {
  emit('click')
}

/** Get badge color classes based on percentage */
const badgeClasses = computed(() => {
  if (props.percentage === 100) {
    return 'bg-green-100 text-green-700'
  } else if (props.percentage >= 80) {
    return 'bg-orange-100 text-orange-700'
  } else if (props.percentage >= 50) {
    return 'bg-yellow-100 text-yellow-700'
  } else {
    return 'bg-red-100 text-red-700'
  }
})
</script>

<template>
  <div
    class="relative overflow-hidden bg-white rounded-2xl p-4 shadow-sm border border-gray-100 cursor-pointer hover:shadow-md hover:border-primary/30 transition-all group"
    role="button"
    tabindex="0"
    aria-label="Compléter mon profil"
    data-testid="profile-completion-card"
    @click="handleClick"
    @keydown.enter="handleClick"
    @keydown.space.prevent="handleClick"
  >
    <div class="flex flex-col gap-3">
      <!-- Header Row: Icon & Badge -->
      <div class="flex items-center justify-between">
        <!-- Icon Container -->
        <div
          class="h-11 w-11 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20"
          data-testid="profile-completion-icon"
        >
          <UserCheck class="h-5 w-5 text-primary" />
        </div>

        <!-- Percentage Badge -->
        <template v-if="isLoading">
          <Skeleton class="h-7 w-12 rounded-full" data-testid="profile-completion-badge-loading" />
        </template>
        <template v-else>
          <span
            class="inline-flex items-center justify-center min-w-[2.5rem] h-7 px-2 rounded-full text-xs font-bold"
            :class="badgeClasses"
            data-testid="profile-completion-badge"
          >
            {{ percentage }}%
          </span>
        </template>
      </div>

      <!-- Content Row -->
      <div class="flex items-end justify-between">
        <div class="space-y-0.5">
          <h3
            class="text-base font-semibold text-gray-900"
            data-testid="profile-completion-title"
          >
            Complétion du profil
          </h3>

          <template v-if="isLoading">
            <Skeleton class="h-4 w-32" data-testid="profile-completion-subtitle-loading" />
          </template>
          <p
            v-else
            class="text-sm text-gray-500"
            data-testid="profile-completion-subtitle"
          >
            <template v-if="percentage === 100">
              Profil complet
            </template>
            <template v-else>
              {{ missingCount }} {{ missingCount > 1 ? 'éléments manquants' : 'élément manquant' }}
            </template>
          </p>
        </div>

        <!-- Clickable indicator -->
        <div
          class="flex items-center gap-1 text-xs font-medium text-primary group-hover:translate-x-0.5 transition-transform"
          data-testid="profile-completion-action"
        >
          <span>Voir</span>
          <ChevronRight class="h-4 w-4" />
        </div>
      </div>
    </div>

    <!-- Decorative Background Icon -->
    <div class="absolute -bottom-2 -right-2 opacity-5 pointer-events-none">
      <UserCheck class="h-16 w-16 text-gray-900" />
    </div>
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}
.bg-primary\/10 {
  background-color: rgba(25, 132, 150, 0.1);
}
.border-primary\/20 {
  border-color: rgba(25, 132, 150, 0.2);
}
.border-primary\/30 {
  border-color: rgba(25, 132, 150, 0.3);
}
.hover\:border-primary\/30:hover {
  border-color: rgba(25, 132, 150, 0.3);
}
</style>

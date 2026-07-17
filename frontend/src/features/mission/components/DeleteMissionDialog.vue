<script setup lang="ts">
import { computed } from 'vue'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'

interface Props {
  isOpen: boolean
  missionTitle: string
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
})

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()

// Close when the host page is deactivated by <keep-alive> — the teleported
// overlay would otherwise stay in <body> on top of the next page.
useDismissOnDeactivate(() => props.isOpen, () => emit('cancel'))

const handleCancel = () => {
  if (!props.isLoading) {
    emit('cancel')
  }
}

const handleConfirm = () => {
  if (!props.isLoading) {
    emit('confirm')
  }
}

const handleBackdropClick = (event: MouseEvent) => {
  if (event.target === event.currentTarget && !props.isLoading) {
    emit('cancel')
  }
}

const truncatedTitle = computed(() => {
  if (props.missionTitle.length > 50) {
    return props.missionTitle.substring(0, 50) + '...'
  }
  return props.missionTitle
})
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click="handleBackdropClick"
      >
        <Transition name="scale">
          <div
            v-if="isOpen"
            class="bg-background rounded-lg shadow-xl max-w-md w-full p-6 border border-border"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-dialog-title"
          >
            <!-- Warning Icon -->
            <div class="flex justify-center mb-4">
              <div class="w-12 h-12 rounded-full bg-destructive/10 flex items-center justify-center">
                <svg
                  class="w-6 h-6 text-destructive"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                  />
                </svg>
              </div>
            </div>

            <!-- Title -->
            <h2 id="delete-dialog-title" class="text-lg font-semibold text-center text-foreground mb-2">
              Supprimer la mission ?
            </h2>

            <!-- Mission Title -->
            <p class="text-sm text-muted-foreground text-center mb-4">
              « {{ truncatedTitle }} »
            </p>

            <!-- Warning Text -->
            <p class="text-sm text-muted-foreground text-center mb-6">
              Cette action est irréversible. La mission sera définitivement supprimée.
            </p>

            <!-- Buttons -->
            <div class="flex gap-3">
              <button
                type="button"
                :disabled="isLoading"
                class="flex-1 px-4 py-2.5 rounded-lg border border-border bg-secondary text-secondary-foreground font-medium transition-colors hover:bg-secondary/80 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="handleCancel"
              >
                Annuler
              </button>
              <button
                type="button"
                :disabled="isLoading"
                class="flex-1 px-4 py-2.5 rounded-lg bg-destructive text-destructive-foreground font-medium transition-colors hover:bg-destructive/90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                @click="handleConfirm"
              >
                <svg
                  v-if="isLoading"
                  class="w-4 h-4 animate-spin"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  />
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  />
                </svg>
                <span>{{ isLoading ? 'Suppression...' : 'Confirmer la suppression' }}</span>
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
  transition: all 0.2s ease;
}

.scale-enter-from,
.scale-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>

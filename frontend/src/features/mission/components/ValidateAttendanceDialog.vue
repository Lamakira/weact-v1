<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { Users } from 'lucide-vue-next'

interface Props {
  isOpen: boolean
  missionTitle: string
  presentCount: number
  absentCount: number
  totalReleased: number
  totalRefunded: number
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
})

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()

const handleCancel = (): void => {
  if (!props.isLoading) {
    emit('cancel')
  }
}

const handleConfirm = (): void => {
  if (!props.isLoading) {
    emit('confirm')
  }
}

const handleBackdropClick = (event: MouseEvent): void => {
  if (event.target === event.currentTarget && !props.isLoading) {
    emit('cancel')
  }
}

const truncatedTitle = computed<string>(() => {
  if (props.missionTitle.length > 50) {
    return props.missionTitle.substring(0, 50) + '...'
  }
  return props.missionTitle
})

function formatCurrency(amount: number): string {
  return (
    new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      currencyDisplay: 'code',
    })
      .format(amount)
      .replace('XOF', '')
      .trim() + ' XOF'
  )
}

const handleKeydown = (event: KeyboardEvent): void => {
  if (event.key === 'Escape' && props.isOpen && !props.isLoading) {
    emit('cancel')
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
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
            aria-labelledby="validate-attendance-dialog-title"
          >
            <!-- Users Icon -->
            <div class="flex justify-center mb-4">
              <div class="w-12 h-12 rounded-full bg-amber-500/10 flex items-center justify-center">
                <Users class="w-6 h-6 text-amber-500" />
              </div>
            </div>

            <!-- Title -->
            <h2
              id="validate-attendance-dialog-title"
              class="text-lg font-semibold text-center text-foreground mb-2"
            >
              Confirmer la validation des présences ?
            </h2>

            <!-- Mission Title -->
            <p class="text-sm text-muted-foreground text-center mb-4" :title="missionTitle">
              {{ truncatedTitle }}
            </p>

            <!-- Financial Recap -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 space-y-2">
              <p class="text-sm text-amber-900 text-center">
                {{ presentCount }} Face(s) présente(s) —
                <strong>{{ formatCurrency(totalReleased) }}</strong> versés.
              </p>
              <p class="text-sm text-amber-900 text-center">
                {{ absentCount }} Face(s) absente(s) —
                <strong>{{ formatCurrency(totalRefunded) }}</strong> remboursement(s) en cours
                (72h pour contestation).
              </p>
            </div>

            <!-- Warning Text -->
            <p class="text-sm text-muted-foreground text-center mb-6">
              Les Faces marquées absentes recevront un email et auront 72h pour contester.
              Cette action est définitive.
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
                class="flex-1 px-4 py-2.5 rounded-lg bg-amber-500 text-white font-medium transition-colors hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
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
                <span>{{ isLoading ? 'Validation...' : 'Valider' }}</span>
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

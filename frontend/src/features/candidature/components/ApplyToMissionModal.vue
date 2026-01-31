<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { X, Loader2, CheckCircle, AlertCircle, Mail, ShieldAlert } from 'lucide-vue-next'
import { useApplyToMission } from '../composables/useApplyToMission'
import { authApi } from '@/features/auth/services/authApi'
import { useToast } from '@/composables/useToast'

/**
 * Props
 */
const props = defineProps<{
  isOpen: boolean
  missionId: number
  missionTitle: string
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success', candidature: { id: number; status: string; status_label: string }): void
}>()

/**
 * State
 */
const motivation = ref('')
const MAX_CHARS = 2000

const { isLoading, error, errorCode, isSuccess, apply, reset } = useApplyToMission()
const toast = useToast()

// Email verification resend state
const isResendingVerification = ref(false)

// Check if error is email not verified
const isEmailNotVerified = computed(() => errorCode.value === 'EMAIL_NOT_VERIFIED')

/**
 * Computed
 */
const charCount = computed(() => motivation.value.length)
const isOverLimit = computed(() => charCount.value > MAX_CHARS)
const canSubmit = computed(() => !isLoading.value && !isOverLimit.value && !isSuccess.value)

/**
 * Watch for modal close to reset state
 */
watch(
  () => props.isOpen,
  (newValue) => {
    if (!newValue) {
      // Reset state when modal closes
      setTimeout(() => {
        motivation.value = ''
        isResendingVerification.value = false
        reset()
      }, 300) // Wait for animation
    }
  },
)

/**
 * Actions
 */
async function handleSubmit(): Promise<void> {
  if (!canSubmit.value) return

  const result = await apply(props.missionId, motivation.value || undefined)

  if (result.success && result.data) {
    emit('success', {
      id: result.data.id,
      status: result.data.status,
      status_label: result.data.status_label,
    })

    // Auto-close after showing success
    setTimeout(() => {
      emit('close')
    }, 1500)
  }
}

function handleClose(): void {
  if (!isLoading.value) {
    emit('close')
  }
}

/**
 * Resend verification email
 */
async function handleResendVerification(): Promise<void> {
  isResendingVerification.value = true
  try {
    const result = await authApi.resendVerificationEmail()
    if (result.sent) {
      toast.success('Un email de vérification a été envoyé.')
    } else if (result.verified) {
      toast.success('Votre email est déjà vérifié. Vous pouvez réessayer.')
      reset()
    }
  } catch (err) {
    if ((err as { response?: { status?: number } })?.response?.status === 429) {
      toast.warning('Veuillez patienter avant de renvoyer un email.')
    } else {
      toast.error("Impossible d'envoyer l'email. Veuillez réessayer.")
    }
  } finally {
    isResendingVerification.value = false
  }
}

function handleBackdropClick(event: MouseEvent): void {
  if (event.target === event.currentTarget) {
    handleClose()
  }
}
</script>

<template>
  <!-- Modal Backdrop -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        @click="handleBackdropClick"
      >
        <!-- Modal Content -->
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-200"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="isOpen"
            class="w-full max-w-lg rounded-2xl border border-border bg-card shadow-2xl"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border p-4 sm:p-6">
              <h2 class="text-lg font-semibold text-foreground sm:text-xl">
                Postuler à cette mission
              </h2>
              <button
                type="button"
                class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
                :disabled="isLoading"
                @click="handleClose"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-4 sm:p-6">
              <!-- Success State -->
              <div
                v-if="isSuccess"
                class="flex flex-col items-center justify-center py-8 text-center"
              >
                <div class="mb-4 rounded-full bg-green-100 p-4 text-green-600 dark:bg-green-900/20">
                  <CheckCircle class="h-12 w-12" />
                </div>
                <h3 class="text-xl font-semibold text-foreground">Candidature envoyée !</h3>
                <p class="mt-2 text-muted-foreground">
                  Votre candidature a été envoyée avec succès.
                </p>
              </div>

              <!-- Form -->
              <div v-else>
                <!-- Mission Title Reminder -->
                <p class="mb-4 text-sm text-muted-foreground">
                  Vous postulez pour la mission : <span class="font-medium text-foreground">{{ missionTitle }}</span>
                </p>

                <!-- Email Not Verified Alert -->
                <div
                  v-if="isEmailNotVerified"
                  class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4"
                  data-testid="email-not-verified-alert"
                >
                  <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                      <ShieldAlert class="h-5 w-5 text-amber-600" />
                    </div>
                    <div class="flex-1">
                      <h4 class="text-sm font-semibold text-amber-800">Email non vérifié</h4>
                      <p class="mt-1 text-sm text-amber-700">
                        Vous devez vérifier votre adresse email avant de pouvoir postuler à une mission.
                      </p>
                      <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="isResendingVerification"
                        @click="handleResendVerification"
                        data-testid="resend-verification-button"
                      >
                        <Mail v-if="!isResendingVerification" class="h-4 w-4" />
                        <Loader2 v-else class="h-4 w-4 animate-spin" />
                        {{ isResendingVerification ? 'Envoi en cours...' : "Renvoyer l'email de vérification" }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Generic Error Alert -->
                <div
                  v-else-if="error"
                  class="mb-4 flex items-start gap-3 rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-destructive"
                >
                  <AlertCircle class="h-5 w-5 flex-shrink-0" />
                  <p class="text-sm">{{ error }}</p>
                </div>

                <!-- Motivation Textarea -->
                <div class="space-y-2">
                  <label
                    for="motivation"
                    class="block text-sm font-medium text-foreground"
                  >
                    Message de motivation
                    <span class="text-muted-foreground">(optionnel)</span>
                  </label>
                  <textarea
                    id="motivation"
                    v-model="motivation"
                    rows="5"
                    class="w-full resize-none rounded-lg border border-border bg-background px-3 py-2 text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{ 'border-destructive focus:border-destructive focus:ring-destructive/20': isOverLimit }"
                    placeholder="Expliquez pourquoi vous seriez parfait(e) pour cette mission..."
                    :disabled="isLoading"
                  />
                  <div class="flex items-center justify-between text-xs">
                    <span
                      v-if="isOverLimit"
                      class="text-destructive"
                    >
                      Le message ne doit pas dépasser {{ MAX_CHARS }} caractères
                    </span>
                    <span v-else />
                    <span
                      class="tabular-nums"
                      :class="isOverLimit ? 'text-destructive' : 'text-muted-foreground'"
                    >
                      {{ charCount }} / {{ MAX_CHARS }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div
              v-if="!isSuccess"
              class="flex flex-col-reverse gap-3 border-t border-border p-4 sm:flex-row sm:justify-end sm:p-6"
            >
              <button
                type="button"
                class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="isLoading"
                @click="handleClose"
              >
                Annuler
              </button>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canSubmit"
                @click="handleSubmit"
              >
                <Loader2
                  v-if="isLoading"
                  class="h-4 w-4 animate-spin"
                />
                <span>{{ isLoading ? 'Envoi...' : 'Envoyer ma candidature' }}</span>
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

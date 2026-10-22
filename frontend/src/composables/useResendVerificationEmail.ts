import { ref, computed, onUnmounted } from 'vue'
import { authApi } from '@/features/auth/services/authApi'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

/**
 * Renvoi de l'e-mail de vérification avec délai de 60 s entre deux envois.
 * Partagé par EmailVerificationBanner et la barre d'alertes du compte.
 */
export function useResendVerificationEmail() {
  const authStore = useAuthStore()
  const toast = useToast()

  const isLoading = ref(false)
  const cooldownSeconds = ref(0)
  const cooldownInterval = ref<ReturnType<typeof setInterval> | null>(null)

  const canResend = computed(() => !isLoading.value && cooldownSeconds.value === 0)

  function startCooldown(): void {
    cooldownSeconds.value = 60
    cooldownInterval.value = setInterval(() => {
      cooldownSeconds.value--
      if (cooldownSeconds.value <= 0) {
        if (cooldownInterval.value) {
          clearInterval(cooldownInterval.value)
          cooldownInterval.value = null
        }
      }
    }, 1000)
  }

  async function handleResend(): Promise<void> {
    if (!canResend.value) return

    isLoading.value = true
    try {
      const result = await authApi.resendVerificationEmail()

      if (result.verified) {
        toast.success('Votre email est déjà vérifié.')
        // Refresh user data to update the UI
        await authStore.refreshUser()
      } else if (result.sent) {
        toast.success('Un email de vérification a été envoyé.')
        startCooldown()
      }
    } catch (error) {
      // Handle throttle error (429)
      if ((error as { response?: { status?: number } })?.response?.status === 429) {
        toast.warning('Veuillez patienter avant de renvoyer un email.')
        startCooldown()
      } else {
        toast.error('Impossible d\'envoyer l\'email. Veuillez réessayer.')
      }
    } finally {
      isLoading.value = false
    }
  }

  onUnmounted(() => {
    if (cooldownInterval.value) {
      clearInterval(cooldownInterval.value)
    }
  })

  return { isLoading, cooldownSeconds, canResend, handleResend }
}

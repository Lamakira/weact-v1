<script setup lang="ts">
/**
 * VerifyEmailPage
 * Handles email verification via signed URL.
 * User lands here from verification email link.
 */
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle, XCircle, Loader2, Mail } from 'lucide-vue-next'
import { authApi } from '@/features/auth/services/authApi'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

type VerificationState = 'loading' | 'success' | 'error' | 'already_verified'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

// State
const state = ref<VerificationState>('loading')
const errorMessage = ref('')
const isResending = ref(false)

// Get URL parameters
const id = computed(() => route.params.id as string)
const hash = computed(() => route.params.hash as string)
const expires = computed(() => route.query.expires as string)
const signature = computed(() => route.query.signature as string)

// Verify email on mount
onMounted(async () => {
  if (!id.value || !hash.value || !expires.value || !signature.value) {
    state.value = 'error'
    errorMessage.value = 'Lien de vérification invalide ou incomplet.'
    return
  }

  try {
    const result = await authApi.verifyEmail(id.value, hash.value, expires.value, signature.value)

    if (result.already_verified) {
      state.value = 'already_verified'
      // Refresh user data from API to ensure consistency
      if (authStore.isAuthenticated) {
        await authStore.refreshUser()
      }
    } else if (result.verified) {
      state.value = 'success'
      // Refresh user data from API to ensure consistency
      if (authStore.isAuthenticated) {
        await authStore.refreshUser()
      }
    }
  } catch (error) {
    state.value = 'error'
    const errorCode = (error as { response?: { data?: { error?: { code?: string } } } })?.response
      ?.data?.error?.code

    if (errorCode === 'VERIFICATION_LINK_EXPIRED') {
      errorMessage.value = 'Ce lien de vérification a expiré. Veuillez en demander un nouveau.'
    } else if (errorCode === 'INVALID_VERIFICATION_LINK') {
      errorMessage.value = 'Ce lien de vérification est invalide.'
    } else if (errorCode === 'USER_NOT_FOUND') {
      errorMessage.value = 'Utilisateur non trouvé.'
    } else {
      errorMessage.value = 'Une erreur est survenue lors de la vérification.'
    }
  }
})

// Redirect to dashboard
function goToDashboard(): void {
  if (authStore.isFace) {
    router.push({ name: 'face-dashboard' })
  } else if (authStore.isProducer) {
    router.push({ name: 'producer-dashboard' })
  } else {
    router.push({ name: 'login' })
  }
}

// Resend verification email
async function handleResend(): Promise<void> {
  if (!authStore.isAuthenticated) {
    toast.warning('Veuillez vous connecter pour renvoyer l\'email de vérification.')
    router.push({ name: 'login' })
    return
  }

  isResending.value = true
  try {
    const result = await authApi.resendVerificationEmail()
    if (result.sent) {
      toast.success('Un nouvel email de vérification a été envoyé.')
    } else if (result.verified) {
      toast.success('Votre email est déjà vérifié.')
      goToDashboard()
    }
  } catch {
    toast.error('Impossible d\'envoyer l\'email. Veuillez réessayer.')
  } finally {
    isResending.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full">
      <!-- Loading State -->
      <div
        v-if="state === 'loading'"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"
        data-testid="verify-loading"
      >
        <Loader2 class="w-12 h-12 text-primary animate-spin mx-auto mb-4" />
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Vérification en cours...</h1>
        <p class="text-slate-500">Veuillez patienter pendant que nous vérifions votre email.</p>
      </div>

      <!-- Success State -->
      <div
        v-else-if="state === 'success'"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"
        data-testid="verify-success"
      >
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <CheckCircle class="w-8 h-8 text-green-600" />
        </div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Email vérifié !</h1>
        <p class="text-slate-500 mb-6">
          Votre adresse email a été vérifiée avec succès. Vous avez maintenant accès à toutes les fonctionnalités de la plateforme.
        </p>
        <button
          @click="goToDashboard"
          class="w-full py-3 px-4 bg-primary text-white font-medium rounded-xl hover:bg-primary/90 transition-colors"
          data-testid="go-to-dashboard-button"
        >
          Accéder à mon espace
        </button>
      </div>

      <!-- Already Verified State -->
      <div
        v-else-if="state === 'already_verified'"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"
        data-testid="verify-already"
      >
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <CheckCircle class="w-8 h-8 text-blue-600" />
        </div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Email déjà vérifié</h1>
        <p class="text-slate-500 mb-6">
          Votre adresse email est déjà vérifiée. Vous pouvez accéder à toutes les fonctionnalités de la plateforme.
        </p>
        <button
          @click="goToDashboard"
          class="w-full py-3 px-4 bg-primary text-white font-medium rounded-xl hover:bg-primary/90 transition-colors"
          data-testid="go-to-dashboard-button"
        >
          Accéder à mon espace
        </button>
      </div>

      <!-- Error State -->
      <div
        v-else-if="state === 'error'"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"
        data-testid="verify-error"
      >
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <XCircle class="w-8 h-8 text-red-600" />
        </div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Erreur de vérification</h1>
        <p class="text-slate-500 mb-6" data-testid="error-message">
          {{ errorMessage }}
        </p>
        <div class="space-y-3">
          <button
            v-if="authStore.isAuthenticated"
            @click="handleResend"
            :disabled="isResending"
            class="w-full py-3 px-4 bg-primary text-white font-medium rounded-xl hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            data-testid="resend-button"
          >
            <Mail v-if="!isResending" class="w-5 h-5" />
            <Loader2 v-else class="w-5 h-5 animate-spin" />
            {{ isResending ? 'Envoi en cours...' : 'Renvoyer un email de vérification' }}
          </button>
          <button
            @click="goToDashboard"
            class="w-full py-3 px-4 bg-gray-100 text-slate-700 font-medium rounded-xl hover:bg-gray-200 transition-colors"
            data-testid="back-button"
          >
            {{ authStore.isAuthenticated ? 'Retour à mon espace' : 'Se connecter' }}
          </button>
        </div>
      </div>
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
</style>

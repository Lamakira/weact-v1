<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle, XCircle, Loader2 } from 'lucide-vue-next'
import { emailChangeApi } from '@/features/auth/services/emailChangeApi'
import { getApiErrorCode } from '@/features/auth/services/authApi'

type ConfirmState = 'loading' | 'success' | 'error'

const route = useRoute()
const router = useRouter()

const state = ref<ConfirmState>('loading')
const errorMessage = ref('')

const id = computed(() => route.params.id as string)
const hash = computed(() => route.params.hash as string)
const expires = computed(() => route.query.expires as string)
const signature = computed(() => route.query.signature as string)

onMounted(async () => {
  if (!id.value || !hash.value || !expires.value || !signature.value) {
    state.value = 'error'
    errorMessage.value = 'Lien de confirmation invalide ou incomplet.'
    return
  }

  try {
    await emailChangeApi.confirmChange(id.value, hash.value, expires.value, signature.value)
    state.value = 'success'
  } catch (error: unknown) {
    state.value = 'error'
    const errorCode = getApiErrorCode(error)

    switch (errorCode) {
      case 'CONFIRMATION_LINK_EXPIRED':
        errorMessage.value = 'Ce lien de confirmation a expiré. Veuillez refaire une demande de changement d\'email.'
        break
      case 'INVALID_CONFIRMATION_LINK':
        errorMessage.value = 'Ce lien de confirmation est invalide.'
        break
      case 'NO_PENDING_EMAIL_CHANGE':
        errorMessage.value = 'Aucun changement d\'email en attente pour ce compte.'
        break
      case 'EMAIL_ALREADY_TAKEN':
        errorMessage.value = 'Cette adresse email est désormais utilisée par un autre compte.'
        break
      case 'USER_NOT_FOUND':
        errorMessage.value = 'Utilisateur non trouvé.'
        break
      default:
        errorMessage.value = 'Une erreur est survenue lors de la confirmation.'
    }
  }
})

function goToLogin(): void {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full">
      <!-- Loading State -->
      <div
        v-if="state === 'loading'"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"
        data-testid="confirm-loading"
      >
        <Loader2 class="w-12 h-12 text-primary animate-spin mx-auto mb-4" />
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Confirmation en cours...</h1>
        <p class="text-slate-500">Veuillez patienter pendant que nous mettons à jour votre email.</p>
      </div>

      <!-- Success State -->
      <div
        v-else-if="state === 'success'"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"
        data-testid="confirm-success"
      >
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <CheckCircle class="w-8 h-8 text-green-600" />
        </div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Email mis à jour !</h1>
        <p class="text-slate-500 mb-6">
          Votre adresse email a été changée avec succès. Vous pouvez maintenant vous connecter avec votre nouvelle adresse.
        </p>
        <button
          @click="goToLogin"
          class="w-full py-3 px-4 bg-primary text-white font-medium rounded-xl hover:bg-primary/90 transition-colors"
          data-testid="go-to-login-button"
        >
          Se connecter
        </button>
      </div>

      <!-- Error State -->
      <div
        v-else-if="state === 'error'"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"
        data-testid="confirm-error"
      >
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <XCircle class="w-8 h-8 text-red-600" />
        </div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Erreur de confirmation</h1>
        <p class="text-slate-500 mb-6" data-testid="error-message">
          {{ errorMessage }}
        </p>
        <button
          @click="goToLogin"
          class="w-full py-3 px-4 bg-gray-100 text-slate-700 font-medium rounded-xl hover:bg-gray-200 transition-colors"
          data-testid="back-button"
        >
          Retour à la connexion
        </button>
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

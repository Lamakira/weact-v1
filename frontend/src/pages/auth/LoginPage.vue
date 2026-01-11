<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import LoginForm from '@/features/auth/components/LoginForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

// Success message for password reset redirect
const successMessage = ref<string | null>(null)
// Warning message for session expired redirect
const warningMessage = ref<string | null>(null)

onMounted(() => {
  const message = route.query.message

  // Check for password reset success message from query
  if (message === 'password-reset-success') {
    successMessage.value = 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
    // Clean up the URL (preserve redirect if present)
    router.replace({ path: '/login', query: route.query.redirect ? { redirect: route.query.redirect } : {} })
  } else if (message === 'session-expired') {
    // Check for session expired message from query
    warningMessage.value = 'Session expirée, veuillez vous reconnecter.'
    toast.warning('Session expirée, veuillez vous reconnecter')
    // Clean up the URL (preserve redirect if present)
    router.replace({ path: '/login', query: route.query.redirect ? { redirect: route.query.redirect } : {} })
  }
})

function handleLoginSuccess(): void {
  // Check for redirect query param (e.g., from protected route redirect)
  const redirectPath = route.query.redirect as string
  if (redirectPath) {
    router.push(redirectPath)
    return
  }

  // Default: Redirect based on user role
  if (authStore.user?.userable_type === 'Face') {
    router.push('/face/dashboard')
  } else if (authStore.user?.userable_type === 'Producer') {
    router.push('/producer/dashboard')
  } else {
    // Fallback to home
    router.push('/')
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <!-- Logo -->
      <div class="flex justify-center">
        <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center">
          <span class="text-white text-2xl font-bold">W</span>
        </div>
      </div>

      <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
        Connexion à WEACT
      </h2>
      <p class="mt-2 text-center text-sm text-gray-600">
        Connectez-vous pour accéder à votre compte
      </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <!-- Success Message (e.g., after password reset) -->
        <div
          v-if="successMessage"
          class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg"
          data-testid="success-message"
        >
          <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clip-rule="evenodd"
              />
            </svg>
            <span class="text-sm font-medium text-green-800">{{ successMessage }}</span>
          </div>
        </div>

        <!-- Warning Message (e.g., after session expiration) -->
        <div
          v-if="warningMessage"
          class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg"
          data-testid="warning-message"
        >
          <div class="flex items-center">
            <svg class="w-5 h-5 text-amber-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"
              />
            </svg>
            <span class="text-sm font-medium text-amber-800">{{ warningMessage }}</span>
          </div>
        </div>

        <LoginForm @success="handleLoginSuccess" />

        <!-- Forgot password link -->
        <div class="mt-6 text-center">
          <router-link
            to="/forgot-password"
            class="text-sm font-medium text-primary hover:text-primary-dark"
          >
            Mot de passe oublié ?
          </router-link>
        </div>

        <!-- Divider -->
        <div class="mt-6">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-300" />
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-white text-gray-500">Pas encore de compte ?</span>
            </div>
          </div>
        </div>

        <!-- Registration links -->
        <div class="mt-6 grid grid-cols-2 gap-3">
          <router-link
            to="/register/face"
            class="w-full inline-flex justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            <span>Devenir Face</span>
          </router-link>
          <router-link
            to="/register/producer"
            class="w-full inline-flex justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            <span>Devenir Producteur</span>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.text-primary {
  color: #198496;
}
.hover\:text-primary-dark:hover {
  color: #156b7a;
}
</style>


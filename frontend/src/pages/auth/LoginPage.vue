<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import LoginForm from '@/features/auth/components/LoginForm.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// Success message for password reset redirect
const successMessage = ref<string | null>(null)

onMounted(() => {
  // Check for password reset success message from query
  if (route.query.message === 'password-reset-success') {
    successMessage.value = 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
    // Clean up the URL
    router.replace({ path: '/login' })
  }
})

function handleLoginSuccess(): void {
  // Redirect based on user role
  if (authStore.user?.userable_type === 'Face') {
    router.push('/dashboard/face')
  } else if (authStore.user?.userable_type === 'Producer') {
    router.push('/dashboard/producer')
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


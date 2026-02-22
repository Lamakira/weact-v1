<script setup lang="ts">
import { ref } from 'vue'
import { usePasswordReset } from '@/features/auth/composables/usePasswordReset'
import logoNoir from '@/assets/images/logonoir.png'

const email = ref('')
const { isLoading, error, successMessage, forgotPassword, clearState } = usePasswordReset()

async function handleSubmit(): Promise<void> {
  await forgotPassword(email.value)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="flex justify-center">
        <img :src="logoNoir" alt="WEACT" class="h-12 w-auto" />
      </div>

      <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
        Mot de passe oublié
      </h2>
      <p class="mt-2 text-center text-sm text-gray-600">
        Entrez votre email pour recevoir un lien de réinitialisation
      </p>
    </div>

    <div class="mt-8 w-full max-w-md">
      <div class="bg-white py-8 px-6 shadow rounded-lg sm:px-10">
        <!-- Success Message -->
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
          <p class="mt-2 text-sm text-green-700">
            Vérifiez votre boîte de réception et suivez les instructions pour réinitialiser votre mot de passe.
          </p>
        </div>

        <!-- Error Message -->
        <div
          v-if="error"
          class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg"
          data-testid="error-message"
        >
          <div class="flex items-center">
            <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clip-rule="evenodd"
              />
            </svg>
            <span class="text-sm font-medium text-red-800">{{ error }}</span>
          </div>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6" v-if="!successMessage">
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">
              Adresse email
            </label>
            <div class="mt-1">
              <input
                id="email"
                v-model="email"
                type="email"
                autocomplete="email"
                required
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                placeholder="votre@email.com"
                data-testid="email-input"
              />
            </div>
          </div>

          <!-- Submit Button -->
          <div>
            <button
              type="submit"
              :disabled="isLoading"
              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              data-testid="submit-button"
            >
              <svg
                v-if="isLoading"
                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
              </svg>
              {{ isLoading ? 'Envoi en cours...' : 'Envoyer le lien' }}
            </button>
          </div>
        </form>

        <!-- Back to Login -->
        <div class="mt-6 text-center">
          <router-link
            to="/login"
            class="text-sm font-medium text-primary hover:text-primary-dark"
          >
            Retour à la connexion
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
.hover\:bg-primary-dark:hover {
  background-color: #156b7a;
}
.text-primary {
  color: #198496;
}
.hover\:text-primary-dark:hover {
  color: #156b7a;
}
.focus\:ring-primary:focus {
  --tw-ring-color: #198496;
}
.focus\:border-primary:focus {
  border-color: #198496;
}
</style>

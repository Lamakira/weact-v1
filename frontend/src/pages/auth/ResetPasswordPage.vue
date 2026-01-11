<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePasswordReset } from '@/features/auth/composables/usePasswordReset'

const route = useRoute()
const { isLoading, error, resetPassword } = usePasswordReset()

const password = ref('')
const passwordConfirmation = ref('')

// Extract token from route params and email from query string
const token = computed(() => route.params.token as string)
const email = computed(() => (route.query.email as string) || '')

// Password validation state
const passwordTouched = ref(false)
const hasMinLength = computed(() => password.value.length >= 8)
const hasUppercase = computed(() => /[A-Z]/.test(password.value))
const hasNumber = computed(() => /[0-9]/.test(password.value))
const passwordsMatch = computed(
  () => password.value === passwordConfirmation.value && password.value !== ''
)
const isPasswordValid = computed(
  () => hasMinLength.value && hasUppercase.value && hasNumber.value && passwordsMatch.value
)

async function handleSubmit(): Promise<void> {
  if (!isPasswordValid.value) return

  await resetPassword({
    token: token.value,
    email: email.value,
    password: password.value,
    password_confirmation: passwordConfirmation.value,
  })
}

function onPasswordFocus(): void {
  passwordTouched.value = true
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
        Réinitialiser le mot de passe
      </h2>
      <p class="mt-2 text-center text-sm text-gray-600">
        Choisissez un nouveau mot de passe sécurisé
      </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
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

        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Email (read-only, prefilled from URL) -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">
              Adresse email
            </label>
            <div class="mt-1">
              <input
                id="email"
                :value="email"
                type="email"
                readonly
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm bg-gray-100 text-gray-500 sm:text-sm"
                data-testid="email-input"
              />
            </div>
          </div>

          <!-- New Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">
              Nouveau mot de passe
            </label>
            <div class="mt-1">
              <input
                id="password"
                v-model="password"
                type="password"
                autocomplete="new-password"
                required
                @focus="onPasswordFocus"
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                placeholder="••••••••"
                data-testid="password-input"
              />
            </div>

            <!-- Password Requirements -->
            <div v-if="passwordTouched" class="mt-2 space-y-1" data-testid="password-requirements">
              <div class="flex items-center text-xs" :class="hasMinLength ? 'text-green-600' : 'text-gray-500'">
                <svg v-if="hasMinLength" class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                </svg>
                Au moins 8 caractères
              </div>
              <div class="flex items-center text-xs" :class="hasUppercase ? 'text-green-600' : 'text-gray-500'">
                <svg v-if="hasUppercase" class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                </svg>
                Au moins 1 lettre majuscule
              </div>
              <div class="flex items-center text-xs" :class="hasNumber ? 'text-green-600' : 'text-gray-500'">
                <svg v-if="hasNumber" class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                </svg>
                Au moins 1 chiffre
              </div>
            </div>
          </div>

          <!-- Confirm Password -->
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
              Confirmer le mot de passe
            </label>
            <div class="mt-1">
              <input
                id="password_confirmation"
                v-model="passwordConfirmation"
                type="password"
                autocomplete="new-password"
                required
                class="appearance-none block w-full px-3 py-2 border rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                :class="passwordConfirmation && !passwordsMatch ? 'border-red-300' : 'border-gray-300'"
                placeholder="••••••••"
                data-testid="password-confirmation-input"
              />
            </div>
            <p v-if="passwordConfirmation && !passwordsMatch" class="mt-1 text-xs text-red-600">
              Les mots de passe ne correspondent pas
            </p>
          </div>

          <!-- Submit Button -->
          <div>
            <button
              type="submit"
              :disabled="isLoading || !isPasswordValid"
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
              {{ isLoading ? 'Réinitialisation...' : 'Réinitialiser le mot de passe' }}
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

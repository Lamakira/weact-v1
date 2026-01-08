<script setup lang="ts">
import { ref } from 'vue'
import { useForm, useField } from 'vee-validate'
import { faceRegistrationValidationSchema } from '../schemas/faceRegistration'
import { useAuth } from '../composables/useAuth'
import type { FaceRegistrationForm as FormData } from '../types'

const emit = defineEmits<{
  success: []
}>()

const { registerFace, isLoading } = useAuth()

// API error message (general)
const apiError = ref<string | null>(null)

// Form setup with VeeValidate
const { handleSubmit, setFieldError } = useForm<FormData>({
  validationSchema: faceRegistrationValidationSchema,
  initialValues: {
    nom: '',
    prenom: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
  },
})

// Form fields
const { value: nom, errorMessage: nomError } = useField<string>('nom')
const { value: prenom, errorMessage: prenomError } = useField<string>('prenom')
const { value: username, errorMessage: usernameError } = useField<string>('username')
const { value: email, errorMessage: emailError } = useField<string>('email')
const { value: password, errorMessage: passwordError } = useField<string>('password')
const { value: password_confirmation, errorMessage: passwordConfirmationError } =
  useField<string>('password_confirmation')

// Submit handler
const onSubmit = handleSubmit(async (values) => {
  apiError.value = null

  const result = await registerFace(values)

  if (result.success) {
    emit('success')
  } else {
    // Set field-specific errors from API
    if (result.errors) {
      Object.entries(result.errors).forEach(([field, messages]) => {
        if (messages && messages.length > 0) {
          setFieldError(field as keyof FormData, messages[0])
        }
      })
    }

    // Set general error if no field-specific errors
    if (!result.errors || Object.keys(result.errors).length === 0) {
      apiError.value = result.message ?? 'Une erreur est survenue'
    }
  }
})
</script>

<template>
  <form @submit="onSubmit" class="space-y-6" data-testid="face-registration-form">
    <!-- General API error -->
    <div
      v-if="apiError"
      class="rounded-md bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="api-error"
    >
      <p class="text-sm text-red-700">{{ apiError }}</p>
    </div>

    <!-- Nom -->
    <div>
      <label for="nom" class="block text-sm font-medium text-gray-700">Nom</label>
      <input
        id="nom"
        v-model="nom"
        type="text"
        autocomplete="family-name"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm sm:text-sm',
          nomError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-primary focus:ring-primary',
        ]"
        data-testid="nom-input"
      />
      <p v-if="nomError" class="mt-1 text-sm text-red-600" data-testid="nom-error">
        {{ nomError }}
      </p>
    </div>

    <!-- Prénom -->
    <div>
      <label for="prenom" class="block text-sm font-medium text-gray-700">Prénom</label>
      <input
        id="prenom"
        v-model="prenom"
        type="text"
        autocomplete="given-name"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm sm:text-sm',
          prenomError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-primary focus:ring-primary',
        ]"
        data-testid="prenom-input"
      />
      <p v-if="prenomError" class="mt-1 text-sm text-red-600" data-testid="prenom-error">
        {{ prenomError }}
      </p>
    </div>

    <!-- Username -->
    <div>
      <label for="username" class="block text-sm font-medium text-gray-700"
        >Nom d'utilisateur</label
      >
      <input
        id="username"
        v-model="username"
        type="text"
        autocomplete="username"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm sm:text-sm',
          usernameError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-primary focus:ring-primary',
        ]"
        data-testid="username-input"
      />
      <p v-if="usernameError" class="mt-1 text-sm text-red-600" data-testid="username-error">
        {{ usernameError }}
      </p>
    </div>

    <!-- Email -->
    <div>
      <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
      <input
        id="email"
        v-model="email"
        type="email"
        autocomplete="email"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm sm:text-sm',
          emailError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-primary focus:ring-primary',
        ]"
        data-testid="email-input"
      />
      <p v-if="emailError" class="mt-1 text-sm text-red-600" data-testid="email-error">
        {{ emailError }}
      </p>
    </div>

    <!-- Password -->
    <div>
      <label for="password" class="block text-sm font-medium text-gray-700">Mot de passe</label>
      <input
        id="password"
        v-model="password"
        type="password"
        autocomplete="new-password"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm sm:text-sm',
          passwordError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-primary focus:ring-primary',
        ]"
        data-testid="password-input"
      />
      <p v-if="passwordError" class="mt-1 text-sm text-red-600" data-testid="password-error">
        {{ passwordError }}
      </p>
      <p class="mt-1 text-xs text-gray-500">
        8 caractères minimum, une majuscule et un chiffre requis
      </p>
    </div>

    <!-- Password Confirmation -->
    <div>
      <label for="password_confirmation" class="block text-sm font-medium text-gray-700"
        >Confirmer le mot de passe</label
      >
      <input
        id="password_confirmation"
        v-model="password_confirmation"
        type="password"
        autocomplete="new-password"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm sm:text-sm',
          passwordConfirmationError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-primary focus:ring-primary',
        ]"
        data-testid="password-confirmation-input"
      />
      <p
        v-if="passwordConfirmationError"
        class="mt-1 text-sm text-red-600"
        data-testid="password-confirmation-error"
      >
        {{ passwordConfirmationError }}
      </p>
    </div>

    <!-- Submit Button -->
    <div>
      <button
        type="submit"
        :disabled="isLoading"
        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
        data-testid="submit-button"
      >
        <span v-if="isLoading" class="flex items-center">
          <svg
            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
            xmlns="http://www.w3.org/2000/svg"
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
            ></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
          Inscription en cours...
        </span>
        <span v-else>S'inscrire en tant que Face</span>
      </button>
    </div>
  </form>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.bg-primary-dark {
  background-color: #156b7a;
}
.focus\:border-primary:focus {
  border-color: #198496;
}
.focus\:ring-primary:focus {
  --tw-ring-color: #198496;
}
</style>

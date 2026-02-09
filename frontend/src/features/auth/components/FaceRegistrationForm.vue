<script setup lang="ts">
import { ref } from 'vue'
import { useForm, useField } from 'vee-validate'
import { faceRegistrationValidationSchema } from '../schemas/faceRegistration'
import { useAuth } from '../composables/useAuth'
import type { FaceRegistrationForm as FormData } from '../types'
import { FloatingField } from '@/components/ui/form'
import { User, AtSign, Mail, Lock } from 'lucide-vue-next'

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
      class="rounded-lg bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="api-error"
    >
      <p class="text-sm text-red-700">{{ apiError }}</p>
    </div>

    <!-- Nom + Prénom (same row) -->
    <div class="grid grid-cols-2 gap-4">
      <FloatingField
        id="nom"
        v-model="nom"
        label="Nom"
        :icon="User"
        :error="nomError"
        required
        autocomplete="family-name"
        data-testid="nom-input"
      />
      <FloatingField
        id="prenom"
        v-model="prenom"
        label="Prénom"
        :icon="User"
        :error="prenomError"
        required
        autocomplete="given-name"
        data-testid="prenom-input"
      />
    </div>

    <!-- Username -->
    <FloatingField
      id="username"
      v-model="username"
      label="Nom d'utilisateur"
      :icon="AtSign"
      :error="usernameError"
      required
      autocomplete="username"
      data-testid="username-input"
    />

    <!-- Email -->
    <FloatingField
      id="email"
      v-model="email"
      type="email"
      label="Email"
      :icon="Mail"
      :error="emailError"
      required
      autocomplete="email"
      data-testid="email-input"
    />

    <!-- Password -->
    <div>
      <FloatingField
        id="password"
        v-model="password"
        type="password"
        label="Mot de passe"
        :icon="Lock"
        :error="passwordError"
        required
        autocomplete="new-password"
        password-toggle
        data-testid="password-input"
      />
      <p class="mt-1 text-xs text-gray-500">
        Min. 8 car., 1 majuscule, 1 chiffre
      </p>
    </div>

    <!-- Password Confirmation -->
    <FloatingField
      id="password_confirmation"
      v-model="password_confirmation"
      type="password"
      label="Confirmation"
      :icon="Lock"
      :error="passwordConfirmationError"
      required
      autocomplete="new-password"
      password-toggle
      data-testid="password-confirmation-input"
    />

    <!-- Submit Button -->
    <button
      type="submit"
      :disabled="isLoading"
      class="w-full py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      data-testid="submit-button"
    >
      <span v-if="isLoading" class="flex items-center justify-center gap-2">
        <svg
          class="animate-spin h-5 w-5 text-white"
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
  </form>
</template>

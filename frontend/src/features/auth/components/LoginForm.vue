<script setup lang="ts">
import { ref } from 'vue'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { loginSchema } from '../schemas/login'
import { useAuth } from '../composables/useAuth'
import { FloatingField } from '@/components/ui/form'
import { Mail, Lock } from 'lucide-vue-next'

const emit = defineEmits<{
  success: []
}>()

const { login, isLoading } = useAuth()

// API error message (general)
const apiError = ref<string | null>(null)

// Form setup with VeeValidate
const { handleSubmit, setFieldError } = useForm({
  validationSchema: toTypedSchema(loginSchema),
  initialValues: {
    email: '',
    password: '',
  },
})

// Form fields
const { value: email, errorMessage: emailError } = useField<string>('email')
const { value: password, errorMessage: passwordError } = useField<string>('password')

// Submit handler
const onSubmit = handleSubmit(async (values) => {
  apiError.value = null

  const result = await login({
    email: values.email,
    password: values.password,
  })

  if (result.success) {
    emit('success')
  } else {
    // Set field-specific errors from API
    if (result.errors) {
      const validFields = ['email', 'password'] as const
      type ValidField = (typeof validFields)[number]

      Object.entries(result.errors).forEach(([field, messages]) => {
        if (messages && messages.length > 0 && validFields.includes(field as ValidField)) {
          setFieldError(field as ValidField, messages[0])
        }
      })
    }

    // Set general error message
    apiError.value = result.message ?? 'Une erreur est survenue'
  }
})
</script>

<template>
  <form @submit="onSubmit" class="space-y-6" data-testid="login-form">
    <!-- General API error -->
    <div
      v-if="apiError"
      class="rounded-lg bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="api-error"
    >
      <p class="text-sm text-red-700">{{ apiError }}</p>
    </div>

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

    <!-- Password with toggle -->
    <FloatingField
      id="password"
      v-model="password"
      type="password"
      label="Mot de passe"
      :icon="Lock"
      :error="passwordError"
      required
      autocomplete="current-password"
      password-toggle
      data-testid="password-input"
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
        Connexion en cours...
      </span>
      <span v-else>Se connecter</span>
    </button>
  </form>
</template>

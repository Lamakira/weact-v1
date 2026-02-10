<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Mail, Lock, Shield } from 'lucide-vue-next'
import { useAdminAuth } from '@/features/admin/composables/useAdminAuth'
import { FloatingField } from '@/components/ui/form'
import logoNoir from '@/assets/images/logonoir.png'

const router = useRouter()
const route = useRoute()
const { login, isLoading } = useAdminAuth()

// Warning message for session expired redirect
const warningMessage = ref<string | null>(null)

// API error message
const apiError = ref<string | null>(null)

// Validation schema (same as user login)
const adminLoginSchema = z.object({
  email: z
    .string({ message: "L'email est obligatoire" })
    .min(1, "L'email est obligatoire")
    .email("L'email doit être une adresse email valide"),
  password: z
    .string({ message: 'Le mot de passe est obligatoire' })
    .min(1, 'Le mot de passe est obligatoire'),
})

// Form setup with VeeValidate
const { handleSubmit, setFieldError } = useForm({
  validationSchema: toTypedSchema(adminLoginSchema),
  initialValues: {
    email: '',
    password: '',
  },
})

// Form fields
const { value: email, errorMessage: emailError } = useField<string>('email')
const { value: password, errorMessage: passwordError } = useField<string>('password')

onMounted(() => {
  const message = route.query.message
  if (message === 'session-expired') {
    warningMessage.value = 'Session expirée, veuillez vous reconnecter.'
    router.replace({
      path: '/admin/login',
      query: route.query.redirect ? { redirect: route.query.redirect } : {},
    })
  }
})

// Submit handler
const onSubmit = handleSubmit(async (values) => {
  apiError.value = null

  const result = await login({
    email: values.email,
    password: values.password,
  })

  if (result.success) {
    // Check for redirect query param
    const redirectPath = route.query.redirect as string
    if (redirectPath) {
      router.push(redirectPath)
    } else {
      router.push({ name: 'admin-dashboard' })
    }
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

    apiError.value = result.message ?? 'Une erreur est survenue'
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12">
    <div class="w-full max-w-md">
      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
        <!-- Logo -->
        <div class="flex justify-center mb-6">
          <img :src="logoNoir" alt="WEACT" class="h-10" />
        </div>

        <!-- Heading -->
        <div class="text-center mb-8">
          <h1 class="text-2xl font-bold text-gray-900 flex items-center justify-center gap-2">
            <Shield class="w-6 h-6 text-primary-600" />
            Administration
          </h1>
          <p class="mt-1 text-sm text-gray-500">Connectez-vous à l'espace d'administration</p>
        </div>

        <!-- Warning Message (session expired) -->
        <div
          v-if="warningMessage"
          class="mb-6 p-3 bg-amber-50 border border-amber-200 rounded-lg"
          data-testid="warning-message"
        >
          <p class="text-sm text-amber-700">{{ warningMessage }}</p>
        </div>

        <!-- Login Form -->
        <form @submit="onSubmit" class="space-y-5" data-testid="admin-login-form">
          <!-- General API error -->
          <div
            v-if="apiError"
            class="rounded-lg bg-red-50 p-3 border border-red-200"
            role="alert"
            data-testid="api-error"
          >
            <p class="text-sm text-red-700">{{ apiError }}</p>
          </div>

          <!-- Email -->
          <FloatingField
            id="admin-email"
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
            id="admin-password"
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
      </div>

      <!-- Footer -->
      <p class="mt-6 text-center text-xs text-gray-400">
        WEACT Administration &middot; Accès restreint
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { User, Mail, Lock, ShieldPlus } from 'lucide-vue-next'
import { useAdmins } from '@/features/admin/composables/useAdmins'
import { useToast } from '@/composables/useToast'
import { FloatingField } from '@/components/ui/form'

const router = useRouter()
const toast = useToast()
const { createAdmin, isLoading } = useAdmins()

// API error message
const apiError = ref<string | null>(null)

// Validation schema
const createAdminSchema = z
  .object({
    name: z
      .string({ message: 'Le nom est obligatoire' })
      .min(1, 'Le nom est obligatoire'),
    email: z
      .string({ message: "L'email est obligatoire" })
      .min(1, "L'email est obligatoire")
      .email("L'email doit être une adresse email valide"),
    password: z
      .string({ message: 'Le mot de passe est obligatoire' })
      .min(8, 'Le mot de passe doit contenir au moins 8 caractères'),
    password_confirmation: z
      .string({ message: 'La confirmation est obligatoire' })
      .min(1, 'La confirmation est obligatoire'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'La confirmation du mot de passe ne correspond pas',
    path: ['password_confirmation'],
  })

// Form setup
const { handleSubmit, setFieldError } = useForm({
  validationSchema: toTypedSchema(createAdminSchema),
  initialValues: {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  },
})

// Form fields
const { value: name, errorMessage: nameError } = useField<string>('name')
const { value: email, errorMessage: emailError } = useField<string>('email')
const { value: password, errorMessage: passwordError } = useField<string>('password')
const { value: passwordConfirmation, errorMessage: passwordConfirmationError } =
  useField<string>('password_confirmation')

// Submit handler
const onSubmit = handleSubmit(async (values) => {
  apiError.value = null

  const result = await createAdmin({
    name: values.name,
    email: values.email,
    password: values.password,
    password_confirmation: values.password_confirmation,
  })

  if (result.success) {
    toast.success(result.message ?? 'Administrateur créé avec succès')
    router.push({ name: 'admin-admins-list' })
  } else {
    // Set field-specific errors from API
    if (result.errors) {
      const validFields = ['name', 'email', 'password', 'password_confirmation'] as const
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
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <ShieldPlus class="h-6 w-6 text-primary-600" />
        Créer un administrateur
      </h1>
      <p class="mt-1 text-sm text-gray-500">
        Ajoutez un nouveau compte administrateur à la plateforme
      </p>
    </div>

    <!-- Form Card -->
    <div class="max-w-lg">
      <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
        <form @submit="onSubmit" class="space-y-5" data-testid="create-admin-form">
          <!-- General API error -->
          <div
            v-if="apiError"
            class="rounded-lg bg-red-50 p-3 border border-red-200"
            role="alert"
            data-testid="api-error"
          >
            <p class="text-sm text-red-700">{{ apiError }}</p>
          </div>

          <!-- Name -->
          <FloatingField
            id="admin-name"
            v-model="name"
            type="text"
            label="Nom"
            :icon="User"
            :error="nameError"
            required
            autocomplete="name"
            data-testid="name-input"
          />

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

          <!-- Password -->
          <FloatingField
            id="admin-password"
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

          <!-- Password Confirmation -->
          <FloatingField
            id="admin-password-confirmation"
            v-model="passwordConfirmation"
            type="password"
            label="Confirmer le mot de passe"
            :icon="Lock"
            :error="passwordConfirmationError"
            required
            autocomplete="new-password"
            password-toggle
            data-testid="password-confirmation-input"
          />

          <!-- Actions -->
          <div class="flex items-center gap-3 pt-2">
            <button
              type="submit"
              :disabled="isLoading"
              class="flex-1 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
                Création en cours...
              </span>
              <span v-else>Créer l'administrateur</span>
            </button>
            <router-link
              to="/admin/admins"
              class="px-6 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              data-testid="cancel-button"
            >
              Annuler
            </router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

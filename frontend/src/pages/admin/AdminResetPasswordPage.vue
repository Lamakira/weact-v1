<script setup lang="ts">
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Lock, Shield } from 'lucide-vue-next'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useAdminPasswordReset } from '@/features/admin/composables/useAdminPasswordReset'
import { FloatingField } from '@/components/ui/form'
import { useToast } from '@/composables/useToast'
import logoNoir from '@/assets/images/logonoir.png'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { resetPassword, isLoading } = useAdminPasswordReset()

const token = route.params.token as string
const email = (route.query.email as string) ?? ''

const resetSchema = z
  .object({
    password: z
      .string({ message: 'Le mot de passe est obligatoire' })
      .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
      .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
      .regex(/[0-9]/, 'Le mot de passe doit contenir au moins un chiffre'),
    password_confirmation: z
      .string({ message: 'La confirmation est obligatoire' })
      .min(1, 'La confirmation est obligatoire'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Les mots de passe ne correspondent pas',
    path: ['password_confirmation'],
  })

const { handleSubmit } = useForm({
  validationSchema: toTypedSchema(resetSchema),
  initialValues: { password: '', password_confirmation: '' },
})

const { value: password, errorMessage: passwordError } = useField<string>('password')
const { value: passwordConfirmation, errorMessage: passwordConfirmationError } =
  useField<string>('password_confirmation')

const onSubmit = handleSubmit(async (values) => {
  const result = await resetPassword({
    token,
    email,
    password: values.password,
    password_confirmation: values.password_confirmation,
  })

  if (result.success) {
    toast.success('Mot de passe mis à jour avec succès')
    router.push({ name: 'admin-login' })
  } else {
    toast.error(result.message ?? 'Lien expiré ou invalide')
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
            Nouveau mot de passe
          </h1>
          <p class="mt-1 text-sm text-gray-500">Choisissez un nouveau mot de passe sécurisé</p>
        </div>

        <!-- Form -->
        <form @submit="onSubmit" class="space-y-5" data-testid="reset-password-form">
          <FloatingField
            id="reset-password"
            v-model="password"
            type="password"
            label="Nouveau mot de passe"
            :icon="Lock"
            :error="passwordError"
            required
            autocomplete="new-password"
            password-toggle
            data-testid="password-input"
          />

          <FloatingField
            id="reset-password-confirmation"
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
              Réinitialisation...
            </span>
            <span v-else>Réinitialiser le mot de passe</span>
          </button>
        </form>

        <!-- Links -->
        <div class="mt-6 text-center space-y-2">
          <RouterLink
            to="/admin/forgot-password"
            class="block text-sm text-primary-600 hover:text-primary-700 transition-colors"
            data-testid="back-to-forgot"
          >
            Renvoyer un lien
          </RouterLink>
          <RouterLink
            to="/admin/login"
            class="block text-sm text-gray-500 hover:text-gray-700 transition-colors"
            data-testid="back-to-login"
          >
            Retour à la connexion
          </RouterLink>
        </div>
      </div>

      <!-- Footer -->
      <p class="mt-6 text-center text-xs text-gray-400">
        WEACT Administration &middot; Accès restreint
      </p>
    </div>
  </div>
</template>

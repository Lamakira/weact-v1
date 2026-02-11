<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { Mail, Shield, ArrowLeft } from 'lucide-vue-next'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useAdminPasswordReset } from '@/features/admin/composables/useAdminPasswordReset'
import { FloatingField } from '@/components/ui/form'
import { useToast } from '@/composables/useToast'
import logoNoir from '@/assets/images/logonoir.png'

const toast = useToast()
const { forgotPassword, isLoading } = useAdminPasswordReset()

const forgotSchema = z.object({
  email: z
    .string({ message: "L'email est obligatoire" })
    .min(1, "L'email est obligatoire")
    .email("L'email doit être une adresse email valide"),
})

const { handleSubmit, resetForm } = useForm({
  validationSchema: toTypedSchema(forgotSchema),
  initialValues: { email: '' },
})

const { value: email, errorMessage: emailError } = useField<string>('email')

const onSubmit = handleSubmit(async (values) => {
  const result = await forgotPassword(values.email)

  if (result.success) {
    toast.success('Email de réinitialisation envoyé')
    resetForm()
  } else {
    toast.error(result.message ?? 'Une erreur est survenue')
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
            Mot de passe oublié
          </h1>
          <p class="mt-1 text-sm text-gray-500">
            Entrez votre email pour recevoir un lien de réinitialisation
          </p>
        </div>

        <!-- Form -->
        <form @submit="onSubmit" class="space-y-5" data-testid="forgot-password-form">
          <FloatingField
            id="forgot-email"
            v-model="email"
            type="email"
            label="Email"
            :icon="Mail"
            :error="emailError"
            required
            autocomplete="email"
            data-testid="email-input"
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
              Envoi en cours...
            </span>
            <span v-else>Envoyer le lien</span>
          </button>
        </form>

        <!-- Back to login -->
        <div class="mt-6 text-center">
          <RouterLink
            to="/admin/login"
            class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 transition-colors"
            data-testid="back-to-login"
          >
            <ArrowLeft class="w-4 h-4" />
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

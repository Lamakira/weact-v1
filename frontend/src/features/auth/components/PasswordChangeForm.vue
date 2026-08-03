<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm, useField } from 'vee-validate'
import { Lock, Loader2 } from 'lucide-vue-next'
import { FloatingField } from '@/components/ui/form'
import { passwordChangeValidationSchema } from '../schemas/passwordChange'
import { usePasswordChange } from '../composables/usePasswordChange'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'

const {
  isLoading,
  error,
  fieldErrors,
  changePassword,
  clearError,
} = usePasswordChange()

const toast = useToast()
const showForm = ref(false)

// An account created through Google has no password: the same form becomes
// "set a password", which is also what unblocks email change and account deletion.
const authStore = useAuthStore()
const hasPassword = computed(() => authStore.hasPassword)

const validationSchema = computed(() => passwordChangeValidationSchema(hasPassword.value))

const { handleSubmit, resetForm, setFieldError } = useForm({
  validationSchema,
  initialValues: {
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  },
})

const { value: currentPassword, errorMessage: currentPasswordError } =
  useField<string>('current_password')
const { value: newPassword, errorMessage: newPasswordError } =
  useField<string>('new_password')
const { value: newPasswordConfirmation, errorMessage: newPasswordConfirmationError } =
  useField<string>('new_password_confirmation')

const onSubmit = handleSubmit(async (values) => {
  clearError()
  const success = await changePassword(
    // Absent on an OAuth-only account: the endpoint accepts an empty value there.
    values.current_password ?? '',
    values.new_password,
    values.new_password_confirmation,
  )

  if (success) {
    toast.success(
      hasPassword.value
        ? 'Votre mot de passe a été modifié avec succès.'
        : 'Votre mot de passe a été défini avec succès.'
    )
    resetForm()
    showForm.value = false
    // Flip has_password so the email-change and delete-account surfaces unlock
    // without a reload.
    await authStore.refreshUser()
  } else if (fieldErrors.value) {
    Object.entries(fieldErrors.value).forEach(([field, messages]) => {
      if (messages && messages.length > 0) {
        setFieldError(field as 'current_password' | 'new_password' | 'new_password_confirmation', messages[0])
      }
    })
  }
})

function handleCancel(): void {
  showForm.value = false
  clearError()
  resetForm()
}
</script>

<template>
  <div data-testid="password-change-section">
    <h3 class="text-sm font-semibold text-slate-800 mb-2">
      {{ hasPassword ? 'Changer de mot de passe' : 'Définir un mot de passe' }}
    </h3>

    <p v-if="!hasPassword" class="text-xs text-gray-500 mb-2" data-testid="set-password-hint">
      Vous vous connectez avec Google. Définir un mot de passe vous permet aussi de vous connecter
      sans Google, de changer votre adresse email et de supprimer votre compte.
    </p>

    <!-- Toggle form button -->
    <button
      v-if="!showForm"
      @click="showForm = true"
      class="text-sm text-primary hover:text-primary/80 font-medium transition-colors"
      data-testid="show-form-button"
    >
      {{ hasPassword ? 'Modifier mon mot de passe' : 'Définir un mot de passe' }}
    </button>

    <!-- Password change form -->
    <form
      v-if="showForm"
      @submit="onSubmit"
      class="space-y-3"
      data-testid="password-change-form"
    >
      <!-- General error message -->
      <div
        v-if="error"
        class="rounded-xl bg-red-50 border border-red-200 p-3"
        data-testid="form-error"
      >
        <p class="text-sm text-red-700">{{ error }}</p>
      </div>

      <!-- Current password field (nothing to confirm on an OAuth-only account) -->
      <FloatingField
        v-if="hasPassword"
        id="current-password-change"
        v-model="currentPassword"
        type="password"
        label="Mot de passe actuel"
        :icon="Lock"
        :error="currentPasswordError"
        required
        autocomplete="current-password"
        password-toggle
        data-testid="current-password-input"
      />

      <!-- New password field -->
      <div>
        <FloatingField
          id="new-password-change"
          v-model="newPassword"
          type="password"
          label="Nouveau mot de passe"
          :icon="Lock"
          :error="newPasswordError"
          required
          autocomplete="new-password"
          password-toggle
          data-testid="new-password-input"
        />
        <p class="mt-1 text-xs text-gray-500">
          Min. 8 car., 1 majuscule, 1 chiffre
        </p>
      </div>

      <!-- Confirm new password field -->
      <FloatingField
        id="confirm-password-change"
        v-model="newPasswordConfirmation"
        type="password"
        label="Confirmer le nouveau mot de passe"
        :icon="Lock"
        :error="newPasswordConfirmationError"
        required
        autocomplete="new-password"
        password-toggle
        data-testid="confirm-password-input"
      />

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <button
          type="submit"
          :disabled="isLoading"
          class="px-4 py-2 text-sm bg-primary text-white font-medium rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          data-testid="submit-button"
        >
          <Loader2 v-if="isLoading" class="w-4 h-4 animate-spin" />
          Confirmer
        </button>
        <button
          type="button"
          @click="handleCancel"
          class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 transition-colors"
          data-testid="cancel-form-button"
        >
          Annuler
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}
</style>

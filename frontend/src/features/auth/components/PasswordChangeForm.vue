<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm, useField } from 'vee-validate'
import { Lock, Loader2 } from 'lucide-vue-next'
import { FloatingField } from '@/components/ui/form'
import { passwordChangeValidationSchema } from '../schemas/passwordChange'
import { usePasswordChange } from '../composables/usePasswordChange'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { takeGoogleReauthTicket } from '../googleReauth'
import GoogleSignInButton from './GoogleSignInButton.vue'

const {
  isLoading,
  error,
  errorCode,
  fieldErrors,
  changePassword,
  setPassword,
  clearError,
} = usePasswordChange()

const toast = useToast()
const showForm = ref(false)

// An account created through Google has no password: the same form becomes
// "set a password", which is also what unblocks email change and account deletion.
const authStore = useAuthStore()
const hasPassword = computed(() => authStore.hasPassword)

// A first password requires a fresh Google re-authentication. The ticket comes
// back from the callback page (purpose-stamped, so a deletion ticket is never
// taken here), lives in memory only, and is dropped as soon as it is spent.
const reauthTicket = ref<string | null>(null)

// Picked up while the component is created (i.e. on return from Google), so the
// form is there on first paint.
if (!hasPassword.value) {
  const ticket = takeGoogleReauthTicket('set_password', authStore.user?.id)

  if (ticket !== null) {
    reauthTicket.value = ticket
    showForm.value = true
  }
}

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

  // Password-less account: the ticket replaces the current password, which is
  // not sent at all.
  const success = hasPassword.value
    ? await changePassword(
        values.current_password ?? '',
        values.new_password,
        values.new_password_confirmation,
      )
    : await setPassword(
        values.new_password,
        values.new_password_confirmation,
        reauthTicket.value ?? '',
      )

  if (success) {
    toast.success(
      hasPassword.value
        ? 'Votre mot de passe a été modifié avec succès.'
        : 'Votre mot de passe a été défini avec succès.'
    )
    resetForm()
    // Flip has_password so the email-change and delete-account surfaces unlock
    // without a reload. The form stays up until then: closing it first would flash
    // the Google button on a password-less account.
    await authStore.refreshUser()
    showForm.value = false
    reauthTicket.value = null
  } else if (!hasPassword.value && !isFieldValidationFailure()) {
    // Spent/expired ticket, or a server error after which the password may or may
    // not have been written: drop the ticket, re-read the account (a password now
    // present flips the form to "change password") and fall back to the Google
    // confirmation, with the error message still visible.
    dropTicket()
    await authStore.refreshUser()
  } else if (fieldErrors.value) {
    Object.entries(fieldErrors.value).forEach(([field, messages]) => {
      if (messages && messages.length > 0) {
        setFieldError(field as 'current_password' | 'new_password' | 'new_password_confirmation', messages[0])
      }
    })
  }
})

// A 422 on a field other than the ticket is rejected before anything is consumed
// or written: the ticket is still good, so the form stays up on the offending field.
function isFieldValidationFailure(): boolean {
  if (errorCode.value === 'REAUTH_TOKEN_INVALID') return false

  const fields = Object.keys(fieldErrors.value ?? {})

  return fields.length > 0 && !fields.includes('reauth_token')
}

function dropTicket(): void {
  reauthTicket.value = null
  showForm.value = false
  resetForm()
}

function handleCancel(): void {
  showForm.value = false
  clearError()
  resetForm()
  // The ticket is single-use: cancelling spends it.
  reauthTicket.value = null
}
</script>

<template>
  <div data-testid="password-change-section">
    <h3 class="text-sm font-semibold text-slate-800 mb-2">
      {{ hasPassword ? 'Changer de mot de passe' : 'Définir un mot de passe' }}
    </h3>

    <p v-if="!hasPassword" class="text-xs text-gray-500 mb-2" data-testid="set-password-hint">
      Vous vous connectez avec Google. Définir un mot de passe vous permet aussi de vous connecter
      sans Google, de changer votre adresse email et de supprimer votre compte. Pour votre
      sécurité, confirmez d'abord votre identité avec Google.
    </p>

    <!-- Password-less account: a first password needs a fresh Google confirmation -->
    <div v-if="!hasPassword && reauthTicket === null" data-testid="set-password-reauth">
      <div
        v-if="error"
        class="rounded-xl bg-red-50 border border-red-200 p-3 mb-3"
        data-testid="form-error"
      >
        <p class="text-sm text-red-700">{{ error }}</p>
      </div>
      <GoogleSignInButton
        intent="reauth"
        reauth-purpose="set_password"
        label="Confirmer avec Google"
      />
    </div>

    <!-- Toggle form button -->
    <button
      v-else-if="!showForm"
      @click="showForm = true"
      class="text-sm text-primary hover:text-primary/80 font-medium transition-colors"
      data-testid="show-form-button"
    >
      {{ hasPassword ? 'Modifier mon mot de passe' : 'Définir un mot de passe' }}
    </button>

    <!-- Password change form -->
    <form
      v-else

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

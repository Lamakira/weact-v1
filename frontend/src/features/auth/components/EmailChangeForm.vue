<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Mail, X, Loader2, CheckCircle } from 'lucide-vue-next'
import { useEmailChange } from '../composables/useEmailChange'
import { useToast } from '@/composables/useToast'

const {
  pendingEmail,
  isLoading,
  isSuccess,
  error,
  fieldErrors,
  requestChange,
  cancelChange,
  fetchStatus,
  clearError,
} = useEmailChange()

const toast = useToast()

const newEmail = ref('')
const password = ref('')
const showForm = ref(false)

onMounted(async () => {
  await fetchStatus()
})

async function handleSubmit(): Promise<void> {
  clearError()
  const success = await requestChange(newEmail.value, password.value)
  if (success) {
    toast.success('Un email de confirmation a été envoyé à votre nouvelle adresse.')
    newEmail.value = ''
    password.value = ''
    showForm.value = false
  }
}

async function handleCancel(): Promise<void> {
  const success = await cancelChange()
  if (success) {
    toast.success('La demande de changement d\'email a été annulée.')
  }
}
</script>

<template>
  <div data-testid="email-change-section">
    <h3 class="text-sm font-semibold text-slate-800 mb-2">Changer d'email</h3>

    <!-- Pending email change notice -->
    <div
      v-if="pendingEmail"
      class="rounded-xl bg-amber-50 border border-amber-200 p-3 mb-3"
      data-testid="pending-email-notice"
    >
      <div class="flex items-start justify-between gap-2">
        <div class="flex items-start gap-2">
          <Mail class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-amber-800 font-medium">Changement en attente</p>
            <p class="text-xs text-amber-700 mt-0.5">
              Un email de confirmation a été envoyé à
              <strong>{{ pendingEmail }}</strong>
            </p>
          </div>
        </div>
        <button
          @click="handleCancel"
          :disabled="isLoading"
          class="text-amber-600 hover:text-amber-800 transition-colors p-1"
          data-testid="cancel-pending-button"
          title="Annuler"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Success message -->
    <div
      v-if="isSuccess && !pendingEmail"
      class="rounded-xl bg-green-50 border border-green-200 p-3 mb-3"
      data-testid="success-message"
    >
      <div class="flex items-center gap-2">
        <CheckCircle class="w-4 h-4 text-green-600" />
        <p class="text-sm text-green-700">Email de confirmation envoyé !</p>
      </div>
    </div>

    <!-- Toggle form button -->
    <button
      v-if="!showForm && !pendingEmail"
      @click="showForm = true"
      class="text-sm text-primary hover:text-primary/80 font-medium transition-colors"
      data-testid="show-form-button"
    >
      Modifier mon adresse email
    </button>

    <!-- Email change form -->
    <form
      v-if="showForm && !pendingEmail"
      @submit.prevent="handleSubmit"
      class="space-y-3"
      data-testid="email-change-form"
    >
      <!-- Error message -->
      <div
        v-if="error && Object.keys(fieldErrors ?? {}).length === 0"
        class="rounded-xl bg-red-50 border border-red-200 p-3"
        data-testid="form-error"
      >
        <p class="text-sm text-red-700">{{ error }}</p>
      </div>

      <!-- New email field -->
      <div>
        <label for="new-email" class="block text-sm font-medium text-slate-700 mb-1">
          Nouvelle adresse email
        </label>
        <input
          id="new-email"
          v-model="newEmail"
          type="email"
          required
          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
          :class="{ 'border-red-300': fieldErrors?.email }"
          placeholder="nouvelle@example.com"
          data-testid="new-email-input"
        />
        <p
          v-if="fieldErrors?.email"
          class="mt-1 text-xs text-red-600"
          data-testid="email-error"
        >
          {{ fieldErrors.email[0] }}
        </p>
      </div>

      <!-- Password field -->
      <div>
        <label for="current-password" class="block text-sm font-medium text-slate-700 mb-1">
          Mot de passe actuel
        </label>
        <input
          id="current-password"
          v-model="password"
          type="password"
          required
          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
          :class="{ 'border-red-300': fieldErrors?.password }"
          placeholder="Votre mot de passe"
          data-testid="password-input"
        />
        <p
          v-if="fieldErrors?.password"
          class="mt-1 text-xs text-red-600"
          data-testid="password-error"
        >
          {{ fieldErrors.password[0] }}
        </p>
      </div>

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
          @click="showForm = false; clearError()"
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

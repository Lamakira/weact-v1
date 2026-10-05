<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm, useField } from 'vee-validate'
import {
  PRODUCER_NAME_MAX_LENGTH,
  producerRegistrationValidationSchema,
} from '../schemas/producerRegistration'
import { useAuth } from '../composables/useAuth'
import type { ProducerRegistrationForm as FormData, ProducerType } from '../types'
import { FloatingField } from '@/components/ui/form'
import GoogleSignInButton from './GoogleSignInButton.vue'
import { Mail, Lock, Building } from 'lucide-vue-next'

// The Google button lives inside the form because it must stay disabled until the
// CGU checkbox is ticked — consent is never collected implicitly.
withDefaults(defineProps<{ googleEnabled?: boolean }>(), { googleEnabled: false })

const emit = defineEmits<{
  success: []
}>()

const { registerProducer, isLoading } = useAuth()

// Selected producer type
const selectedType = ref<ProducerType>('agency')

// API error message (general)
const apiError = ref<string | null>(null)

const validationSchema = computed(() => producerRegistrationValidationSchema(selectedType.value))

// Form setup with VeeValidate
const { handleSubmit, setFieldError, setFieldValue } = useForm({
  validationSchema,
  initialValues: {
    type: selectedType.value,
    nom: '',
    email: '',
    password: '',
    accept_cgu: false,
  },
})

// Form fields
const { value: nom, errorMessage: nomError } = useField<string>('nom')
const { value: email, errorMessage: emailError } = useField<string>('email')
const { value: password, errorMessage: passwordError } = useField<string>('password')
const { value: accept_cgu, errorMessage: acceptCguError } = useField<boolean>('accept_cgu')

// Keep the submitted `type` in sync with the toggle. The name field is shared by
// both branches, so nothing to clear when switching.
watch(selectedType, (newType) => {
  setFieldValue('type', newType)
  setFieldError('type', undefined)
  setFieldError('nom', undefined)
  apiError.value = null
})

// The shared name input maps to a different backend key per type.
const nameFieldKey = computed(() => (selectedType.value === 'agency' ? 'agency_name' : 'nom_complet'))

// Submit handler
const onSubmit = handleSubmit(async () => {
  apiError.value = null

  const submitData: FormData =
    selectedType.value === 'agency'
      ? {
          type: 'agency' as const,
          email: email.value,
          password: password.value,
          agency_name: nom.value,
          accept_cgu: accept_cgu.value,
        }
      : {
          type: 'particulier' as const,
          email: email.value,
          password: password.value,
          nom_complet: nom.value,
          accept_cgu: accept_cgu.value,
        }

  const result = await registerProducer(submitData)

  if (result.success) {
    emit('success')
  } else {
    // Set field-specific errors from API. `agency_name`/`nom_complet` both land on
    // the single `nom` input.
    if (result.errors) {
      const validFields = ['type', 'email', 'password', 'accept_cgu'] as const
      type ValidField = (typeof validFields)[number]

      Object.entries(result.errors).forEach(([field, messages]) => {
        if (!messages || messages.length === 0) return

        if (field === nameFieldKey.value) {
          setFieldError('nom', messages[0])
          return
        }

        if (validFields.includes(field as ValidField)) {
          setFieldError(field as ValidField, messages[0])
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
  <form @submit="onSubmit" class="space-y-6" data-testid="producer-registration-form">
    <!-- General API error -->
    <div
      v-if="apiError"
      class="rounded-lg bg-red-50 p-4 border border-red-200"
      role="alert"
      data-testid="api-error"
    >
      <p class="text-sm text-red-700">{{ apiError }}</p>
    </div>

    <!-- Type Selector -->
    <div class="flex rounded-lg bg-gray-100 p-1" data-testid="type-selector">
      <button
        type="button"
        :class="[
          'flex-1 py-3 rounded-md text-sm font-medium transition-all',
          selectedType === 'agency'
            ? 'bg-primary-500 text-white shadow-sm'
            : 'text-gray-600 hover:text-gray-900',
        ]"
        @click="selectedType = 'agency'"
        data-testid="type-agency-button"
      >
        Agence
      </button>
      <button
        type="button"
        :class="[
          'flex-1 py-3 rounded-md text-sm font-medium transition-all',
          selectedType === 'particulier'
            ? 'bg-primary-500 text-white shadow-sm'
            : 'text-gray-600 hover:text-gray-900',
        ]"
        @click="selectedType = 'particulier'"
        data-testid="type-particulier-button"
      >
        Particulier
      </button>
    </div>

    <!-- Divider -->
    <div class="relative">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-200" />
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="bg-white px-4 text-gray-500">Informations</span>
      </div>
    </div>

    <!-- Name: one field for both account types -->
    <FloatingField
      id="nom"
      v-model="nom"
      label="Nom ou raison sociale"
      :icon="Building"
      :error="nomError"
      required
      :maxlength="PRODUCER_NAME_MAX_LENGTH"
      autocomplete="organization"
      data-testid="nom-input"
    />

    <!-- Email -->
    <FloatingField
      id="email"
      v-model="email"
      type="email"
      label="Email professionnel"
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

    <!-- CGU Consent Checkbox -->
    <div class="space-y-1" data-testid="accept-cgu-field">
      <label class="flex items-start gap-2.5 cursor-pointer">
        <input
          v-model="accept_cgu"
          type="checkbox"
          class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500 cursor-pointer"
          data-testid="accept-cgu-checkbox"
        />
        <span class="text-xs text-gray-600 leading-relaxed">
          J'accepte les
          <router-link to="/cgu" target="_blank" class="text-primary-500 hover:underline font-medium">Conditions Générales d'Utilisation</router-link>
          et la
          <router-link to="/politique-confidentialite" target="_blank" class="text-primary-500 hover:underline font-medium">Politique de Confidentialité</router-link>
          de WEACT.
        </span>
      </label>
      <p v-if="acceptCguError" class="text-xs text-red-500 ml-6" data-testid="accept-cgu-error">{{ acceptCguError }}</p>
    </div>

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
        Création en cours...
      </span>
      <span v-else>Créer mon compte producteur</span>
    </button>

    <!-- Google Sign-In: same consent gate as the form itself -->
    <template v-if="googleEnabled">
      <div class="relative">
        <div class="absolute inset-0 flex items-center">
          <div class="w-full border-t border-gray-200" />
        </div>
        <div class="relative flex justify-center text-sm">
          <span class="px-2 bg-white text-gray-500">ou</span>
        </div>
      </div>
      <GoogleSignInButton
        intent="producer"
        :disabled="!accept_cgu"
        label="S'inscrire avec Google"
      />
      <p v-if="!accept_cgu" class="text-xs text-gray-500 text-center" data-testid="google-cgu-hint">
        Acceptez les CGU ci-dessus pour continuer avec Google.
      </p>
    </template>
  </form>
</template>

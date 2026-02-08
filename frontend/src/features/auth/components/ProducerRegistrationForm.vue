<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm, useField } from 'vee-validate'
import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'
import { useAuth } from '../composables/useAuth'
import type { ProducerRegistrationForm as FormData, ProducerType } from '../types'

const emit = defineEmits<{
  success: []
}>()

const { registerProducer, isLoading } = useAuth()

// Selected producer type
const selectedType = ref<ProducerType>('agency')

// Password visibility toggles
const showPassword = ref(false)
const showPasswordConfirm = ref(false)

// API error message (general)
const apiError = ref<string | null>(null)

// Dynamic schema based on selected type
const getSchema = (type: ProducerType) => {
  const baseSchema = {
    type: z.literal(type),
    email: z
      .string({ message: "L'email est obligatoire" })
      .min(1, "L'email est obligatoire")
      .email("L'email doit être une adresse email valide"),
    password: z
      .string({ message: 'Le mot de passe est obligatoire' })
      .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
      .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
      .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),
    password_confirmation: z
      .string({ message: 'La confirmation du mot de passe est obligatoire' })
      .min(1, 'La confirmation du mot de passe est obligatoire'),
  }

  if (type === 'agency') {
    return z
      .object({
        ...baseSchema,
        agency_name: z
          .string({ message: "Le nom de l'agence est obligatoire" })
          .min(1, "Le nom de l'agence est obligatoire")
          .max(255, "Le nom de l'agence ne peut pas dépasser 255 caractères"),
      })
      .refine((data) => data.password === data.password_confirmation, {
        message: 'La confirmation du mot de passe ne correspond pas',
        path: ['password_confirmation'],
      })
  } else {
    return z
      .object({
        ...baseSchema,
        first_name: z
          .string({ message: 'Le prénom est obligatoire' })
          .min(1, 'Le prénom est obligatoire')
          .max(255, 'Le prénom ne peut pas dépasser 255 caractères'),
        last_name: z
          .string({ message: 'Le nom est obligatoire' })
          .min(1, 'Le nom est obligatoire')
          .max(255, 'Le nom ne peut pas dépasser 255 caractères'),
      })
      .refine((data) => data.password === data.password_confirmation, {
        message: 'La confirmation du mot de passe ne correspond pas',
        path: ['password_confirmation'],
      })
  }
}

// Form setup with VeeValidate
const { handleSubmit, setFieldError, setFieldValue } = useForm({
  validationSchema: toTypedSchema(getSchema(selectedType.value)),
  initialValues: {
    type: selectedType.value,
    email: '',
    password: '',
    password_confirmation: '',
    agency_name: '',
    first_name: '',
    last_name: '',
  },
})

// Form fields - common
const { value: email, errorMessage: emailError } = useField<string>('email')
const { value: password, errorMessage: passwordError } = useField<string>('password')
const { value: password_confirmation, errorMessage: passwordConfirmationError } =
  useField<string>('password_confirmation')

// Form fields - agency
const { value: agency_name, errorMessage: agencyNameError } = useField<string>('agency_name')

// Form fields - particulier
const { value: first_name, errorMessage: firstNameError } = useField<string>('first_name')
const { value: last_name, errorMessage: lastNameError } = useField<string>('last_name')

// Watch for type changes and reset form
watch(selectedType, (newType) => {
  setFieldValue('type', newType)
  // Clear type-specific field errors when switching
  if (newType === 'agency') {
    setFieldValue('first_name', '')
    setFieldValue('last_name', '')
  } else {
    setFieldValue('agency_name', '')
  }
  apiError.value = null
})

// Submit handler
const onSubmit = handleSubmit(async () => {
  apiError.value = null

  // Prepare data based on type using field refs directly
  const submitData: FormData =
    selectedType.value === 'agency'
      ? {
          type: 'agency' as const,
          email: email.value,
          password: password.value,
          password_confirmation: password_confirmation.value,
          agency_name: agency_name.value,
        }
      : {
          type: 'particulier' as const,
          email: email.value,
          password: password.value,
          password_confirmation: password_confirmation.value,
          first_name: first_name.value,
          last_name: last_name.value,
        }

  const result = await registerProducer(submitData)

  if (result.success) {
    emit('success')
  } else {
    // Set field-specific errors from API
    if (result.errors) {
      const validFields = [
        'type',
        'email',
        'password',
        'password_confirmation',
        'agency_name',
        'first_name',
        'last_name',
      ] as const
      type ValidField = (typeof validFields)[number]

      Object.entries(result.errors).forEach(([field, messages]) => {
        if (messages && messages.length > 0 && validFields.includes(field as ValidField)) {
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

    <!-- Conditional: Agency Name -->
    <div v-if="selectedType === 'agency'" data-testid="agency-fields">
      <label for="agency_name" class="block text-sm font-medium text-gray-700 mb-1">
        Nom de l'agence<span class="text-red-500">*</span>
      </label>
      <input
        id="agency_name"
        v-model="agency_name"
        type="text"
        placeholder="Ex: WeAct Productions"
        :class="[
          'w-full px-4 py-3 border rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:outline-none transition-colors',
          agencyNameError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-200'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20',
        ]"
        data-testid="agency-name-input"
      />
      <p v-if="agencyNameError" class="mt-1 text-sm text-red-600" data-testid="agency-name-error">
        {{ agencyNameError }}
      </p>
    </div>

    <!-- Conditional: First/Last Name (2 columns) -->
    <div v-else class="grid grid-cols-2 gap-4" data-testid="particulier-fields">
      <div>
        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
          Prénom<span class="text-red-500">*</span>
        </label>
        <input
          id="first_name"
          v-model="first_name"
          type="text"
          placeholder="Jean"
          autocomplete="given-name"
          :class="[
            'w-full px-4 py-3 border rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:outline-none transition-colors',
            firstNameError
              ? 'border-red-300 focus:border-red-500 focus:ring-red-200'
              : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20',
          ]"
          data-testid="first-name-input"
        />
        <p v-if="firstNameError" class="mt-1 text-sm text-red-600" data-testid="first-name-error">
          {{ firstNameError }}
        </p>
      </div>
      <div>
        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">
          Nom<span class="text-red-500">*</span>
        </label>
        <input
          id="last_name"
          v-model="last_name"
          type="text"
          placeholder="Dupont"
          autocomplete="family-name"
          :class="[
            'w-full px-4 py-3 border rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:outline-none transition-colors',
            lastNameError
              ? 'border-red-300 focus:border-red-500 focus:ring-red-200'
              : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20',
          ]"
          data-testid="last-name-input"
        />
        <p v-if="lastNameError" class="mt-1 text-sm text-red-600" data-testid="last-name-error">
          {{ lastNameError }}
        </p>
      </div>
    </div>

    <!-- Email -->
    <div>
      <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
        Email professionnel<span class="text-red-500">*</span>
      </label>
      <input
        id="email"
        v-model="email"
        type="email"
        placeholder="contact@votreentreprise.com"
        autocomplete="email"
        :class="[
          'w-full px-4 py-3 border rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:outline-none transition-colors',
          emailError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-200'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20',
        ]"
        data-testid="email-input"
      />
      <p v-if="emailError" class="mt-1 text-sm text-red-600" data-testid="email-error">
        {{ emailError }}
      </p>
    </div>

    <!-- Password + Confirmation (same row) -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
          Mot de passe<span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input
            id="password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="••••••••"
            autocomplete="new-password"
            :class="[
              'w-full px-4 py-3 pr-12 border rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:outline-none transition-colors',
              passwordError
                ? 'border-red-300 focus:border-red-500 focus:ring-red-200'
                : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20',
            ]"
            data-testid="password-input"
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
            :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
            data-testid="toggle-password-visibility"
          >
            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
          </button>
        </div>
        <p v-if="passwordError" class="mt-1 text-sm text-red-600" data-testid="password-error">
          {{ passwordError }}
        </p>
        <p class="mt-1 text-xs text-gray-500">
          Min. 8 car., 1 majuscule, 1 chiffre
        </p>
      </div>
      <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
          Confirmation<span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input
            id="password_confirmation"
            v-model="password_confirmation"
            :type="showPasswordConfirm ? 'text' : 'password'"
            placeholder="••••••••"
            autocomplete="new-password"
            :class="[
              'w-full px-4 py-3 pr-12 border rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:outline-none transition-colors',
              passwordConfirmationError
                ? 'border-red-300 focus:border-red-500 focus:ring-red-200'
                : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20',
            ]"
            data-testid="password-confirmation-input"
          />
          <button
            type="button"
            @click="showPasswordConfirm = !showPasswordConfirm"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
            :aria-label="showPasswordConfirm ? 'Masquer la confirmation du mot de passe' : 'Afficher la confirmation du mot de passe'"
            data-testid="toggle-password-confirm-visibility"
          >
            <svg v-if="!showPasswordConfirm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
          </button>
        </div>
        <p
          v-if="passwordConfirmationError"
          class="mt-1 text-sm text-red-600"
          data-testid="password-confirmation-error"
        >
          {{ passwordConfirmationError }}
        </p>
      </div>
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
  </form>
</template>

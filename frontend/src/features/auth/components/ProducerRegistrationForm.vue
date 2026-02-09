<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm, useField } from 'vee-validate'
import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'
import { useAuth } from '../composables/useAuth'
import type { ProducerRegistrationForm as FormData, ProducerType } from '../types'
import { FloatingField } from '@/components/ui/form'
import { User, Mail, Lock, Building } from 'lucide-vue-next'

const emit = defineEmits<{
  success: []
}>()

const { registerProducer, isLoading } = useAuth()

// Selected producer type
const selectedType = ref<ProducerType>('agency')

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
      <FloatingField
        id="agency_name"
        v-model="agency_name"
        label="Nom de l'agence"
        :icon="Building"
        :error="agencyNameError"
        required
        data-testid="agency-name-input"
      />
    </div>

    <!-- Conditional: First/Last Name (2 columns) -->
    <div v-else class="grid grid-cols-2 gap-4" data-testid="particulier-fields">
      <FloatingField
        id="first_name"
        v-model="first_name"
        label="Prénom"
        :icon="User"
        :error="firstNameError"
        required
        autocomplete="given-name"
        data-testid="first-name-input"
      />
      <FloatingField
        id="last_name"
        v-model="last_name"
        label="Nom"
        :icon="User"
        :error="lastNameError"
        required
        autocomplete="family-name"
        data-testid="last-name-input"
      />
    </div>

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

    <!-- Password + Confirmation (same row) -->
    <div class="grid grid-cols-2 gap-4">
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
      <FloatingField
        id="password_confirmation"
        v-model="password_confirmation"
        type="password"
        label="Confirmation"
        :icon="Lock"
        :error="passwordConfirmationError"
        required
        autocomplete="new-password"
        password-toggle
        data-testid="password-confirmation-input"
      />
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

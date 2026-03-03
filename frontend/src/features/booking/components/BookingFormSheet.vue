<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { bookingSchema } from '../schemas/booking'
import { useBookingCreate } from '../composables/useBookingCreate'
import { calculatePricingPreview, type CreateBookingData, type Booking } from '../types'
import BookingPricingBreakdown from './BookingPricingBreakdown.vue'
import { FloatingField, FloatingDateField, FloatingTextarea, FloatingSelect } from '@/components/ui/form'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import {
  X,
  Calendar,
  Clock,
  Film,
  MessageSquare,
  Loader2,
  Send,
  AlertCircle,
  Receipt,
} from 'lucide-vue-next'

const props = defineProps<{
  isOpen: boolean
  faceId: number
  faceName: string
  tarifHoraire: number | null
  tarifJournalier: number | null
}>()

const emit = defineEmits<{
  close: []
  success: [booking: Booking]
}>()

const { createBooking, isSubmitting, error, validationErrors } = useBookingCreate()
const toast = useToast()

// Focus trap
const dialogRef = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const contentTypeOptions = [
  { value: 'Publicité', label: 'Publicité' },
  { value: 'Film', label: 'Film' },
  { value: 'Clip musical', label: 'Clip musical' },
  { value: 'Court-métrage', label: 'Court-métrage' },
  { value: 'Shooting photo', label: 'Shooting photo' },
  { value: 'Contenu réseaux sociaux', label: 'Contenu réseaux sociaux' },
  { value: 'Autre', label: 'Autre' },
]

const { handleSubmit, setFieldError, resetForm } = useForm({
  validationSchema: toTypedSchema(bookingSchema),
  initialValues: {
    face_id: props.faceId,
    date_debut: '',
    date_fin: '',
    duree_heures: 4,
    type_contenu: 'Publicité',
    message: '',
  },
})

const { value: date_debut, errorMessage: dateDebutError } = useField<string>('date_debut')
const { value: date_fin, errorMessage: dateFinError } = useField<string>('date_fin')
const { value: duree_heures, errorMessage: dureeError } = useField<number>('duree_heures')
const { value: type_contenu, errorMessage: typeContenuError } = useField<string>('type_contenu')
const { value: message, errorMessage: messageError } = useField<string>('message')

// Pricing preview
const pricingPreview = computed(() => {
  if (!duree_heures.value || duree_heures.value < 4) return null

  let tarifBase = 0
  const hours = duree_heures.value

  if (hours >= 8 && props.tarifJournalier) {
    const days = Math.ceil(hours / 8)
    tarifBase = days * props.tarifJournalier
  } else if (props.tarifHoraire) {
    tarifBase = hours * props.tarifHoraire
  } else if (props.tarifJournalier) {
    tarifBase = Math.round((props.tarifJournalier / 8) * hours)
  }

  if (tarifBase === 0) return null
  return calculatePricingPreview(tarifBase)
})

// Reset form and manage focus when opened/closed
watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      previouslyFocused = document.activeElement as HTMLElement
      resetForm({
        values: {
          face_id: props.faceId,
          date_debut: '',
          date_fin: '',
          duree_heures: 4,
          type_contenu: 'Publicité',
          message: '',
        },
      })
      nextTick(() => {
        const firstInput = dialogRef.value?.querySelector<HTMLElement>('input, select, textarea')
        firstInput?.focus()
      })
    } else {
      if (previouslyFocused) {
        previouslyFocused.focus()
        previouslyFocused = null
      }
    }
  },
)

// Handle Escape key and focus trap
function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close')
    return
  }

  if (e.key === 'Tab' && dialogRef.value) {
    const focusable = dialogRef.value.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    )
    if (focusable.length === 0) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

const onSubmit = handleSubmit(async (values) => {
  const data: CreateBookingData = {
    face_id: props.faceId,
    date_debut: values.date_debut,
    date_fin: values.date_fin,
    duree_heures: values.duree_heures,
    type_contenu: values.type_contenu,
    message: values.message || undefined,
  }

  const result = await createBooking(data)

  if (result.success && result.data) {
    toast.success('Demande de booking envoyée !')
    emit('success', result.data)
    emit('close')
  } else if (result.errors) {
    const validFields = [
      'face_id',
      'date_debut',
      'date_fin',
      'duree_heures',
      'type_contenu',
      'message',
    ] as const
    type ValidField = (typeof validFields)[number]

    Object.entries(result.errors).forEach(([field, messages]) => {
      if (messages && messages.length > 0 && validFields.includes(field as ValidField)) {
        setFieldError(field as ValidField, messages[0])
      }
    })
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm"
        data-testid="booking-form-sheet"
        @click.self="emit('close')"
        @keydown="handleKeydown"
      >
        <Transition
          appear
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 translate-y-8 sm:translate-y-2 sm:scale-95"
          enter-to-class="opacity-100 translate-y-0 sm:scale-100"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="opacity-100 translate-y-0 sm:scale-100"
          leave-to-class="opacity-0 translate-y-8 sm:translate-y-2 sm:scale-95"
        >
          <div
            v-if="isOpen"
            ref="dialogRef"
            class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white shadow-2xl rounded-t-2xl sm:rounded-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="booking-sheet-title"
          >
            <!-- Header -->
            <div class="sticky top-0 z-10 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between rounded-t-2xl">
              <div>
                <h2 id="booking-sheet-title" class="text-lg font-semibold text-gray-900">
                  Réserver {{ faceName }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                  Envoyez une demande de booking
                </p>
              </div>
              <button
                type="button"
                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-all"
                aria-label="Fermer"
                data-testid="close-button"
                @click="emit('close')"
              >
                <X :size="20" />
              </button>
            </div>

            <!-- Form -->
            <form class="p-6 space-y-5" @submit="onSubmit">
              <!-- API Error -->
              <div
                v-if="error"
                class="flex items-start gap-3 p-4 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700"
                role="alert"
                data-testid="api-error"
              >
                <AlertCircle :size="18" class="shrink-0 mt-0.5" />
                <span>{{ error }}</span>
              </div>

              <!-- Dates row -->
              <div class="grid grid-cols-2 gap-4">
                <FloatingDateField
                  id="date_debut"
                  v-model="date_debut"
                  label="Date de début"
                  :icon="Calendar"
                  :error="dateDebutError"
                  required
                />
                <FloatingDateField
                  id="date_fin"
                  v-model="date_fin"
                  label="Date de fin"
                  :icon="Calendar"
                  :error="dateFinError"
                  required
                />
              </div>

              <!-- Duration -->
              <FloatingField
                id="duree_heures"
                v-model="duree_heures"
                type="number"
                label="Durée (heures)"
                :icon="Clock"
                :error="dureeError"
                required
              />

              <!-- Content type -->
              <FloatingSelect
                id="type_contenu"
                v-model="type_contenu"
                label="Type de contenu"
                :icon="Film"
                :error="typeContenuError"
                :options="contentTypeOptions"
                required
              />

              <!-- Message -->
              <FloatingTextarea
                id="message"
                v-model="message"
                label="Message (optionnel)"
                :icon="MessageSquare"
                :error="messageError"
              />

              <!-- Pricing Preview -->
              <div
                v-if="pricingPreview"
                class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3"
                data-testid="pricing-preview"
              >
                <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                  <Receipt :size="16" />
                  Estimation tarifaire
                </div>
                <BookingPricingBreakdown :tarif-base="pricingPreview.tarifBase" role="producer" />
                <p class="text-xs text-gray-400 mt-1">
                  Le montant final sera confirmé après acceptation de la Face.
                </p>
              </div>

              <!-- Submit -->
              <div class="pt-2">
                <Button
                  type="submit"
                  class="w-full"
                  :disabled="isSubmitting"
                  data-testid="submit-booking"
                >
                  <Loader2 v-if="isSubmitting" :size="18" class="animate-spin mr-2" />
                  <Send v-else :size="18" class="mr-2" />
                  {{ isSubmitting ? 'Envoi en cours...' : 'Envoyer la demande' }}
                </Button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

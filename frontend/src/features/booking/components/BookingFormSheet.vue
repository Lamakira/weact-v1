<script setup lang="ts">
import { formatMoney } from '@/utils/money'
import { ref, computed, watch, nextTick, toRef } from 'vue'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { bookingSchema } from '../schemas/booking'
import { useBookingCreate } from '../composables/useBookingCreate'
import { calculatePricingPreview, type CreateBookingData, type Booking } from '../types'
import BookingPricingBreakdown from './BookingPricingBreakdown.vue'
import UgcBookingFields from './UgcBookingFields.vue'
import ImageTermsFields from '@/features/imageContract/components/ImageTermsFields.vue'
import ImageContractCodeNotice from '@/features/imageContract/components/ImageContractCodeNotice.vue'
import ImageContractSignDialog from '@/features/imageContract/components/ImageContractSignDialog.vue'
import { useImageContractsEnabled } from '@/features/imageContract/composables/useImageContractsEnabled'
import { defaultFinalite, supportLabel, territoireLabel } from '@/features/imageContract/constants'
import type {
  ImageDuree,
  ImageSignature,
  ImageSupport,
  ImageTerritoire,
  SignatureSubmitOutcome,
} from '@/features/imageContract/types'
import { signatureFieldError } from '@/features/imageContract/utils/signatureErrors'
import { useAuthStore } from '@/stores/auth'
import {
  CommissionBreakdown,
  computeUgcCommission,
  computeUgcHybridProducerTotal,
  type UgcCompensationType,
} from '@/components/ugc'
import { FloatingField, FloatingDateField, FloatingSelect, FloatingTextarea } from '@/components/ui/form'
import { Button } from '@/components/ui/button'
import { BENIN_CITY_OPTIONS } from '@/shared/constants/beninCities'
import { useToast } from '@/composables/useToast'
import { useFaceAvailability } from '@/features/agenda/composables/useFaceAvailability'
import {
  firstUnavailableIn,
  formatDayMonth,
  formatDayNumbers,
  formatMonthName,
  monthStart,
} from '@/features/agenda/utils/agendaDates'
import {
  addDaysIso,
  bookingMinStartDateIso,
  bookingResponseDeadline,
  businessTodayIso,
  formatBusinessDeadline,
} from '@/lib/dates'
import {
  X,
  Calendar,
  Clock,
  Film,
  MapPin,
  Loader2,
  Send,
  AlertCircle,
  Receipt,
  Info,
  ShieldCheck,
} from 'lucide-vue-next'

const props = defineProps<{
  isOpen: boolean
  faceId: string
  faceName: string
  tarifHoraire: number | null
  tarifJournalier: number | null
}>()

const emit = defineEmits<{
  close: []
  success: [booking: Booking]
}>()

const { createBooking, isSubmitting, error } = useBookingCreate()
const toast = useToast()
const authStore = useAuthStore()
const imageContractsEnabled = useImageContractsEnabled()

// Le compte vient de localStorage : sans relecture, une session ouverte avant l'activation
// du contrat enverrait un booking sans conditions d'image, refusé par le serveur.
watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) void authStore.refreshUser?.()
  },
  { immediate: true },
)

// Focus trap
const dialogRef = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

// Duration presets (½ journée → 5 journées, puis custom)
const durationPresets = [
  { value: '4',      label: '½ journée (max 4h)' },
  { value: '8',      label: '1 journée (max 8h)' },
  { value: '12',     label: '1,5 jour (max 12h)' },
  { value: '16',     label: '2 journées (max 16h)' },
  { value: '20',     label: '2,5 jours (max 20h)' },
  { value: '24',     label: '3 journées (max 24h)' },
  { value: '28',     label: '3,5 jours (max 28h)' },
  { value: '32',     label: '4 journées (max 32h)' },
  { value: '36',     label: '4,5 jours (max 36h)' },
  { value: '40',     label: '5 journées (max 40h)' },
  { value: 'custom', label: 'Plus de 5 jours...' },
]

const selectedPreset = ref<string>('4')
const customDays = ref<number>(6)
const customDaysError = ref<string>('')

const contentTypeOptions = [
  { value: 'Publicité', label: 'Publicité' },
  { value: 'Film', label: 'Film' },
  { value: 'Clip musical', label: 'Clip musical' },
  { value: 'Court-métrage', label: 'Court-métrage' },
  { value: 'Shooting photo', label: 'Shooting photo' },
  { value: 'Contenu réseaux sociaux', label: 'Contenu réseaux sociaux' },
  { value: 'UGC', label: 'UGC' },
  { value: 'Autre', label: 'Autre' },
]

// Single source of truth for form defaults (factory so face_id is re-read on each reset).
const makeDefaultValues = () => ({
  face_id: props.faceId,
  date_debut: '',
  date_fin: '',
  duree_heures: 4,
  type_contenu: 'Publicité',
  lieu: '',
  message: '',
  type_compensation: 'product',
  nom_produit: '',
  valeur_produit: undefined,
  nombre_videos: undefined,
  montant_remuneration: undefined,
})

const { handleSubmit, setFieldError, resetForm } = useForm({
  validationSchema: toTypedSchema(bookingSchema),
  initialValues: makeDefaultValues(),
})

const { value: date_debut, errorMessage: dateDebutError } = useField<string>('date_debut')
const { value: date_fin, errorMessage: dateFinError } = useField<string>('date_fin')

// Bornes alignées sur le backend : date_debut `after_or_equal:today` (heure du Bénin),
// date_fin `after_or_equal:date_debut`.
const dateDebutMin = bookingMinStartDateIso()
const formOpenedAt = new Date()
const dateFinMin = computed(() => date_debut.value || dateDebutMin)
const { value: duree_heures, errorMessage: dureeError } = useField<number>('duree_heures')
const { value: type_contenu, errorMessage: typeContenuError } = useField<string>('type_contenu')
const { value: lieu, errorMessage: lieuError } = useField<string>('lieu')
// Lot B12 : brief facultatif (cash et UGC), sans coordonnées.
const { value: message, errorMessage: messageError } = useField<string>('message')

// UGC fields (validated conditionally when type_contenu === 'UGC')
const { value: type_compensation } = useField<UgcCompensationType>('type_compensation')
const { value: nom_produit, errorMessage: nomProduitError } = useField<string>('nom_produit')
const { value: valeur_produit, errorMessage: valeurProduitError } = useField<number | string | undefined>('valeur_produit')
const { value: nombre_videos, errorMessage: nombreVideosError } = useField<number | string | undefined>('nombre_videos')
const { value: montant_remuneration, errorMessage: montantRemunerationError } = useField<number | string | undefined>('montant_remuneration')

// Photos produit (0-2, facultatives — spec photos produit). Hors vee-validate :
// fichiers locaux avec préviews, envoyés en FormData à la création.
const productPhotos = ref<File[]>([])
const productPhotosError = ref<string>('')

const isUgc = computed(() => type_contenu.value === 'UGC')

// Disponibilité de la Face (booking cash seulement) : les champs date sont natifs et ne peuvent pas
// griser des jours, on prévient donc ici. Le serveur refuse aussi la plage (même message).
const availability = useFaceAvailability(toRef(props, 'faceId'))
const isIsoDay = (value: unknown): value is string => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)
const shootRange = computed<{ start: string; end: string } | null>(() => {
  if (isUgc.value || !isIsoDay(date_debut.value)) return null
  const end = isIsoDay(date_fin.value) && date_fin.value >= date_debut.value ? date_fin.value : date_debut.value
  return { start: date_debut.value, end }
})

watch(
  [() => props.isOpen, () => props.faceId, shootRange],
  ([open, , range]) => {
    if (open && range) void availability.loadRange(range.start, range.end)
  },
  { immediate: true },
)

const unavailableConflictDay = computed(() =>
  shootRange.value
    ? firstUnavailableIn(availability.allUnavailable.value, shootRange.value.start, shootRange.value.end)
    : null,
)
const availabilityError = computed(() =>
  unavailableConflictDay.value
    ? `Cette Face est indisponible le ${formatDayMonth(unavailableConflictDay.value)}. Choisissez d'autres dates.`
    : undefined,
)

/** Demande de dernière minute (tournage aujourd'hui ou demain, heure du Bénin) : échéances annoncées. */
const shortNoticeMessage = computed<string | null>(() => {
  if (isUgc.value || !date_debut.value) return null
  const today = businessTodayIso(formOpenedAt)
  const isToday = date_debut.value === today
  if (!isToday && date_debut.value !== addDaysIso(today, 1)) return null

  const deadline = formatBusinessDeadline(bookingResponseDeadline(date_debut.value, formOpenedAt), formOpenedAt)
  const payment = isToday
    ? 'Si elle accepte, vous aurez 2 h pour payer.'
    : 'Si elle accepte, vous aurez jusqu\'à 08 h le jour du tournage pour payer (au moins 2 h après son acceptation).'

  return `Demande de dernière minute : la Face a jusqu'à ${deadline} pour répondre. ${payment}`
})
const dateDebutShownError = computed(() => dateDebutError.value || availabilityError.value)

/** « Jours indisponibles en novembre : 12, 13, 18 au 20 » pour le mois du début, puis celui de la fin. */
const unavailableHints = computed<string[]>(() => {
  const range = shootRange.value
  if (!range) return []
  const months = [...new Set([monthStart(range.start), monthStart(range.end)])]
  return months.flatMap((month) => {
    const days = availability.unavailableInMonth(month).filter((day) => day >= dateDebutMin)
    return days.length > 0 ? [`Jours indisponibles en ${formatMonthName(month)} : ${formatDayNumbers(days)}`] : []
  })
})

// Autorisation d'image (flag serveur) : conditions saisies ici, signature dans la fenêtre dédiée.
const imageFinalite = ref('')
const imageFinaliteTouched = ref(false)
const imageSupports = ref<ImageSupport[]>([])
const imageTerritoire = ref<ImageTerritoire>('benin')
const imageDuree = ref<ImageDuree>(1)
const imageTermsError = ref<string | null>(null)
const showSignDialog = ref(false)
const pendingData = ref<CreateBookingData | null>(null)

// Finalité préremplie d'après le type de contenu tant que le Producteur n'y a pas touché.
watch(
  () => type_contenu.value,
  (value) => {
    if (!imageFinaliteTouched.value) imageFinalite.value = defaultFinalite(value)
  },
  { immediate: true },
)

function updateImageFinalite(value: string): void {
  imageFinaliteTouched.value = true
  imageFinalite.value = value
}

const signerDefaultName = computed(() => {
  const userable = authStore.user?.userable as
    | { first_name?: string | null; last_name?: string | null; display_name?: string }
    | null
    | undefined
  const personal = `${userable?.first_name ?? ''} ${userable?.last_name ?? ''}`.trim()
  return personal || userable?.display_name || ''
})
// Photos produit obligatoires (1-2) pour l'UGC uniquement — calque la garde
// confirm-disabled de la modale de réception Face. Un booking non-UGC n'a pas de photos.
const ugcPhotosMissing = computed(() => isUgc.value && productPhotos.value.length === 0)
// Montant « À payer maintenant » — DOIT refléter le récap CommissionBreakdown :
// produit-seul (ou hybride sans cash saisi) = commission sur la valeur produit ;
// hybride avec cash = rémunération + frais de service 10 % (computeUgcHybridProducerTotal).
const ugcPayable = computed(() => {
  const cash = Number(montant_remuneration.value) || 0
  return type_compensation.value === 'hybrid' && cash > 0
    ? computeUgcHybridProducerTotal(cash)
    : computeUgcCommission(Number(valeur_produit.value) || 0)
})
const submitLabel = computed(() => {
  if (imageContractsEnabled.value) return 'Lire et signer le contrat'
  if (isSubmitting.value) return isUgc.value ? 'Création...' : 'Envoi en cours...'
  if (isUgc.value) {
    const amount = formatMoney(ugcPayable.value)
    // En hybride le montant n'est pas une commission (rémunération + frais) → libellé neutre.
    return type_compensation.value === 'hybrid' && (Number(montant_remuneration.value) || 0) > 0
      ? `Payer · ${amount}`
      : `Payer la commission · ${amount}`
  }
  return 'Envoyer la demande'
})

// Sync duration preset / custom days → duree_heures field
watch([selectedPreset, customDays], () => {
  if (selectedPreset.value === 'custom') {
    const days = customDays.value
    if (!Number.isInteger(days) || days <= 5) {
      customDaysError.value = 'Entrez un nombre de jours entier supérieur à 5'
      return
    }
    customDaysError.value = ''
    duree_heures.value = days * 8
  } else {
    customDaysError.value = ''
    duree_heures.value = Number(selectedPreset.value)
  }
})

// Pricing preview
const pricingPreview = computed(() => {
  if (!duree_heures.value || duree_heures.value < 4) return null
  if (!props.tarifJournalier) return null

  const tarifBase = Math.round((props.tarifJournalier / 8) * duree_heures.value)
  if (tarifBase === 0) return null
  return calculatePricingPreview(tarifBase)
})

// Reset form and manage focus when opened/closed
watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      previouslyFocused = document.activeElement as HTMLElement
      selectedPreset.value = '4'
      customDays.value = 6
      customDaysError.value = ''
      productPhotos.value = []
      productPhotosError.value = ''
      imageFinaliteTouched.value = false
      imageFinalite.value = defaultFinalite(type_contenu.value)
      imageSupports.value = []
      imageTerritoire.value = 'benin'
      imageDuree.value = 1
      imageTermsError.value = null
      showSignDialog.value = false
      pendingData.value = null
      resetForm({ values: makeDefaultValues() })
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
  // Le composant est chargé en asynchrone : il peut se monter déjà ouvert
  { immediate: true },
)

// Handle Escape key and focus trap
function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && props.isOpen) {
    if (showSignDialog.value) return
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
    if (!first || !last) return

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

const STALE_FORM_MESSAGE = 'Une nouvelle étape est nécessaire : renseignez l\'utilisation de l\'image.'

const validFields = [
  'face_id',
  'date_debut',
  'date_fin',
  'duree_heures',
  'type_contenu',
  'lieu',
  'message',
  'type_compensation',
  'nom_produit',
  'valeur_produit',
  'nombre_videos',
  'montant_remuneration',
] as const
type ValidField = (typeof validFields)[number]

function applyServerErrors(errors: Record<string, string[]>): void {
  Object.entries(errors).forEach(([field, messages]) => {
    if (messages && messages.length > 0 && validFields.includes(field as ValidField)) {
      setFieldError(field as ValidField, messages[0])
    }
    // Erreurs photos produit (clés `product_photos` ou `product_photos.N`) —
    // hors vee-validate, affichées sous l'uploader.
    if (messages && messages.length > 0 && field.startsWith('product_photos')) {
      productPhotosError.value = messages[0] ?? ''
    }
    if (messages && messages.length > 0 && field.startsWith('image_terms')) {
      imageTermsError.value = messages[0] ?? null
    }
  })
}

function validateImageTerms(): boolean {
  if (imageFinalite.value.trim() === '') {
    imageTermsError.value = "Indiquez la finalité de l'utilisation de l'image."
    return false
  }
  if (imageSupports.value.length === 0) {
    imageTermsError.value = 'Choisissez au moins un support.'
    return false
  }
  imageTermsError.value = null
  return true
}

function onSuccess(booking: Booking): void {
  toast.success(isUgc.value ? 'Booking UGC créé.' : 'Demande de booking envoyée !')
  emit('success', booking)
  emit('close')
}

const onSubmit = handleSubmit(async (values) => {
  // Plage touchant un jour indisponible : l'erreur est déjà affichée sous la date de début.
  if (values.type_contenu !== 'UGC' && unavailableConflictDay.value) return

  // Garde photos produit UGC : au moins une photo requise (le bouton est déjà
  // désactivé, garde défensive miroir du backend `product_photos.required`).
  if (values.type_contenu === 'UGC' && productPhotos.value.length === 0) {
    productPhotosError.value = 'Au moins une photo du produit est requise.'
    return
  }

  const data: CreateBookingData = {
    face_id: props.faceId,
    type_contenu: values.type_contenu,
  }

  if (values.type_contenu === 'UGC') {
    // UGC dotation: no shoot date / duration / location — only the compensation fields.
    data.type_compensation = values.type_compensation as 'product' | 'hybrid'
    data.nom_produit = values.nom_produit?.trim()
    data.valeur_produit = Number(values.valeur_produit)
    if (values.type_compensation === 'hybrid') {
      data.nombre_videos = Number(values.nombre_videos)
      data.montant_remuneration = Number(values.montant_remuneration)
    }
    if (productPhotos.value.length > 0) {
      data.product_photos = productPhotos.value
    }
  } else {
    data.date_debut = values.date_debut
    data.date_fin = values.date_fin
    data.duree_heures = values.duree_heures
    data.lieu = values.lieu
  }

  const brief = values.message?.trim()
  if (brief) {
    data.message = brief
  }

  // Autorisation d'image : le booking part depuis la fenêtre de signature.
  if (imageContractsEnabled.value) {
    if (!validateImageTerms()) return
    pendingData.value = data
    showSignDialog.value = true
    return
  }

  const result = await createBooking(data)

  if (result.success && result.data) {
    onSuccess(result.data)
  } else if (result.errors) {
    const staleForm = Object.keys(result.errors).some(
      (key) => key.startsWith('image_terms') || key === 'signature',
    )
    if (staleForm) {
      // Formulaire resté ouvert à l'activation : relire le compte fait apparaître la section.
      await authStore.refreshUser?.()
      imageTermsError.value = STALE_FORM_MESSAGE
      toast.error(STALE_FORM_MESSAGE)
      return
    }
    applyServerErrors(result.errors)
  }
})

// Contrepartie telle que la Face la verra : cachet, ou produit (+ rémunération en hybride).
const signCompensation = computed(() => {
  if (isUgc.value) {
    const product = `${nom_produit.value || 'Produit'} (${formatMoney(Number(valeur_produit.value) || 0)})`
    const cash = Number(montant_remuneration.value) || 0
    return type_compensation.value === 'hybrid' && cash > 0
      ? `${product} et ${formatMoney(cash)}`
      : product
  }
  return pricingPreview.value
    ? `Rémunération de ${formatMoney(pricingPreview.value.tarifBase)} selon le tarif de la Face`
    : 'Rémunération selon le tarif de la Face'
})

const signSummary = computed(() => ({
  projet: type_contenu.value,
  finalite: imageFinalite.value.trim(),
  supports: imageSupports.value.map(supportLabel),
  territoire: territoireLabel(imageTerritoire.value),
  dureeAnnees: imageDuree.value,
  contrepartie: signCompensation.value,
}))

async function submitSigned(signature: ImageSignature): Promise<SignatureSubmitOutcome> {
  if (!pendingData.value) {
    return { ok: false, message: 'La demande est introuvable. Fermez la fenêtre et recommencez.' }
  }

  const result = await createBooking({
    ...pendingData.value,
    image_terms: {
      finalite: imageFinalite.value.trim(),
      supports: imageSupports.value,
      territoire: imageTerritoire.value,
      duree_annees: imageDuree.value,
    },
    signature,
  })

  if (result.success && result.data) {
    showSignDialog.value = false
    pendingData.value = null
    onSuccess(result.data)
    return { ok: true }
  }

  // Erreur sur la signature (ex. nom refusé) : la fenêtre reste ouverte, le code reste valide côté serveur.
  const signatureError = signatureFieldError(result.errors)
  if (signatureError) return { ok: false, code: signatureError.code, message: signatureError.message }

  const isSignatureError = (result.errorCode ?? '').startsWith('SIGNATURE_CODE')
  if (!isSignatureError && result.errors && Object.keys(result.errors).length > 0) {
    // Erreur sur un champ de la demande : retour au formulaire pour la corriger.
    showSignDialog.value = false
    applyServerErrors(result.errors)
  }
  return { ok: false, code: result.errorCode, message: result.message }
}

function closeSignDialog(): void {
  showSignDialog.value = false
}
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
                  Booker {{ faceName }}
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

              <!-- Shoot details — cash bookings only (a UGC dotation has no shoot date/duration/location) -->
              <template v-if="!isUgc">
              <!-- Dates row -->
              <div class="grid grid-cols-2 gap-4">
                <FloatingDateField
                  id="date_debut"
                  v-model="date_debut"
                  label="Date de début"
                  :min="dateDebutMin"
                  :icon="Calendar"
                  :error="dateDebutShownError"
                  required
                />
                <FloatingDateField
                  id="date_fin"
                  v-model="date_fin"
                  label="Date de fin"
                  :min="dateFinMin"
                  :icon="Calendar"
                  :error="dateFinError"
                  required
                />
              </div>
              <div
                v-if="shortNoticeMessage"
                class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                data-testid="booking-short-notice"
              >
                {{ shortNoticeMessage }}
              </div>
              <ul
                v-if="unavailableHints.length > 0"
                class="-mt-2 space-y-0.5 text-xs text-gray-500"
                data-testid="unavailable-days-hint"
              >
                <li v-for="hint in unavailableHints" :key="hint">{{ hint }}</li>
              </ul>

              <!-- Duration -->
              <FloatingSelect
                id="duree_preset"
                v-model="selectedPreset"
                label="Durée du tournage"
                :icon="Clock"
                :error="selectedPreset !== 'custom' ? dureeError : undefined"
                :options="durationPresets"
                required
              />
              <template v-if="selectedPreset === 'custom'">
                <FloatingField
                  id="custom_days"
                  v-model="customDays"
                  type="number"
                  label="Nombre de jours (> 5)"
                  :icon="Clock"
                  :error="customDaysError || dureeError"
                  required
                />
                <div class="flex items-start gap-2 text-xs text-gray-500 -mt-2">
                  <Info :size="14" class="shrink-0 mt-0.5 text-blue-400" />
                  <span>À partir de 5 jours, seuls des jours entiers sont possibles.</span>
                </div>
              </template>

              </template>

              <!-- Content type (always shown — this is where UGC is selected) -->
              <FloatingSelect
                id="type_contenu"
                v-model="type_contenu"
                label="Type de contenu"
                :icon="Film"
                :error="typeContenuError"
                :options="contentTypeOptions"
                required
              />

              <!-- Shooting location — cash bookings only -->
              <FloatingSelect
                v-if="!isUgc"
                id="lieu"
                v-model="lieu"
                label="Lieu du tournage"
                :icon="MapPin"
                :error="lieuError"
                :options="BENIN_CITY_OPTIONS"
                required
                data-testid="lieu-select"
              />

              <!-- UGC dynamic fields -->
              <template v-if="isUgc">
                <UgcBookingFields
                  v-model:compensation-type="type_compensation"
                  v-model:nom-produit="nom_produit"
                  v-model:valeur-produit="valeur_produit"
                  v-model:nombre-videos="nombre_videos"
                  v-model:montant-remuneration="montant_remuneration"
                  v-model:product-photos="productPhotos"
                  :nom-produit-error="nomProduitError"
                  :valeur-produit-error="valeurProduitError"
                  :nombre-videos-error="nombreVideosError"
                  :montant-remuneration-error="montantRemunerationError"
                  :product-photos-error="productPhotosError || undefined"
                />
                <CommissionBreakdown
                  :product-value="Number(valeur_produit) || 0"
                  :pay-amount="type_compensation === 'hybrid' ? (Number(montant_remuneration) || 0) : 0"
                  :on-platform="true"
                />
                <div class="flex items-center gap-1.5 text-[11px] text-gray-500">
                  <ShieldCheck :size="12" class="shrink-0 text-weact" />
                  <span>Paiement WeAct sécurisé · Remboursé si refus</span>
                </div>
              </template>

              <!-- Brief facultatif (lot B12) : jamais de coordonnées avant paiement -->
              <div>
                <FloatingTextarea
                  id="message"
                  v-model="message"
                  label="Brief pour la Face (facultatif)"
                  :error="messageError"
                  :maxlength="1000"
                  :rows="3"
                  data-testid="booking-brief"
                />
                <p v-if="!messageError" class="mt-1 text-xs text-gray-500">
                  Décrivez le projet en quelques lignes. Pas de numéro, d'e-mail ni de lien : la messagerie s'ouvre après le paiement.
                </p>
              </div>

              <!-- Pricing Preview (cash bookings only) -->
              <div
                v-if="pricingPreview && !isUgc"
                class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3"
                data-testid="pricing-preview"
              >
                <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                  <Receipt :size="16" />
                  Estimation tarifaire
                </div>
                <BookingPricingBreakdown :tarif-base="pricingPreview.tarifBase" role="producer" />
              </div>

              <!-- Autorisation d'utilisation de l'image (flag serveur) -->
              <ImageTermsFields
                v-if="imageContractsEnabled"
                :finalite="imageFinalite"
                :supports="imageSupports"
                :territoire="imageTerritoire"
                :duree-annees="imageDuree"
                :error="imageTermsError"
                @update:finalite="updateImageFinalite"
                @update:supports="imageSupports = $event"
                @update:territoire="imageTerritoire = $event"
                @update:duree-annees="imageDuree = $event"
              />

              <!-- Submit -->
              <div class="space-y-3 pt-2">
                <ImageContractCodeNotice v-if="imageContractsEnabled" />
                <Button
                  type="submit"
                  class="w-full"
                  :disabled="isSubmitting || ugcPhotosMissing || !!unavailableConflictDay"
                  data-testid="submit-booking"
                >
                  <Loader2 v-if="isSubmitting" :size="18" class="animate-spin mr-2" />
                  <Send v-else :size="18" class="mr-2" />
                  {{ submitLabel }}
                </Button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>

  <ImageContractSignDialog
    v-if="imageContractsEnabled"
    :is-open="showSignDialog"
    role="producer"
    :summary="signSummary"
    :default-name="signerDefaultName"
    :submit="submitSigned"
    @close="closeSignDialog"
  />
</template>

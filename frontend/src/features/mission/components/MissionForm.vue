<script setup lang="ts">
import { formatMoney } from '@/utils/money'
import { ref, computed, watch } from 'vue'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { createMissionSchema } from '../schemas/mission'
import { useMissionCreate } from '../composables/useMissionCreate'
import {
  MISSION_DURATION_PRESETS,
  formatCustomDuration,
  parseDurationToPreset,
} from '../constants/missionDuration'
import { BENIN_CITY_OPTIONS } from '@/shared/constants/beninCities'
import {
  MissionType,
  MissionGender,
  MissionTypeLabel,
  getMissionTypeOptions,
  getMissionGenderOptions,
  type Mission,
  type CreateMissionData,
} from '../types'
import { Button } from '@/components/ui/button'
import { FloatingField, FloatingTextarea, FloatingSelect } from '@/components/ui/form'
import UgcDotationFields from './UgcDotationFields.vue'
import ImageTermsFields from '@/features/imageContract/components/ImageTermsFields.vue'
import ImageContractCodeNotice from '@/features/imageContract/components/ImageContractCodeNotice.vue'
import ImageContractSignDialog from '@/features/imageContract/components/ImageContractSignDialog.vue'
import { useImageContractsEnabled } from '@/features/imageContract/composables/useImageContractsEnabled'
import {
  FINALITE_MAX_LENGTH,
  defaultFinalite,
  supportLabel,
  territoireLabel,
} from '@/features/imageContract/constants'
import type {
  ImageDuree,
  ImageSignature,
  ImageSupport,
  ImageTerritoire,
  SignatureSubmitOutcome,
} from '@/features/imageContract/types'
import { signatureFieldError } from '@/features/imageContract/utils/signatureErrors'
import { useAuthStore } from '@/stores/auth'
import { CommissionBreakdown, computeUgcCommission, type UgcCompensationType } from '@/components/ugc'
import {
  Film,
  Users,
  MapPin,
  Clock,
  Wallet,
  Calendar,
  UserCircle,
  AlertCircle,
  Info,
  Loader2,
  Send,
  Save,
  ShieldAlert,
  Mail,
  Type,
  AlignLeft,
} from 'lucide-vue-next'
import { authApi } from '@/features/auth/services/authApi'
import { useToast } from '@/composables/useToast'
import { addDaysIso, tomorrowIso } from '@/lib/dates'

const props = withDefaults(defineProps<{
  mode?: 'create' | 'edit'
  initialValues?: Partial<CreateMissionData>
  isSubmitting?: boolean
}>(), {
  mode: 'create',
  isSubmitting: false
})

const emit = defineEmits<{
  success: [mission: Mission]
  cancel: []
  submit: [data: CreateMissionData]
}>()

const { createMission, isSubmitting: isCreating, error, errorCode, clearError } = useMissionCreate()
const isProcessing = computed(() => props.isSubmitting || isCreating.value)
const toast = useToast()
const authStore = useAuthStore()
const imageContractsEnabled = useImageContractsEnabled()

// Autorisation d'image : seulement à la création (l'édition ne touche jamais aux conditions signées).
const showImageTerms = computed(() => props.mode === 'create' && imageContractsEnabled.value)

// Le compte vient de localStorage : sans relecture, une session ouverte avant l'activation
// publierait une mission sans conditions d'image, refusée par le serveur.
if (props.mode === 'create') void authStore.refreshUser?.()

// Email verification state
const isResendingVerification = ref(false)
const isEmailNotVerified = computed(() => errorCode.value === 'EMAIL_NOT_VERIFIED')

/**
 * Resend verification email
 */
async function handleResendVerification(): Promise<void> {
  isResendingVerification.value = true
  try {
    const result = await authApi.resendVerificationEmail()
    if (result.sent) {
      toast.success('Un email de vérification a été envoyé.')
    } else if (result.verified) {
      toast.success('Votre email est déjà vérifié. Vous pouvez réessayer.')
    }
  } catch (err) {
    if ((err as { response?: { status?: number } })?.response?.status === 429) {
      toast.warning('Veuillez patienter avant de renvoyer un email.')
    } else {
      toast.error("Impossible d'envoyer l'email. Veuillez réessayer.")
    }
  } finally {
    isResendingVerification.value = false
  }
}

const missionTypeOptions =
  props.mode === 'create'
    ? [...getMissionTypeOptions(), { value: 'ugc', label: 'UGC' }]
    : getMissionTypeOptions()
const genderOptions = getMissionGenderOptions()

// Duration preset state
const initialDuree = props.initialValues?.duree
  ? parseDurationToPreset(props.initialValues.duree)
  : { preset: MISSION_DURATION_PRESETS[0]!.value }
const selectedDureePreset = ref<string>(initialDuree.preset)
const customDureeJours = ref<number>(initialDuree.customDays ?? 6)
const customDureeError = ref<string>('')
const isLegacyDuree = ref(initialDuree.isLegacy === true)

// Édition : la clôture initiale reste valable même passée (on peut déplacer le tournage après la clôture).
const initialDeadline = props.mode === 'edit' ? (props.initialValues?.date_limite_candidature ?? null) : null
const { handleSubmit, setFieldError } = useForm({
  validationSchema: toTypedSchema(createMissionSchema({ unchangedDeadline: initialDeadline })),
  initialValues: {
    titre: props.initialValues?.titre ?? '',
    description: props.initialValues?.description ?? '',
    date_tournage: props.initialValues?.date_tournage ?? '',
    profil_recherche: props.initialValues?.profil_recherche ?? '',
    budget: props.initialValues?.budget ?? undefined,
    date_limite_candidature: props.initialValues?.date_limite_candidature ?? '',
    nombre_faces_voulu: props.initialValues?.nombre_faces_voulu ?? 1,
    type_mission: props.initialValues?.type_mission ?? MissionType.PUBLICITE,
    type_mission_autre: props.initialValues?.type_mission_autre ?? '',
    genre_voulu: props.initialValues?.genre_voulu ?? MissionGender.TOUS,
    lieu: props.initialValues?.lieu ?? '',
    duree: props.initialValues?.duree ?? MISSION_DURATION_PRESETS[0]!.value,
    type_compensation: props.initialValues?.type_compensation ?? 'product',
    nom_produit: props.initialValues?.nom_produit ?? '',
    valeur_produit: props.initialValues?.valeur_produit ?? undefined,
    nombre_videos: props.initialValues?.nombre_videos ?? undefined,
    montant_remuneration: props.initialValues?.montant_remuneration ?? undefined,
  },
})

const { value: titre, errorMessage: titreError } = useField<string>('titre')
const { value: type_mission, errorMessage: typeMissionError } = useField<string>('type_mission')
const { value: type_mission_autre, errorMessage: typeMissionAutreError } = useField<string>('type_mission_autre')
const { value: description, errorMessage: descriptionError } = useField<string>('description')
const { value: profil_recherche, errorMessage: profilRechercheError } =
  useField<string>('profil_recherche')
const { value: budget, errorMessage: budgetError } = useField<number>('budget')
const { value: lieu, errorMessage: lieuError } = useField<string>('lieu')
const { value: duree, errorMessage: dureeError } = useField<string>('duree')
const { value: genre_voulu, errorMessage: genreVouluError } = useField<string>('genre_voulu')
const { value: nombre_faces_voulu, errorMessage: nombreFacesError } =
  useField<number>('nombre_faces_voulu')
const { value: date_limite_candidature, errorMessage: dateLimiteError } =
  useField<string>('date_limite_candidature')
const { value: date_tournage, errorMessage: dateTournageError } = useField<string>('date_tournage')

// Bornes alignées sur les règles backend : clôture `after:today`, tournage `after:date_limite_candidature`.
// Édition : la valeur initiale (même passée) reste sélectionnable ; une autre valeur garde `after:today`.
const dateLimiteMin = (() => {
  const tomorrow = tomorrowIso()
  const initial = initialDeadline ? initialDeadline.slice(0, 10) : null
  return initial !== null && initial < tomorrow ? initial : tomorrow
})()
const dateLimiteMax = computed(() =>
  date_tournage.value && !isUgc.value ? addDaysIso(date_tournage.value, -1) : undefined,
)
const dateTournageMin = computed(() => {
  const afterDeadline = addDaysIso(date_limite_candidature.value || dateLimiteMin, 1)
  // Édition avec clôture passée conservée : le tournage ne peut pas être aujourd'hui ni passé.
  return props.mode === 'edit' ? [afterDeadline, tomorrowIso()].sort().pop()! : afterDeadline
})

// UGC dotation fields (rendus uniquement quand type_mission === 'ugc')
const { value: type_compensation, errorMessage: typeCompensationError } =
  useField<UgcCompensationType>('type_compensation')
const { value: nom_produit, errorMessage: nomProduitError } = useField<string>('nom_produit')
const { value: valeur_produit, errorMessage: valeurProduitError } =
  useField<number | string | undefined>('valeur_produit')
const { value: nombre_videos, errorMessage: nombreVideosError } =
  useField<number | string | undefined>('nombre_videos')
const { value: montant_remuneration, errorMessage: montantRemunerationError } =
  useField<number | string | undefined>('montant_remuneration')

// Photos produit (0-2, facultatives, création seulement — spec photos produit).
// Hors vee-validate : fichiers locaux avec préviews, envoyés en FormData.
const productPhotos = ref<File[]>([])
const productPhotosError = ref<string>('')

const isUgc = computed(() => type_mission.value === 'ugc')

// Conditions d'image (flag serveur) : saisies ici, signature dans la fenêtre dédiée.
const initialImageTerms = props.initialValues?.image_terms
const imageFinalite = ref(initialImageTerms?.finalite ?? '')
const imageFinaliteTouched = ref(initialImageTerms !== undefined)
const imageSupports = ref<ImageSupport[]>(initialImageTerms ? [...initialImageTerms.supports] : [])
const imageTerritoire = ref<ImageTerritoire>(initialImageTerms?.territoire ?? 'benin')
const imageDuree = ref<ImageDuree>(initialImageTerms?.duree_annees ?? 1)
const imageTermsError = ref<string | null>(null)
const showSignDialog = ref(false)
const pendingData = ref<CreateMissionData | null>(null)

const STALE_FORM_MESSAGE = "Une nouvelle étape est nécessaire : renseignez l'utilisation de l'image."
const IMAGE_TERMS_INTRO = 'Ces choix s\'appliquent à toutes les Faces que vous retiendrez.'

// Finalité proposée d'après le type de mission, sinon d'après le titre, tant que le Producteur n'y a pas touché.
const suggestedFinalite = computed(() => {
  const label = isUgc.value ? 'UGC' : (MissionTypeLabel[type_mission.value as keyof typeof MissionTypeLabel] ?? '')
  return (defaultFinalite(label) || (titre.value ?? '').trim()).slice(0, FINALITE_MAX_LENGTH)
})
watch(
  suggestedFinalite,
  (value) => {
    if (!imageFinaliteTouched.value) imageFinalite.value = value
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
// Photos produit obligatoires (1-2) pour l'UGC à la CRÉATION uniquement — calque la garde
// confirm-disabled de la modale de réception Face. Masquées/non requises en édition (v1).
const ugcPhotosMissing = computed(
  () => isUgc.value && props.mode === 'create' && productPhotos.value.length === 0,
)
const ugcCommission = computed(() => computeUgcCommission(Number(valeur_produit.value) || 0))
const submitLabel = computed(() => {
  if (props.mode === 'edit') return 'Enregistrer les modifications'
  if (showImageTerms.value) return 'Lire, signer et publier'
  if (!isUgc.value) return 'Publier la mission'
  // UGC hybride : publié DIRECTEMENT, sans paiement (commission WeAct sur le cash, prélevée
  // par-Face à l'acceptation) — pas de « Payer la commission » au publish (D-8.4.c).
  if (type_compensation.value === 'hybrid') return 'Publier la mission'
  // UGC produit-seul : gate commission produit (pending_payment) → réglée au publish.
  return `Payer la commission · ${formatMoney(ugcCommission.value)}`
})

// Sync duration preset / custom days → duree field
watch([selectedDureePreset, customDureeJours], () => {
  // Clear legacy flag on first user interaction — preserve original value until then
  if (isLegacyDuree.value) {
    isLegacyDuree.value = false
    return
  }
  if (selectedDureePreset.value === 'custom') {
    const days = Number(customDureeJours.value)
    if (!Number.isInteger(days) || days <= 5) {
      customDureeError.value = 'Entrez un nombre de jours entier supérieur à 5'
      return
    }
    customDureeError.value = ''
    duree.value = formatCustomDuration(days)
  } else {
    customDureeError.value = ''
    duree.value = selectedDureePreset.value
  }
})

const apiError = computed(() => error.value)

const pricingHint = computed(() => {
  const b = Number(budget.value) || 0
  const n = Number(nombre_faces_voulu.value) || 0
  if (b <= 0 || n <= 0) return null
  const sousTotal = b * n
  const commission = Math.round(sousTotal * 0.10)
  const total = sousTotal + commission
  return { total, n }
})

const onSubmit = handleSubmit(async (values) => {
  // Garde photos produit UGC à la création : au moins une photo requise (le bouton est
  // déjà désactivé, garde défensive miroir du backend `product_photos.required`).
  if (values.type_mission === 'ugc' && props.mode === 'create' && productPhotos.value.length === 0) {
    productPhotosError.value = 'Au moins une photo du produit est requise.'
    return
  }

  // Base payload WITHOUT budget ni champs de tournage (ajoutés conditionnellement ci-dessous —
  // budget dérivé serveur + date/lieu/durée masqués pour l'UGC, D-1.4.d / D-8.1.e)
  const data: CreateMissionData = {
    titre: values.titre,
    description: values.description,
    profil_recherche: values.profil_recherche,
    date_limite_candidature: values.date_limite_candidature,
    nombre_faces_voulu: values.nombre_faces_voulu,
    type_mission: values.type_mission as CreateMissionData['type_mission'],
    type_mission_autre: values.type_mission_autre || undefined,
    genre_voulu: values.genre_voulu as CreateMissionData['genre_voulu'],
  }

  if (values.type_mission === 'ugc') {
    data.type_compensation = values.type_compensation as 'product' | 'hybrid'
    data.nom_produit = values.nom_produit?.trim()
    data.valeur_produit = Number(values.valeur_produit)
    if (values.type_compensation === 'hybrid') {
      data.nombre_videos = Number(values.nombre_videos)
      data.montant_remuneration = Number(values.montant_remuneration)
    }
    // Upload uniquement à la création (v1) — jamais joint au payload d'édition.
    if (props.mode === 'create' && productPhotos.value.length > 0) {
      data.product_photos = productPhotos.value
    }
  } else {
    data.budget = values.budget as number // Validated by schema before submit
    data.date_tournage = values.date_tournage
    data.lieu = values.lieu
    data.duree = values.duree
  }

  // In edit mode, emit the data to parent for handling
  if (props.mode === 'edit') {
    emit('submit', data)
    return
  }

  // Autorisation d'image : la mission part depuis la fenêtre de signature.
  if (showImageTerms.value) {
    if (!validateImageTerms()) return
    pendingData.value = data
    showSignDialog.value = true
    return
  }

  // In create mode, handle the API call directly
  const result = await createMission(data)

  if (result.success && result.data) {
    emit('success', result.data)
  } else if (result.errors) {
    // Seuls image_terms / signature eux-mêmes réclamés (absents) trahissent un formulaire périmé ;
    // `signature.name` etc. sont de vraies erreurs de saisie traitées dans la fenêtre de signature.
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

const validFields = [
  'titre',
  'description',
  'date_tournage',
  'profil_recherche',
  'budget',
  'date_limite_candidature',
  'nombre_faces_voulu',
  'type_mission',
  'type_mission_autre',
  'genre_voulu',
  'lieu',
  'duree',
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

// Contrepartie telle que la Face la verra : cachet par Face, ou produit (+ rémunération en hybride).
const signCompensation = computed(() => {
  if (isUgc.value) {
    const product = `${nom_produit.value || 'Produit'} (${formatMoney(Number(valeur_produit.value) || 0)})`
    const cash = Number(montant_remuneration.value) || 0
    return type_compensation.value === 'hybrid' && cash > 0
      ? `${product} et ${formatMoney(cash)}`
      : product
  }
  return `Rémunération de ${formatMoney(Number(budget.value) || 0)} par Face`
})

const signSummary = computed(() => ({
  projet: (titre.value ?? '').trim(),
  finalite: imageFinalite.value.trim(),
  supports: imageSupports.value.map(supportLabel),
  territoire: territoireLabel(imageTerritoire.value),
  dureeAnnees: imageDuree.value,
  contrepartie: signCompensation.value,
}))

async function submitSigned(signature: ImageSignature): Promise<SignatureSubmitOutcome> {
  if (!pendingData.value) {
    return { ok: false, message: 'La mission est introuvable. Fermez la fenêtre et recommencez.' }
  }

  const result = await createMission({
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
    emit('success', result.data)
    return { ok: true }
  }

  // Erreur sur la signature (ex. nom refusé) : la fenêtre reste ouverte, le code reste valide côté serveur.
  const signatureError = signatureFieldError(result.errors)
  if (signatureError) return { ok: false, code: signatureError.code, message: signatureError.message }

  const isSignatureError = (result.errorCode ?? '').startsWith('SIGNATURE_CODE')
  if (!isSignatureError && result.errors && Object.keys(result.errors).length > 0) {
    // Erreur sur un champ de la mission : retour au formulaire pour la corriger.
    showSignDialog.value = false
    applyServerErrors(result.errors)
  }
  return { ok: false, code: result.errorCode, message: result.message }
}

function closeSignDialog(): void {
  showSignDialog.value = false
  // L'erreur de code déjà affichée dans la fenêtre ne doit pas rester en bandeau sur le formulaire.
  clearError()
}

const sectionClasses = 'bg-white rounded-2xl border border-gray-100 p-6 mb-6'
</script>

<template>
  <form @submit.prevent="onSubmit" class="pb-24" data-testid="mission-form">
    <fieldset :disabled="isSubmitting" class="contents">

    <!-- Email Not Verified Error -->
    <div
      v-if="isEmailNotVerified"
      class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4"
      data-testid="email-not-verified-alert"
    >
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
          <ShieldAlert class="h-5 w-5 text-amber-600" />
        </div>
        <div class="flex-1">
          <h4 class="text-sm font-semibold text-amber-800">Email non vérifié</h4>
          <p class="mt-1 text-sm text-amber-700">
            Vous devez vérifier votre adresse email avant de pouvoir publier une mission.
          </p>
          <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isResendingVerification"
            @click="handleResendVerification"
            data-testid="resend-verification-button"
          >
            <Mail v-if="!isResendingVerification" class="h-4 w-4" />
            <Loader2 v-else class="h-4 w-4 animate-spin" />
            {{ isResendingVerification ? 'Envoi en cours...' : "Renvoyer l'email de vérification" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Generic API Errors -->
    <div
      v-else-if="apiError"
      class="mb-6 p-4 bg-red-50 border border-red-100 rounded-lg flex items-start gap-3 text-red-700"
      role="alert"
      data-testid="api-error"
    >
      <AlertCircle class="w-5 h-5 mt-0.5 shrink-0" />
      <p class="text-sm font-medium">{{ apiError }}</p>
    </div>

    <!-- Section 1: Informations Générales -->
    <div :class="sectionClasses">
      <div
        class="flex items-center gap-2 mb-6 text-weact font-semibold uppercase tracking-wider text-xs"
      >
        <div class="w-8 h-8 rounded-lg bg-weact/10 flex items-center justify-center">
          <Film class="w-4 h-4" />
        </div>
        Informations Générales
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Titre -->
        <div class="md:col-span-2">
          <FloatingField
            id="titre"
            v-model="titre"
            label="Titre de la mission"
            :icon="Type"
            :error="titreError"
            required
            data-testid="titre-input"
          />
        </div>

        <!-- Type de mission -->
        <FloatingSelect
          id="type_mission"
          v-model="type_mission"
          label="Type de mission"
          :icon="Film"
          :error="typeMissionError"
          :options="missionTypeOptions"
          required
          data-testid="type-mission-select"
        />

        <!-- Précision type de mission (conditionnel) -->
        <FloatingField
          v-if="type_mission === MissionType.AUTRE"
          id="type_mission_autre"
          v-model="type_mission_autre"
          label="Précisez le type de mission"
          :icon="Type"
          :error="typeMissionAutreError"
          required
          data-testid="type-mission-autre-input"
        />

        <!-- Rémunération + Nombre de profils (rémunération masquée pour l'UGC — D-1.4.d) -->
        <FloatingField
          v-if="!isUgc"
          id="budget"
          v-model="budget"
          type="number"
          label="Rémunération par Face (F CFA)"
          :icon="Wallet"
          :error="budgetError"
          required
          min="1"
          data-testid="budget-input"
        />

        <FloatingField
          id="nombre_faces_voulu"
          v-model="nombre_faces_voulu"
          type="number"
          label="Nombre de profils voulus"
          :icon="UserCircle"
          :error="nombreFacesError"
          min="1"
          data-testid="nombre-faces-input"
        />

        <!-- Hint tarifaire réactif (masqué pour l'UGC) -->
        <div
          v-if="pricingHint && !isUgc"
          data-testid="pricing-hint"
          class="md:col-span-2 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm"
        >
          <Wallet class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
          <div class="space-y-0.5 text-muted-foreground">
            <p>Chaque Face sélectionnée recevra <span class="font-semibold text-foreground">{{ formatMoney(Number(budget)) }}</span>.</p>
            <p>
              Total à payer, frais de service inclus :
              <span class="font-semibold text-foreground">{{ formatMoney(pricingHint.total) }}</span>
              <span class="text-xs"> pour {{ pricingHint.n }} profil{{ pricingHint.n > 1 ? 's' : '' }}</span>
            </p>
          </div>
        </div>

        <!-- Description -->
        <div class="md:col-span-2">
          <FloatingTextarea
            id="description"
            v-model="description"
            label="Description détaillée"
            :icon="AlignLeft"
            :error="descriptionError"
            required
            :rows="4"
            data-testid="description-input"
          />
        </div>

        <!-- Bloc Dotation UGC (création + type UGC) -->
        <div v-if="isUgc" class="md:col-span-2 space-y-4">
          <UgcDotationFields
            v-model:compensation-type="type_compensation"
            v-model:nom-produit="nom_produit"
            v-model:valeur-produit="valeur_produit"
            v-model:nombre-videos="nombre_videos"
            v-model:montant-remuneration="montant_remuneration"
            v-model:product-photos="productPhotos"
            :show-product-photos="mode === 'create'"
            :compensation-type-error="typeCompensationError"
            :nom-produit-error="nomProduitError"
            :valeur-produit-error="valeurProduitError"
            :nombre-videos-error="nombreVideosError"
            :montant-remuneration-error="montantRemunerationError"
            :product-photos-error="productPhotosError || undefined"
          />
          <CommissionBreakdown
            mode="mission"
            :product-value="Number(valeur_produit) || 0"
            :pay-amount="type_compensation === 'hybrid' ? (Number(montant_remuneration) || 0) : 0"
            :nombre-faces="Number(nombre_faces_voulu) || 1"
          />
        </div>
      </div>
    </div>

    <!-- Section 2: Casting & Profils -->
    <div :class="sectionClasses">
      <div
        class="flex items-center gap-2 mb-6 text-weact font-semibold uppercase tracking-wider text-xs"
      >
        <div class="w-8 h-8 rounded-lg bg-weact/10 flex items-center justify-center">
          <Users class="w-4 h-4" />
        </div>
        Casting & Profils
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Genre recherché -->
        <FloatingSelect
          id="genre_voulu"
          v-model="genre_voulu"
          label="Genre recherché"
          :icon="Users"
          :error="genreVouluError"
          :options="genderOptions"
          required
          data-testid="genre-voulu-select"
        />

        <!-- Profil recherché -->
        <div class="md:col-span-2">
          <FloatingTextarea
            id="profil_recherche"
            v-model="profil_recherche"
            label="Profil recherché"
            :icon="UserCircle"
            :error="profilRechercheError"
            required
            :rows="3"
            data-testid="profil-recherche-input"
          />
        </div>
      </div>
    </div>

    <!-- Section 3: Dates & Lieu -->
    <div :class="sectionClasses">
      <div
        class="flex items-center gap-2 mb-6 text-weact font-semibold uppercase tracking-wider text-xs"
      >
        <div class="w-8 h-8 rounded-lg bg-weact/10 flex items-center justify-center">
          <MapPin class="w-4 h-4" />
        </div>
        Dates & Lieu
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Lieu (masqué pour l'UGC : dotation sans tournage — D-8.1.e) -->
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

        <!-- Durée (masquée pour l'UGC : dotation sans tournage — D-8.1.e) -->
        <div v-if="!isUgc">
          <FloatingSelect
            id="duree_preset"
            v-model="selectedDureePreset"
            label="Durée du tournage"
            :icon="Clock"
            :error="dureeError"
            :options="MISSION_DURATION_PRESETS"
            required
            data-testid="duree-select"
          />
          <template v-if="selectedDureePreset === 'custom'">
            <FloatingField
              id="duree_custom_jours"
              v-model="customDureeJours"
              type="number"
              label="Nombre de jours"
              :icon="Clock"
              :error="customDureeError"
              required
              min="6"
              class="mt-3"
              data-testid="duree-custom-days"
            />
            <div class="flex items-start gap-2 text-xs text-gray-500 mt-1">
              <Info :size="14" class="shrink-0 mt-0.5 text-blue-400" />
              <span>Au-delà de 5 jours, seuls des jours entiers sont possibles.</span>
            </div>
          </template>
        </div>

        <!-- Date de tournage (masquée pour l'UGC : dotation sans tournage — D-8.1.e) -->
        <FloatingField
          v-if="!isUgc"
          id="date_tournage"
          v-model="date_tournage"
          type="date"
          label="Date de tournage"
          :min="dateTournageMin"
          :icon="Calendar"
          :error="dateTournageError"
          required
          data-testid="date-tournage-input"
        />

        <!-- Date limite de candidature -->
        <FloatingField
          id="date_limite_candidature"
          v-model="date_limite_candidature"
          type="date"
          label="Clôture des candidatures"
          :min="dateLimiteMin"
          :max="dateLimiteMax"
          :icon="Calendar"
          :error="dateLimiteError"
          required
          data-testid="date-limite-input"
        />
      </div>
    </div>

    <!-- Section 4: Utilisation de l'image (création, drapeau serveur) -->
    <div
      v-if="showImageTerms"
      class="mb-6 grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]"
      data-testid="mission-image-terms"
    >
      <ImageTermsFields
        :finalite="imageFinalite"
        :supports="imageSupports"
        :territoire="imageTerritoire"
        :duree-annees="imageDuree"
        :error="imageTermsError"
        :intro="IMAGE_TERMS_INTRO"
        @update:finalite="updateImageFinalite"
        @update:supports="imageSupports = $event"
        @update:territoire="imageTerritoire = $event"
        @update:duree-annees="imageDuree = $event"
      />
      <div
        class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900"
        data-testid="mission-image-terms-info"
      >
        <Info :size="18" class="mt-0.5 shrink-0 text-blue-500" aria-hidden="true" />
        <p>
          Les Faces voient ces conditions avant de candidater. Elles s'appliquent à toutes les Faces retenues
          et ne changent plus après la publication.
        </p>
      </div>
    </div>

    </fieldset>

    <!-- Actions -->
    <div class="mt-8 space-y-4">
      <ImageContractCodeNotice v-if="showImageTerms" />
      <div class="flex justify-end gap-3">
        <Button
          type="button"
          variant="outline"
          @click="emit('cancel')"
          :disabled="isProcessing"
          class="min-w-[120px]"
          data-testid="cancel-button"
        >
          Annuler
        </Button>
        <Button
          type="submit"
          :disabled="isProcessing || ugcPhotosMissing"
          class="min-w-[180px] bg-weact hover:bg-weact/90 text-white shadow-lg shadow-weact/20"
          data-testid="submit-button"
        >
          <template v-if="isProcessing">
            <Loader2 class="w-4 h-4 mr-2 animate-spin" />
            {{ props.mode === 'edit' ? 'Enregistrement...' : 'Publication...' }}
          </template>
          <template v-else>
            <component :is="props.mode === 'edit' ? Save : Send" class="w-4 h-4 mr-2" />
            {{ submitLabel }}
          </template>
        </Button>
      </div>
    </div>

    <!-- Fenêtre de signature (téléportée dans body) -->
    <ImageContractSignDialog
      v-if="showImageTerms"
      flow="mission"
      :is-open="showSignDialog"
      role="producer"
      :summary="signSummary"
      :default-name="signerDefaultName"
      :submit="submitSigned"
      @close="closeSignDialog"
    />
  </form>
</template>

<style scoped>
input,
select,
textarea {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type='number'] {
  -moz-appearance: textfield;
}
</style>
